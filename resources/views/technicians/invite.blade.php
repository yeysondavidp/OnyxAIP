<x-layouts.app title="Invite Technicians — {{ $job->job_reference }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('jobs.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Service Jobs</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('jobs.show', $job) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $job->job_reference }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Invite</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Invite Technicians</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">
                Select technicians to invite to <strong>{{ $job->job_name }}</strong>.
                Each will receive an email with a secure, expiring link.
            </p>
        </div>

        @php
            $alreadyInvited = $job->technicians->pluck('id')->toArray();
        @endphp

        <form method="POST" action="{{ route('jobs.invite.send', $job) }}" novalidate
            x-data="{ selected: {{ json_encode($alreadyInvited) }} }">
            @csrf

            @error('profile_ids')
                <x-onyx.alert tone="critical" style="margin-bottom: var(--space-4);">{{ $message }}</x-onyx.alert>
            @enderror

            <x-onyx.card variant="default" padding="none">
                @if ($profiles->isEmpty())
                    <div style="padding: var(--space-6);">
                        <x-onyx.empty title="No active technicians" description="Add technicians to the directory first."
                        ><x-onyx.button href="{{ route('technicians.create') }}" variant="outline" size="sm">Add technician</x-onyx.button></x-onyx.empty>
                    </div>
                @else
                    @foreach ($profiles as $profile)
                        @php $alreadySent = in_array($profile->id, $alreadyInvited); @endphp
                        <label style="display: flex; align-items: center; gap: var(--space-4); padding: var(--space-4) var(--space-5); border-bottom: 1px solid var(--border-subtle); cursor: pointer; min-height: 56px;"
                            :style="selected.includes({{ $profile->id }}) ? 'background: var(--bronze-50);' : ''">
                            <input type="checkbox" name="profile_ids[]" value="{{ $profile->id }}"
                                :checked="selected.includes({{ $profile->id }})"
                                @change="selected.includes({{ $profile->id }}) ? selected.splice(selected.indexOf({{ $profile->id }}), 1) : selected.push({{ $profile->id }})"
                                style="accent-color: var(--bronze-600); flex-shrink: 0; width: 18px; height: 18px;">
                            <div style="flex: 1;">
                                <div style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">
                                    {{ $profile->name }}
                                    @if ($alreadySent)
                                        <span style="font-size: var(--fs-12); color: var(--bronze-600); margin-left: var(--space-2);">(re-invite)</span>
                                    @endif
                                </div>
                                <div style="font-size: var(--fs-12); color: var(--text-secondary);">
                                    {{ $profile->email }}
                                    @if ($profile->specialtyLabels())
                                        · {{ collect($profile->specialtyLabels())->implode(', ') }}
                                    @endif
                                </div>
                            </div>
                            @if ($alreadySent)
                                <x-onyx.badge tone="info" variant="soft">Invited</x-onyx.badge>
                            @endif
                        </label>
                    @endforeach
                @endif
            </x-onyx.card>

            @if ($profiles->isNotEmpty())
                <div style="display: flex; gap: var(--space-3); margin-top: var(--space-5);">
                    <x-onyx.button type="submit" variant="primary">Send invitations</x-onyx.button>
                    <x-onyx.button href="{{ route('jobs.show', $job) }}" variant="ghost">Cancel</x-onyx.button>
                </div>
            @endif

        </form>
    </div>

</x-layouts.app>
