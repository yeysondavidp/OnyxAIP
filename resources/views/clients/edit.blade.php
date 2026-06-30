<x-layouts.app title="Edit — {{ $client->client_name }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('clients.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Clients</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('clients.show', $client) }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">{{ $client->client_name }}</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Edit</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Edit Client</h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">{{ $client->client_name }} — {{ $client->client_code }}</p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('clients.update', $client) }}" novalidate>
                @csrf
                @method('PATCH')

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="client_name"
                        label="Client name"
                        type="text"
                        :value="old('client_name', $client->client_name)"
                        :error="$errors->first('client_name')"
                        required
                        autocomplete="off"
                    />

                    <x-onyx.input
                        name="client_code"
                        label="Client code"
                        type="text"
                        :value="old('client_code', $client->client_code)"
                        :error="$errors->first('client_code')"
                        helper="Short uppercase identifier, e.g. PAN, SEP, DIO."
                        required
                        maxlength="10"
                        style="text-transform: uppercase;"
                    />

                    <x-onyx.divider />

                    <x-onyx.input
                        name="primary_contact"
                        label="Primary contact name"
                        type="text"
                        :value="old('primary_contact', $client->primary_contact)"
                        :error="$errors->first('primary_contact')"
                    />

                    <x-onyx.input
                        name="primary_email"
                        label="Primary contact email"
                        type="email"
                        :value="old('primary_email', $client->primary_email)"
                        :error="$errors->first('primary_email')"
                    />

                    <x-onyx.divider />

                    <x-onyx.textarea
                        name="notes"
                        label="Internal notes"
                        :error="$errors->first('notes')"
                        rows="4"
                        helper="Internal ONYX notes — not visible to the client."
                    >{{ old('notes', $client->notes) }}</x-onyx.textarea>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('clients.show', $client) }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save changes</x-onyx.button>
                </div>

            </form>
        </x-onyx.card>

        {{-- Deactivate section --}}
        @if ($client->is_active)
            <div style="margin-top: var(--space-6);">
                <x-onyx.card variant="outline" padding="md">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4);">
                        <div>
                            <p style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Deactivate client</p>
                            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                                The client record and all its history are preserved. Deactivated clients are hidden from active lists.
                            </p>
                        </div>
                        <form method="POST" action="{{ route('clients.destroy', $client) }}"
                              onsubmit="return confirm('Deactivate {{ addslashes($client->client_name) }}? The record will be preserved but hidden from active lists.')">
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
