<x-layouts.app title="Edit — {{ $profile->name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('sla-profiles.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">SLA Profiles</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('sla-profiles.show', $profile) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $profile->name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit SLA Profile</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">{{ $profile->name }}</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('sla-profiles.update', $profile) }}" novalidate>
                @csrf
                @method('PATCH')

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="name"
                        label="Profile name"
                        type="text"
                        :value="old('name', $profile->name)"
                        :error="$errors->first('name')"
                        required
                        autocomplete="off"
                    />

                    <x-onyx.divider />

                    <x-onyx.input
                        name="acknowledgement_hours"
                        label="Acknowledgement window (business hours)"
                        type="number"
                        min="1"
                        :value="old('acknowledgement_hours', $profile->acknowledgement_hours)"
                        :error="$errors->first('acknowledgement_hours')"
                        required
                    />

                    <x-onyx.input
                        name="onsite_response_metro_hours"
                        label="On-site response — metro (business hours)"
                        type="number"
                        min="1"
                        :value="old('onsite_response_metro_hours', $profile->onsite_response_metro_hours)"
                        :error="$errors->first('onsite_response_metro_hours')"
                        required
                    />

                    <x-onyx.input
                        name="onsite_response_regional_hours"
                        label="On-site response — regional (business hours)"
                        type="number"
                        min="1"
                        :value="old('onsite_response_regional_hours', $profile->onsite_response_regional_hours)"
                        :error="$errors->first('onsite_response_regional_hours')"
                        required
                    />

                    <x-onyx.input
                        name="resolution_hours"
                        label="Resolution target (business hours)"
                        type="number"
                        min="1"
                        :value="old('resolution_hours', $profile->resolution_hours)"
                        :error="$errors->first('resolution_hours')"
                        helper="The overall deadline the SLA clock tracks."
                        required
                    />

                    <x-onyx.divider />

                    <x-onyx.select name="monitoring_coverage" label="Monitoring coverage" :error="$errors->first('monitoring_coverage')" required>
                        @foreach ($coverages as $coverage)
                            <option value="{{ $coverage->value }}" @selected(old('monitoring_coverage', $profile->monitoring_coverage->value) === $coverage->value)>
                                {{ $coverage->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('sla-profiles.show', $profile) }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save changes</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>

        @if ($profile->is_active)
            <div style="margin-top: var(--space-6);">
                <x-onyx.card variant="outline" padding="md">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4);">
                        <div>
                            <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Deactivate profile</p>
                            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                                The profile and its history are preserved. Deactivated profiles are hidden from active lists
                                and can no longer be assigned to a client.
                            </p>
                        </div>
                        <form method="POST" action="{{ route('sla-profiles.destroy', $profile) }}"
                              onsubmit="return confirm('Deactivate {{ addslashes($profile->name) }}? Clients already assigned to it will keep it until reassigned.')">
                            @csrf
                            @method('DELETE')
                            <x-onyx.button type="submit" variant="outline" size="sm"
                                           style="color: var(--critical); border-color: var(--critical);">
                                Deactivate
                            </x-onyx.button>
                        </form>
                    </div>
                </x-onyx.card>
            </div>
        @endif
    </div>

</x-layouts.app>
