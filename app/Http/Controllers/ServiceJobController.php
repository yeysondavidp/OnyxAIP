<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\EarlyStartWindow;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Enums\PhotoType;
use App\Enums\PostServiceStatus;
use App\Http\Requests\StoreServiceJobRequest;
use App\Http\Requests\UpdateServiceJobRequest;
use App\Http\Requests\UploadJobAttachmentRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\JobAssetOutcome;
use App\Models\JobAttachment;
use App\Models\JobCheckpoint;
use App\Models\JobPhoto;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\TechnicianProfile;
use App\Services\AssetTransitionService;
use App\Services\JobValidationService;
use App\Services\Sla\SlaClockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServiceJobController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(): View
    {
        $this->authorize('viewAny', ServiceJob::class);

        return view('service-jobs.index');
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    public function create(Request $request): View
    {
        $this->authorize('create', ServiceJob::class);

        // Pre-select store from query string (e.g. link from store dashboard)
        $selectedStore = $request->query('store_id')
            ? Store::find((int) $request->query('store_id'))
            : null;

        return view('service-jobs.create', $this->formData(selectedStore: $selectedStore));
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(StoreServiceJobRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Derive client_id server-side — never trusted from request input
        $store  = Store::findOrFail((int) $validated['store_id']);
        $client = Client::with('slaProfile')->findOrFail($store->client_id);

        // Determine job_level from parent (US-08.5)
        $parentJobId = isset($validated['parent_job_id']) ? (int) $validated['parent_job_id'] : null;
        $jobLevel    = 0;

        if ($parentJobId !== null) {
            $parent   = ServiceJob::findOrFail($parentJobId);
            $jobLevel = $parent->job_level + 1;
        }

        // SLA clock starts only for fault jobs against a client with an active profile (US-12.2)
        $slaFields = $validated['job_type'] === JobType::FaultRepair->value
            ? app(SlaClockService::class)->resolveClockFields($client, $store)
            : [];

        $job = DB::transaction(function () use ($validated, $store, $client, $parentJobId, $jobLevel, $slaFields) {
            $job = ServiceJob::create([
                'job_reference'      => $validated['job_reference'],
                'job_name'           => $validated['job_name'],
                'job_description'    => $validated['job_description'],
                'job_type'           => $validated['job_type'],
                'client_id'          => $client->id,
                'store_id'           => $store->id,
                'job_timezone'       => $store->store_timezone,
                'scheduled_date'     => $validated['scheduled_date'] ?? null,
                'scheduled_time'     => $validated['scheduled_time'] ?? null,
                'early_start_window' => $validated['early_start_window'],
                'job_status'         => JobStatus::Draft->value,
                'parent_job_id'      => $parentJobId,
                'job_level'          => $jobLevel,
                'client_email'       => $validated['client_email'] ?? null,
                'client_name'        => $validated['client_name']  ?? null,
                ...$slaFields,
            ]);

            // Attach affected assets — auto-transition eligible ones to UnderMaintenance (US-08.2)
            $this->syncAffectedAssets($job, $validated['asset_ids'] ?? []);

            // Assign technicians (US-08.4)
            $this->syncTechnicians($job, $validated['technician_profile_ids'] ?? []);

            return $job;
        });

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', "Job '{$job->job_name}' has been created.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(ServiceJob $job): View
    {
        $this->authorize('view', $job);

        $job->load([
            'client',
            'store',
            'parent',
            'children',
            'assets',
            'technicians',
            'attachments',
        ]);

        return view('service-jobs.show', compact('job'));
    }

    // ── Edit ───────────────────────────────────────────────────────────────────

    public function edit(ServiceJob $job): View
    {
        $this->authorize('update', $job);

        $job->load(['assets', 'technicians', 'attachments']);

        return view('service-jobs.edit', array_merge(
            $this->formData(),
            compact('job'),
        ));
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(UpdateServiceJobRequest $request, ServiceJob $job): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $job) {
            $job->update([
                'job_reference'      => $validated['job_reference'],
                'job_name'           => $validated['job_name'],
                'job_description'    => $validated['job_description'],
                'job_type'           => $validated['job_type'],
                'scheduled_date'     => $validated['scheduled_date'] ?? null,
                'scheduled_time'     => $validated['scheduled_time'] ?? null,
                'early_start_window' => $validated['early_start_window'],
                'client_email'       => $validated['client_email'] ?? null,
                'client_name'        => $validated['client_name']  ?? null,
            ]);

            $this->syncAffectedAssets($job, $validated['asset_ids'] ?? []);
            $this->syncTechnicians($job, $validated['technician_profile_ids'] ?? []);
        });

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', "Job '{$job->job_name}' has been updated.");
    }

    // ── Cancel (soft-delete) ───────────────────────────────────────────────────

    public function destroy(ServiceJob $job): RedirectResponse
    {
        $this->authorize('cancel', $job);

        $job->transitionTo(JobStatus::Cancelled, auth()->user());

        return redirect()
            ->route('jobs.index')
            ->with('success', "Job '{$job->job_name}' has been cancelled.");
    }

    // ── Transitions ────────────────────────────────────────────────────────────

    /** PM invites technicians (Draft → Invited). */
    public function invite(ServiceJob $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $job->transitionTo(JobStatus::Invited, auth()->user());

        return back()->with('success', 'Job invitation sent to assigned technicians.');
    }

    /**
     * Review surface for a Completed job — evidence + per-asset decision form (US-11.1).
     */
    public function showValidation(ServiceJob $job): View
    {
        $this->authorize('validate', $job);

        $job->load(['store', 'client', 'assets', 'technicians']);

        $checkpoints = JobCheckpoint::where('job_id', $job->id)
            ->with('profile')
            ->get();

        $outcomes = JobAssetOutcome::where('job_id', $job->id)->get()->keyBy('asset_id');

        $beforePhotos = JobPhoto::where('job_id', $job->id)->where('type', PhotoType::Before->value)->get();
        $afterPhotos  = JobPhoto::where('job_id', $job->id)->where('type', PhotoType::After->value)->get();

        $postStatuses = PostServiceStatus::cases();

        return view('service-jobs.validate', compact(
            'job', 'checkpoints', 'outcomes', 'beforePhotos', 'afterPhotos', 'postStatuses'
        ));
    }

    /** PM validates a completed job (Completed → Validated), resolving per-asset outcomes (US-11.1). */
    public function validate(Request $request, ServiceJob $job): RedirectResponse
    {
        $this->authorize('validate', $job);

        $validated = $request->validate([
            'decisions'   => ['nullable', 'array'],
            'decisions.*' => ['string', Rule::enum(PostServiceStatus::class)],
        ]);

        try {
            app(JobValidationService::class)->validate($job, auth()->user(), $validated['decisions'] ?? []);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['decisions' => $e->getMessage()]);
        }

        return redirect()->route('jobs.show', $job)->with('success', 'Job has been validated.');
    }

    /** PM flags a completed job for remediation and spawns the sub-job (US-11.2). */
    public function flagRemediation(Request $request, ServiceJob $job): RedirectResponse
    {
        $this->authorize('flagRemediation', $job);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'reason.required' => 'A reason is required to flag a job for remediation.',
        ]);

        try {
            $remediation = app(JobValidationService::class)
                ->flagRemediation($job, auth()->user(), $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['reason' => $e->getMessage()]);
        }

        return redirect()
            ->route('jobs.show', $remediation)
            ->with('success', "Job flagged for remediation. Sub-job '{$remediation->job_reference}' created.");
    }

    /** PM force-completes a job (InProgress → Completed) with a mandatory reason. */
    public function forceComplete(Request $request, ServiceJob $job): RedirectResponse
    {
        $this->authorize('forceComplete', $job);

        $validated = $request->validate([
            'force_complete_reason' => ['required', 'string', 'max:1000'],
        ], [
            'force_complete_reason.required' => 'A reason is required when force-completing a job.',
        ]);

        $job->transitionTo(
            JobStatus::Completed,
            auth()->user(),
            $validated['force_complete_reason']
        );

        return back()->with('success', 'Job has been marked as completed.');
    }

    // ── Attachments ────────────────────────────────────────────────────────────

    /** Upload a PM attachment (US-08.6). */
    public function storeAttachment(UploadJobAttachmentRequest $request, ServiceJob $job): RedirectResponse
    {
        $file = $request->file('attachment');

        // Detect MIME server-side — never trust client-supplied content-type
        $detectedMime = $file->getMimeType() ?? '';

        if (! in_array($detectedMime, UploadJobAttachmentRequest::ALLOWED_MIMES, strict: true)) {
            return back()->withErrors(['attachment' => 'File type is not permitted.']);
        }

        $path = $file->store('job-attachments/'.$job->id, 'local');

        JobAttachment::create([
            'job_id'            => $job->id,
            'original_filename' => $file->getClientOriginalName(),
            'stored_path'       => (string) $path,
            'mime_type'         => $detectedMime,
            'file_size'         => $file->getSize() ?: null,
        ]);

        return back()->with('success', 'Attachment uploaded.');
    }

    /** Serve an attachment via a signed, expiring download URL (US-08.6). */
    public function downloadAttachment(ServiceJob $job, JobAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $job);

        abort_if($attachment->job_id !== $job->id, 403);

        return Storage::disk('local')->download(
            $attachment->stored_path,
            $attachment->original_filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    /** Delete a PM attachment. */
    public function destroyAttachment(ServiceJob $job, JobAttachment $attachment): RedirectResponse
    {
        $this->authorize('manageAttachments', $job);

        abort_if($attachment->job_id !== $job->id, 403);

        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /** Serve a before/after job photo for PM review (US-11.1) — no public route, scoped by policy. */
    public function downloadPhoto(ServiceJob $job, JobPhoto $photo): StreamedResponse
    {
        $this->authorize('view', $job);

        abort_if($photo->job_id !== $job->id, 403);

        return Storage::disk('local')->download(
            $photo->stored_path,
            $photo->type->value.'-photo-'.$photo->id.'.jpg',
            ['Content-Type' => $photo->mime_type]
        );
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Sync the affected-assets pivot and auto-transition eligible assets
     * to UnderMaintenance via the AssetTransitionService (US-08.2).
     *
     * Captures each asset's status_before on the pivot at attach time — the
     * reference point US-11.1 needs at validation, taken before the auto-
     * transition below. Preserved across re-syncs on edit (never overwritten
     * once set) so re-saving the job form doesn't lose the original value.
     *
     * @param  list<int>  $assetIds
     */
    private function syncAffectedAssets(ServiceJob $job, array $assetIds): void
    {
        $existingStatusBefore = $job->assets()->pluck('job_assets.status_before', 'assets.id')->toArray();

        if (empty($assetIds)) {
            $job->assets()->sync([]);

            return;
        }

        $assets = Asset::whereIn('id', $assetIds)->get(['id', 'asset_status']);

        $syncData = [];
        foreach ($assets as $asset) {
            $syncData[$asset->id] = [
                'status_before' => $existingStatusBefore[$asset->id] ?? $asset->asset_status->value,
            ];
        }

        $job->assets()->sync($syncData);

        $transitionService = app(AssetTransitionService::class);

        $assets
            ->filter(fn (Asset $a) => in_array($a->asset_status, [AssetStatus::Active, AssetStatus::Faulty], strict: true))
            ->each(function (Asset $asset) use ($transitionService): void {
                try {
                    $transitionService->transitionTo(
                        $asset,
                        AssetStatus::UnderMaintenance,
                        systemLabel: 'system:job_created'
                    );
                } catch (\InvalidArgumentException) {
                    // Already Under Maintenance or ineligible — skip silently
                }
            });
    }

    /**
     * Sync the assigned-technician-profiles pivot, preserving existing lifecycle state
     * and initialising new entries as 'invited' (US-08.4/09.1).
     *
     * @param  list<int>  $profileIds
     */
    private function syncTechnicians(ServiceJob $job, array $profileIds): void
    {
        $existing = $job->technicians()
            ->pluck('technician_status', 'job_technicians.technician_profile_id')
            ->toArray();

        $syncData = [];
        foreach ($profileIds as $profileId) {
            $syncData[(int) $profileId] = [
                'technician_status' => $existing[(int) $profileId] ?? 'invited',
            ];
        }

        $job->technicians()->sync($syncData);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Store $selectedStore = null): array
    {
        $clients        = Client::where('is_active', true)->orderBy('client_name')->get();
        $storesByClient = $clients->mapWithKeys(fn (Client $c) => [
            (int) $c->id => Store::where('client_id', $c->id)
                ->where('is_active', true)
                ->orderBy('store_name')
                ->get()
                ->map(fn (Store $s) => ['id' => (int) $s->id, 'name' => $s->store_name, 'timezone' => $s->store_timezone])
                ->values(),
        ]);

        $jobTypes          = JobType::cases();
        $earlyStartWindows = EarlyStartWindow::cases();
        $technicians       = TechnicianProfile::where('is_active', true)->orderBy('name')->get(['id', 'name', 'email']);
        $parentJobs        = ServiceJob::where('job_level', 0)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'job_reference', 'job_name', 'client_id']);

        return compact(
            'clients',
            'storesByClient',
            'jobTypes',
            'earlyStartWindows',
            'technicians',
            'parentJobs',
            'selectedStore',
        );
    }
}
