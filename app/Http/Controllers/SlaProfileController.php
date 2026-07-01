<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringCoverage;
use App\Http\Requests\StoreSlaProfileRequest;
use App\Http\Requests\UpdateSlaProfileRequest;
use App\Models\SlaProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SlaProfileController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', SlaProfile::class);

        return view('sla-profiles.index');
    }

    public function create(): View
    {
        $this->authorize('create', SlaProfile::class);

        return view('sla-profiles.create', ['coverages' => MonitoringCoverage::cases()]);
    }

    public function store(StoreSlaProfileRequest $request): RedirectResponse
    {
        $profile = SlaProfile::create($request->validated() + ['is_active' => true]);

        return redirect()
            ->route('sla-profiles.show', $profile)
            ->with('success', "SLA profile '{$profile->name}' has been created.");
    }

    public function show(SlaProfile $slaProfile): View
    {
        $this->authorize('view', $slaProfile);

        return view('sla-profiles.show', [
            'profile' => $slaProfile,
            'clients' => $slaProfile->clients()->where('is_active', true)->orderBy('client_name')->get(),
        ]);
    }

    public function edit(SlaProfile $slaProfile): View
    {
        $this->authorize('update', $slaProfile);

        return view('sla-profiles.edit', [
            'profile'   => $slaProfile,
            'coverages' => MonitoringCoverage::cases(),
        ]);
    }

    public function update(UpdateSlaProfileRequest $request, SlaProfile $slaProfile): RedirectResponse
    {
        $slaProfile->update($request->validated());

        return redirect()
            ->route('sla-profiles.show', $slaProfile)
            ->with('success', "SLA profile '{$slaProfile->name}' has been updated.");
    }

    public function destroy(SlaProfile $slaProfile): RedirectResponse
    {
        $this->authorize('delete', $slaProfile);

        $slaProfile->update(['is_active' => false]);

        return redirect()
            ->route('sla-profiles.index')
            ->with('success', "SLA profile '{$slaProfile->name}' has been deactivated.");
    }
}
