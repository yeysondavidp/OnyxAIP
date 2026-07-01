<x-layouts.app title="Add SLA Profile">

    <x-slot:breadcrumbs>
        <a href="{{ route('sla-profiles.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">SLA Profiles</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add profile</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add SLA Profile</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Define response and resolution targets. Assign it to a client from the client's edit page.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('sla-profiles.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="name"
                        label="Profile name"
                        type="text"
                        :value="old('name')"
                        :error="$errors->first('name')"
                        helper="e.g. &quot;Pandora Standard&quot;"
                        required
                        autocomplete="off"
                    />

                    <x-onyx.divider />

                    <x-onyx.input
                        name="acknowledgement_hours"
                        label="Acknowledgement window (business hours)"
                        type="number"
                        min="1"
                        :value="old('acknowledgement_hours', 2)"
                        :error="$errors->first('acknowledgement_hours')"
                        helper="e.g. 2 business hours"
                        required
                    />

                    <x-onyx.input
                        name="onsite_response_metro_hours"
                        label="On-site response — metro (business hours)"
                        type="number"
                        min="1"
                        :value="old('onsite_response_metro_hours', 10)"
                        :error="$errors->first('onsite_response_metro_hours')"
                        helper="e.g. next business day ≈ 10 business hours"
                        required
                    />

                    <x-onyx.input
                        name="onsite_response_regional_hours"
                        label="On-site response — regional (business hours)"
                        type="number"
                        min="1"
                        :value="old('onsite_response_regional_hours', 20)"
                        :error="$errors->first('onsite_response_regional_hours')"
                        helper="e.g. 1–2 business days ≈ 20 business hours"
                        required
                    />

                    <x-onyx.input
                        name="resolution_hours"
                        label="Resolution target (business hours)"
                        type="number"
                        min="1"
                        :value="old('resolution_hours', 40)"
                        :error="$errors->first('resolution_hours')"
                        helper="The overall deadline the SLA clock tracks — e.g. 5 business days ≈ 40 business hours"
                        required
                    />

                    <x-onyx.divider />

                    <x-onyx.select name="monitoring_coverage" label="Monitoring coverage" :error="$errors->first('monitoring_coverage')" required>
                        <option value="">— Select —</option>
                        @foreach ($coverages as $coverage)
                            <option value="{{ $coverage->value }}" @selected(old('monitoring_coverage') === $coverage->value)>
                                {{ $coverage->label() }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('sla-profiles.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save profile</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>
