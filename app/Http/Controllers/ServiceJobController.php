<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\EarlyStartWindow;
use App\Enums\JobStatus;
use App\Enums\JobType;
use App\Http\Requests\StoreServiceJobRequest;
use App\Http\Requests\UpdateServiceJobRequest;
use App\Http\Requests\UploadJobAttachmentRequest;
use App\Models\Asset;
use App\Models\Client;
use App\Models\JobAttachment;
use App\Models\ServiceJob;
use App\Models\Store;
use App\Models\User;
use App\Services\AssetTransitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
        $store    = Store::findOrFail((int) $validated['store_id']);
        $clientId = $store->client_id;

        // Determine job_level from parent (US-08.5)
        $parentJobId = isset($validated['parent_job_id']) ? (int) $validated['parent_job_id'] : null;
        $jobLevel    = 0;

        if ($parentJobId !== null) {
            $parent   = ServiceJob::findOrFail($parentJobId);
            $jobLevel = $parent->job_level + 1;
        }

        $job = DB::transaction(function () use ($validated, $store, $clientId, $parentJobId, $jobLevel) {
            $job = ServiceJob::create([
                'job_reference'      => $validated['job_reference'],
                'job_name'           => $validated['job_name'],
                'job_description'    => $validated['job_description'],
                'job_type'           => $validated['job_type'],
                'client_id'          => $clientId,
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
            ]);

            // Attach affected assets — auto-transition eligible ones to UnderMaintenance (US-08.2)
            $this->syncAffectedAssets($job, $validated['asset_ids'] ?? []);

            // Assign technicians (US-08.4)
            $this->syncTechnicians($job, $validated['technician_ids'] ?? []);

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
            $this->syncTechnicians($job, $validated['technician_ids'] ?? []);
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

    /** PM validates a completed job (Completed → Validated). */
    public function validate(Request $request, ServiceJob $job): RedirectResponse
    {
        $this->authorize('validate', $job);

        $job->transitionTo(JobStatus::Validated, auth()->user());

        return back()->with('success', 'Job has been validated.');
    }

    /** PM flags a completed job for remediation (Completed → RequiresRemediation). */
    public function flagRemediation(ServiceJob $job): RedirectResponse
    {
        $this->authorize('flagRemediation', $job);

        $job->transitionTo(JobStatus::RequiresRemediation, auth()->user());

        return back()->with('success', 'Job flagged for remediation. A sub-job can now be created.');
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

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Sync the affected-assets pivot and auto-transition eligible assets
     * to UnderMaintenance via the AssetTransitionService (US-08.2).
     *
     * @param  list<int>  $assetIds
     */
    private function syncAffectedAssets(ServiceJob $job, array $assetIds): void
    {
        $job->assets()->sync($assetIds);

        if (empty($assetIds)) {
            return;
        }

        $transitionService = app(AssetTransitionService::class);

        Asset::whereIn('id', $assetIds)
            ->whereIn('asset_status', [AssetStatus::Active->value, AssetStatus::Faulty->value])
            ->get()
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
     * Sync the assigned-technicians pivot, preserving existing lifecycle state
     * and initialising new entries as 'invited' (US-08.4).
     *
     * @param  list<int>  $technicianIds
     */
    private function syncTechnicians(ServiceJob $job, array $technicianIds): void
    {
        $existing = $job->technicians()->pluck('technician_status', 'users.id')->toArray();

        $syncData = [];
        foreach ($technicianIds as $userId) {
            $syncData[(int) $userId] = [
                'technician_status' => $existing[(int) $userId] ?? 'invited',
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
        $technicians       = User::where('role', 'technician')->orderBy('name')->get(['id', 'name', 'email']);
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
