<x-layouts.app title="Add Client">

    <x-slot:breadcrumbs>
        <a href="{{ route('clients.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Clients</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Add client</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Add Client</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">Create a new brand account. All stores and assets belong to a client.</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('clients.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="client_name"
                        label="Client name"
                        type="text"
                        :value="old('client_name')"
                        :error="$errors->first('client_name')"
                        required
                        autocomplete="off"
                    />

                    <x-onyx.input
                        name="client_code"
                        label="Client code"
                        type="text"
                        :value="old('client_code')"
                        :error="$errors->first('client_code')"
                        helper="Short uppercase identifier, e.g. PAN, SEP, DIO. Used across all records for this client."
                        required
                        maxlength="10"
                        style="text-transform: uppercase;"
                    />

                    <x-onyx.divider />

                    <x-onyx.input
                        name="primary_contact"
                        label="Primary contact name"
                        type="text"
                        :value="old('primary_contact')"
                        :error="$errors->first('primary_contact')"
                        helper="Optional — client-side contact name."
                    />

                    <x-onyx.input
                        name="primary_email"
                        label="Primary contact email"
                        type="email"
                        :value="old('primary_email')"
                        :error="$errors->first('primary_email')"
                    />

                    <x-onyx.divider />

                    <x-onyx.textarea
                        name="notes"
                        label="Internal notes"
                        :error="$errors->first('notes')"
                        rows="4"
                        helper="Internal ONYX notes — not visible to the client."
                    >{{ old('notes') }}</x-onyx.textarea>

                    <x-onyx.divider />

                    <x-onyx.select
                        name="sla_profile_id"
                        label="SLA profile"
                        :error="$errors->first('sla_profile_id')"
                        helper="Optional — can be assigned later from the client's page."
                    >
                        <option value="">— No SLA profile —</option>
                        @foreach ($slaProfiles as $profile)
                            <option value="{{ $profile->id }}" @selected((string) old('sla_profile_id') === (string) $profile->id)>
                                {{ $profile->name }}
                            </option>
                        @endforeach
                    </x-onyx.select>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('clients.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save client</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>
