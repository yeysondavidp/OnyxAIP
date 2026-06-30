<x-layouts.app title="{{ $profile->name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('technicians.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Technicians</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $profile->name }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        @if ($profile->is_active)
            <x-onyx.button href="{{ route('technicians.edit', $profile) }}" variant="outline" size="sm">Edit</x-onyx.button>
        @endif
    </x-slot:headerActions>

    @if (session('success'))
        <x-onyx.alert tone="positive" style="margin-bottom: var(--space-4);">{{ session('success') }}</x-onyx.alert>
    @endif

    <div style="margin-bottom: var(--space-6);">
        <div style="display: flex; align-items: center; gap: var(--space-3); margin-bottom: var(--space-1);">
            <h1 style="font-size: var(--fs-28); font-weight: var(--weight-bold); color: var(--text-primary);">{{ $profile->name }}</h1>
            <x-onyx.badge :tone="$profile->is_active ? 'positive' : 'neutral'" variant="soft">
                {{ $profile->is_active ? 'Active' : 'Deactivated' }}
            </x-onyx.badge>
            @if ($profile->hasAccount())
                <x-onyx.badge tone="info" variant="soft">Account holder</x-onyx.badge>
            @else
                <x-onyx.badge tone="neutral" variant="soft">Guest</x-onyx.badge>
            @endif
        </div>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">{{ $profile->email }}</p>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 300px; gap: var(--space-6); align-items: start;">

        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Skills &amp; competency</h2>
                <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Specialties</dt>
                        <dd>
                            @if ($profile->specialtyLabels())
                                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                                    @foreach ($profile->specialtyLabels() as $label)
                                        <x-onyx.badge tone="info" variant="soft">{{ $label }}</x-onyx.badge>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-tertiary);">None listed</span>
                            @endif
                        </dd>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Certifications</dt>
                        <dd>
                            @if ($profile->certifications)
                                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                                    @foreach ($profile->certifications as $cert)
                                        <span style="background: var(--bronze-100); color: var(--bronze-800); font-size: var(--fs-13); padding: var(--space-1) var(--space-2); border-radius: var(--radius-sm);">{{ $cert }}</span>
                                    @endforeach
                                </div>
                            @else
                                <span style="color: var(--text-tertiary);">None listed</span>
                            @endif
                        </dd>
                    </div>
                    @if ($profile->asset_competency)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Asset competency</dt>
                            <dd style="color: var(--text-primary);">{{ $profile->asset_competency }}</dd>
                        </div>
                    @endif
                </dl>
            </x-onyx.card>

            {{-- Recent jobs --}}
            <x-onyx.card variant="default" padding="none">
                <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle);">
                    <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">Recent jobs</h2>
                </div>
                @if ($profile->jobs->isEmpty())
                    <div style="padding: var(--space-5) var(--space-6);">
                        <p style="font-size: var(--fs-13); color: var(--text-tertiary);">No jobs assigned yet.</p>
                    </div>
                @else
                    @foreach ($profile->jobs as $job)
                        <div style="padding: var(--space-4) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <a href="{{ route('jobs.show', $job) }}"
                                    style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary); text-decoration: none;">
                                    {{ $job->job_name }}
                                </a>
                                <div style="font-size: var(--fs-12); color: var(--text-secondary); margin-top: var(--space-1);">
                                    <span style="font-family: monospace;">{{ $job->job_reference }}</span>
                                </div>
                            </div>
                            <x-onyx.badge :tone="$job->job_status->tone()" variant="soft">{{ $job->job_status->label() }}</x-onyx.badge>
                        </div>
                    @endforeach
                @endif
            </x-onyx.card>

        </div>

        <div style="display: flex; flex-direction: column; gap: var(--space-5);">

            <x-onyx.card variant="default" padding="lg">
                <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-4);">Contact</h2>
                <dl style="display: flex; flex-direction: column; gap: var(--space-3); font-size: var(--fs-14);">
                    <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                        <dt style="color: var(--text-secondary);">Email</dt>
                        <dd><a href="mailto:{{ $profile->email }}" style="color: var(--bronze-600);">{{ $profile->email }}</a></dd>
                    </div>
                    @if ($profile->phone)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Phone</dt>
                            <dd>{{ $profile->phone }}</dd>
                        </div>
                    @endif
                    @if ($profile->user)
                        <div style="display: flex; flex-direction: column; gap: var(--space-1);">
                            <dt style="color: var(--text-secondary);">Account</dt>
                            <dd style="color: var(--text-primary);">{{ $profile->user->name }}</dd>
                        </div>
                    @endif
                </dl>
            </x-onyx.card>

            @if ($profile->is_active)
                <x-onyx.card variant="outline" padding="lg">
                    <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--critical-600); margin-bottom: var(--space-2);">Deactivate</h2>
                    <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-bottom: var(--space-3);">
                        Deactivated technicians are hidden from job assignment but their history is preserved.
                    </p>
                    <form method="POST" action="{{ route('technicians.destroy', $profile) }}"
                        onsubmit="return confirm('Deactivate {{ $profile->name }}?')">
                        @csrf @method('DELETE')
                        <x-onyx.button type="submit" variant="ghost"
                            style="color: var(--critical-600); border-color: var(--critical-300);">
                            Deactivate
                        </x-onyx.button>
                    </form>
                </x-onyx.card>
            @endif

        </div>

    </div>

</x-layouts.app>
