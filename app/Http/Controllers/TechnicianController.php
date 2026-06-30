<?php

namespace App\Http\Controllers;

use App\Enums\TechnicianJobStatus;
use App\Enums\TechnicianSpecialty;
use App\Http\Requests\StoreTechnicianProfileRequest;
use App\Http\Requests\UpdateTechnicianProfileRequest;
use App\Models\Client;
use App\Models\ServiceJob;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\JobInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TechnicianController extends Controller
{
    // ── Directory CRUD (US-09.1) ───────────────────────────────────────────────

    public function index(): View
    {
        $this->authorize('viewAny', TechnicianProfile::class);

        return view('technicians.index');
    }

    public function create(): View
    {
        $this->authorize('create', TechnicianProfile::class);

        return view('technicians.create', $this->formData());
    }

    public function store(StoreTechnicianProfileRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $profile = TechnicianProfile::create([
            'name'                 => $validated['name'],
            'email'                => $validated['email'],
            'phone'                => $validated['phone']                ?? null,
            'specialty_categories' => $validated['specialty_categories'] ?? null,
            'certifications'       => $validated['certifications']       ?? null,
            'preferred_client_ids' => $validated['preferred_client_ids'] ?? null,
            'asset_competency'     => $validated['asset_competency']     ?? null,
            'is_active'            => true,
            'user_id'              => $validated['user_id'] ?? null,
        ]);

        return redirect()
            ->route('technicians.show', $profile)
            ->with('success', "{$profile->name} has been added to the directory.");
    }

    public function show(TechnicianProfile $technician): View
    {
        $this->authorize('view', $technician);

        $technician->load(['user', 'jobs' => fn ($q) => $q->orderByDesc('created_at')->limit(10)]);

        return view('technicians.show', ['profile' => $technician]);
    }

    public function edit(TechnicianProfile $technician): View
    {
        $this->authorize('update', $technician);

        return view('technicians.edit', array_merge($this->formData(), ['profile' => $technician]));
    }

    public function update(UpdateTechnicianProfileRequest $request, TechnicianProfile $technician): RedirectResponse
    {
        $validated = $request->validated();

        $technician->update([
            'name'                 => $validated['name'],
            'email'                => $validated['email'],
            'phone'                => $validated['phone']                ?? null,
            'specialty_categories' => $validated['specialty_categories'] ?? null,
            'certifications'       => $validated['certifications']       ?? null,
            'preferred_client_ids' => $validated['preferred_client_ids'] ?? null,
            'asset_competency'     => $validated['asset_competency']     ?? null,
            'user_id'              => $validated['user_id']              ?? null,
        ]);

        return redirect()
            ->route('technicians.show', $technician)
            ->with('success', "{$technician->name}'s profile has been updated.");
    }

    /** Deactivate (soft-delete) — preserves job/invite history (US-09.1). */
    public function destroy(TechnicianProfile $technician): RedirectResponse
    {
        $this->authorize('delete', $technician);

        $technician->update(['is_active' => false]);
        $technician->delete();

        return redirect()
            ->route('technicians.index')
            ->with('success', "{$technician->name} has been deactivated.");
    }

    // ── Invite to a job (US-09.2) ──────────────────────────────────────────────

    /** Show the invite form — linked from the job show page. */
    public function inviteForm(ServiceJob $job): View
    {
        $this->authorize('update', $job);

        $job->load(['technicians', 'store', 'client']);

        $profiles = TechnicianProfile::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('technicians.invite', compact('job', 'profiles'));
    }

    /** Send (or resend) invitations via queued email (US-09.2). */
    public function sendInvitations(Request $request, ServiceJob $job, JobInvitationService $invitationService): RedirectResponse
    {
        $this->authorize('update', $job);

        $validated = $request->validate([
            'profile_ids'   => ['required', 'array', 'min:1'],
            'profile_ids.*' => ['integer', 'exists:technician_profiles,id'],
        ], [
            'profile_ids.required' => 'Please select at least one technician to invite.',
        ]);

        $profiles = TechnicianProfile::whereIn('id', $validated['profile_ids'])
            ->where('is_active', true)
            ->get();

        $actor = auth()->user();

        foreach ($profiles as $profile) {
            $invitationService->invite($job, $profile, $actor);
        }

        $count = $profiles->count();

        return redirect()
            ->route('jobs.show', $job)
            ->with('success', "Invitation sent to {$count} technician".($count === 1 ? '' : 's').'.');
    }

    // ── Accept / Decline via signed link (US-09.3) ─────────────────────────────

    /**
     * Show the mobile-first accept/decline screen for a technician.
     * Route protected by 'signed' middleware; token validated here.
     */
    public function inviteResponse(Request $request, ServiceJob $job, JobInvitationService $invitationService): View
    {
        $token     = (string) $request->query('token', '');
        $profileId = $invitationService->resolveToken($job->id, $token);

        if ($profileId === null) {
            return view('technician.link-expired');
        }

        $profile = TechnicianProfile::findOrFail($profileId);
        $job->load(['store', 'assets']);

        $pivotRow = DB::table('job_technicians')
            ->where('job_id', $job->id)
            ->where('technician_profile_id', $profileId)
            ->first();

        $currentStatus = $pivotRow
            ? TechnicianJobStatus::tryFrom($pivotRow->technician_status)
            : null;

        return view('technicians.invite-response', compact('job', 'profile', 'token', 'currentStatus'));
    }

    /** POST: Accept invitation (invited → accepted). */
    public function acceptInvite(Request $request, ServiceJob $job, JobInvitationService $invitationService): RedirectResponse
    {
        $token     = (string) $request->input('token', '');
        $profileId = $invitationService->resolveToken($job->id, $token);

        if ($profileId === null) {
            return redirect()->route('technician.link-expired');
        }

        DB::table('job_technicians')
            ->where('job_id', $job->id)
            ->where('technician_profile_id', $profileId)
            ->where('technician_status', TechnicianJobStatus::Invited->value)
            ->update(['technician_status' => TechnicianJobStatus::Accepted->value]);

        return back()->with('accepted', true);
    }

    /** POST: Decline invitation (invited → declined). */
    public function declineInvite(Request $request, ServiceJob $job, JobInvitationService $invitationService): RedirectResponse
    {
        $token     = (string) $request->input('token', '');
        $profileId = $invitationService->resolveToken($job->id, $token);

        if ($profileId === null) {
            return redirect()->route('technician.link-expired');
        }

        DB::table('job_technicians')
            ->where('job_id', $job->id)
            ->where('technician_profile_id', $profileId)
            ->where('technician_status', TechnicianJobStatus::Invited->value)
            ->update(['technician_status' => TechnicianJobStatus::Declined->value]);

        return back()->with('declined', true);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function formData(): array
    {
        $specialties = TechnicianSpecialty::cases();
        $clients     = Client::where('is_active', true)->orderBy('client_name')->get(['id', 'client_name']);
        $techUsers   = User::where('role', 'technician')->orderBy('name')->get(['id', 'name', 'email']);

        return compact('specialties', 'clients', 'techUsers');
    }
}
