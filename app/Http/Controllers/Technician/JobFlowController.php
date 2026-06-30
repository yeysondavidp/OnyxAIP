<?php

namespace App\Http\Controllers\Technician;

use App\Enums\AssetStatus;
use App\Enums\PhotoType;
use App\Enums\PostServiceStatus;
use App\Enums\TechnicianJobStatus;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\JobAssetOutcome;
use App\Models\JobCheckpoint;
use App\Models\JobPhoto;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Services\JobFlowService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Drives the 5-screen technician mobile workflow (EPIC-10, SRA §6).
 *
 * Every method:
 *   1. Is under the 'signed' middleware — Laravel validates the signed URL.
 *   2. Calls resolveIdentity() — validates the invitation token matches the DB.
 *   3. Authorises the actor against the specific (job, technician) binding.
 *
 * Alpine-first (ADR-001): camera/GPS/photo-preview/wizard-nav are client-side.
 * The server is contacted only at business checkpoints (Start, photo-submit, Complete).
 */
class JobFlowController extends Controller
{
    use ResolvesJobFlowIdentity;

    // ── Screen 1 — Job Overview ────────────────────────────────────────────────

    public function overview(Request $request, string $job): View|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;

        /** @var ServiceJob $jobModel */
        $jobModel->load(['store', 'client', 'assets']);

        // .ics calendar data for before-photo download
        $icsContent = $this->buildIcsContent($jobModel);

        return view('technician.screen-1-overview', compact('jobModel', 'profile', 'icsContent'));
    }

    // ── Start checkpoint (Screen 1 → Screen 2) ────────────────────────────────

    public function start(Request $request, string $job): RedirectResponse|View
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;

        /** @var ServiceJob $jobModel @var TechnicianProfile $profile */
        $validated = $request->validate([
            'gps_lat'    => ['nullable', 'numeric'],
            'gps_lng'    => ['nullable', 'numeric'],
            'gps_status' => ['required', 'string', 'in:granted,denied,failed,skipped'],
        ]);

        $flowService = app(JobFlowService::class);

        try {
            $flowService->startJob($jobModel, $profile, [
                'lat'        => isset($validated['gps_lat']) ? (float) $validated['gps_lat'] : null,
                'lng'        => isset($validated['gps_lng']) ? (float) $validated['gps_lng'] : null,
                'gps_status' => $validated['gps_status'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['gps_status' => $e->getMessage()]);
        }

        // Log GPS failure for observability (non-blocking, §14.6)
        if (in_array($validated['gps_status'], ['denied', 'failed'], strict: true)) {
            $flowService->logGpsFailure($jobModel->id, $profile->id, $validated['gps_status']);
        }

        // Redirect to Screen 2, preserving signed params
        return redirect()->route('technician.job.before-photos', array_merge(
            ['job' => $jobModel->id],
            $request->only(['token', 'technician_profile_id', 'expires', 'signature'])
        ));
    }

    // ── Cancel Screen 2 ────────────────────────────────────────────────────────

    public function cancelStart(Request $request, string $job): RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;
        app(JobFlowService::class)->cancelStart($jobModel, $profile);

        return redirect()->route('technician.job.overview', array_merge(
            ['job' => $jobModel->id],
            $request->only(['token', 'technician_profile_id', 'expires', 'signature'])
        ));
    }

    // ── Screen 2 — Before photos ───────────────────────────────────────────────

    public function beforePhotos(Request $request, string $job): View|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;

        $pivotRow = DB::table('job_technicians')
            ->where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->first();

        // Must have Started this job to see Screen 2
        if (! $pivotRow || $pivotRow->technician_status !== TechnicianJobStatus::Started->value) {
            return redirect()->route('technician.job.overview', array_merge(
                ['job' => $jobModel->id],
                $request->only(['token', 'technician_profile_id', 'expires', 'signature'])
            ));
        }

        $uploadedCount = JobPhoto::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::Before->value)
            ->count();

        return view('technician.screen-2-before-photos', compact('jobModel', 'profile', 'uploadedCount'));
    }

    // ── Screen 3 — Briefing & asset reference ─────────────────────────────────

    public function brief(Request $request, string $job): View|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;
        $jobModel->load(['store', 'assets', 'attachments']);

        return view('technician.screen-3-brief', compact('jobModel', 'profile'));
    }

    // ── In-flow asset status update (Screen 3) ────────────────────────────────

    public function updateAssetStatus(Request $request, string $job, string $asset): JsonResponse|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return response()->json(['error' => 'Link expired.'], 403);
        }

        [$jobModel, $profile] = $result;

        $validated = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = AssetStatus::tryFrom($validated['status']);
        if (! $newStatus) {
            return response()->json(['error' => 'Invalid status.'], 422);
        }

        $assetModel = Asset::find((int) $asset);
        if (! $assetModel) {
            return response()->json(['error' => 'Asset not found.'], 404);
        }

        try {
            app(JobFlowService::class)->updateAssetStatus($jobModel, $assetModel, $newStatus);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'new_status' => $newStatus->value, 'label' => $newStatus->label()]);
    }

    // ── Screen 4 — After photos ───────────────────────────────────────────────

    public function afterPhotos(Request $request, string $job): View|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;
        $jobModel->load('assets');

        $uploadedAfterCount = JobPhoto::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::After->value)
            ->count();

        $postStatuses = PostServiceStatus::cases();

        return view('technician.screen-4-after-photos', compact('jobModel', 'profile', 'uploadedAfterCount', 'postStatuses'));
    }

    // ── Photo upload endpoint (US-10.6 — idempotent, rate-limited) ─────────────

    public function uploadPhoto(Request $request, string $job): JsonResponse|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return response()->json(['error' => 'Link expired.'], 403);
        }

        [$jobModel, $profile] = $result;

        $validated = $request->validate([
            'photo'            => ['required', 'file', 'max:20480'],
            'type'             => ['required', 'in:before,after'],
            'client_upload_id' => ['required', 'string', 'max:64'],
        ]);

        $photoType = PhotoType::from($validated['type']);
        $file      = $request->file('photo');

        try {
            $photoId = app(JobFlowService::class)->storePhoto(
                $jobModel,
                $profile,
                $photoType,
                $file,
                $validated['client_upload_id']
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'id' => $photoId]);
    }

    // ── Complete checkpoint (Screen 4 → Screen 5) ─────────────────────────────

    public function complete(Request $request, string $job): RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;

        $validated = $request->validate([
            'gps_lat'             => ['nullable', 'numeric'],
            'gps_lng'             => ['nullable', 'numeric'],
            'gps_status'          => ['required', 'string', 'in:granted,denied,failed,skipped'],
            'completion_notes'    => ['nullable', 'string', 'max:1000'],
            'outcomes'            => ['nullable', 'array'],
            'outcomes.*.asset_id' => ['required', 'integer'],
            'outcomes.*.status'   => ['required', 'string'],
            'outcomes.*.notes'    => ['nullable', 'string', 'max:500'],
        ]);

        // Validate at least 1 after-photo uploaded
        $afterPhotoCount = JobPhoto::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::After->value)
            ->count();

        if ($afterPhotoCount === 0) {
            return back()->withErrors(['after_photos' => 'At least one after-photo is required before submitting.']);
        }

        // Validate + map outcome statuses against enum
        $outcomes = [];
        foreach ($validated['outcomes'] ?? [] as $outcome) {
            $ps = PostServiceStatus::tryFrom($outcome['status']);
            if (! $ps) {
                return back()->withErrors(['outcomes' => 'Invalid post-service status: '.$outcome['status']]);
            }

            $outcomes[] = [
                'asset_id'            => (int) $outcome['asset_id'],
                'post_service_status' => $ps->value,
                'notes'               => $outcome['notes'] ?? null,
            ];
        }

        $flowService = app(JobFlowService::class);

        $flowService->completeJob(
            $jobModel,
            $profile,
            [
                'lat'        => isset($validated['gps_lat']) ? (float) $validated['gps_lat'] : null,
                'lng'        => isset($validated['gps_lng']) ? (float) $validated['gps_lng'] : null,
                'gps_status' => $validated['gps_status'],
            ],
            $outcomes,
            $validated['completion_notes'] ?? null,
        );

        if (in_array($validated['gps_status'], ['denied', 'failed'], strict: true)) {
            $flowService->logGpsFailure($jobModel->id, $profile->id, $validated['gps_status']);
        }

        return redirect()->route('technician.job.summary', array_merge(
            ['job' => $jobModel->id],
            $request->only(['token', 'technician_profile_id', 'expires', 'signature'])
        ));
    }

    // ── Screen 5 — Job summary (read-only) ────────────────────────────────────

    public function summary(Request $request, string $job): View|RedirectResponse
    {
        $result = $this->resolveIdentity($request, $job);
        if ($this->isExpiredRedirect($result)) {
            return $result;
        }

        [$jobModel, $profile] = $result;
        $jobModel->load(['store', 'assets', 'client']);

        $checkpoint = JobCheckpoint::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->first();

        $beforePhotos = JobPhoto::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::Before->value)
            ->get();

        $afterPhotos = JobPhoto::where('job_id', $jobModel->id)
            ->where('technician_profile_id', $profile->id)
            ->where('type', PhotoType::After->value)
            ->get();

        $outcomes = JobAssetOutcome::where('job_id', $jobModel->id)->get()->keyBy('asset_id');

        return view('technician.screen-5-summary', compact(
            'jobModel', 'profile', 'checkpoint', 'beforePhotos', 'afterPhotos', 'outcomes'
        ));
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function buildIcsContent(ServiceJob $job): ?string
    {
        if (! $job->scheduled_date || ! $job->scheduled_time) {
            return null;
        }

        $startUtc = Carbon::parse(
            Carbon::parse((string) $job->scheduled_date)->format('Y-m-d').' '.$job->scheduled_time,
            'UTC'
        );
        $endUtc = $startUtc->copy()->addHours(2);
        /** @var Store|null $store */
        $store    = $job->store;
        $location = $store
            ? "{$store->address_line1}, {$store->suburb} {$store->state->value}"
            : '';

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ONYX AIP//EN',
            'BEGIN:VEVENT',
            'UID:'.$job->job_reference.'@onyx-aip',
            'DTSTART:'.$startUtc->format('Ymd\THis\Z'),
            'DTEND:'.$endUtc->format('Ymd\THis\Z'),
            'SUMMARY:'.str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $job->job_name),
            'DESCRIPTION:'.str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $job->job_description),
            'LOCATION:'.str_replace([',', ';', '\\', "\n"], ['\\,', '\\;', '\\\\', '\\n'], $location),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);
    }
}
