<x-layouts.app :title="$agent ? 'New key — '.$agent->name : 'Create agent'">

    <x-slot:breadcrumbs>
        <a href="{{ route('settings.edit') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Settings</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('agents.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Agent Access</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $agent ? 'New key' : 'Create agent' }}</span>
    </x-slot:breadcrumbs>

    <div style="max-width: 640px;">
        <div style="margin-bottom: var(--space-6);">
            <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">
                {{ $agent ? 'New key for '.$agent->name : 'Create agent' }}
            </h1>
            <p style="font-size: var(--fs-14); color: var(--text-secondary);">
                Only tick what the agent needs. It can never delete anything, validate or cancel jobs, see passwords or change settings.
            </p>
        </div>

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ $agent ? route('agents.tokens.store', $agent) : route('agents.store') }}" novalidate>
                @csrf

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    @unless ($agent)
                        <x-onyx.input
                            name="name"
                            label="Agent name"
                            maxlength="100"
                            :value="old('name')"
                            :error="$errors->first('name')"
                            helper="Shown in the audit log next to every change it makes, e.g. &quot;Claude (agent)&quot;."
                            required
                        />

                        <x-onyx.select
                            name="client_id"
                            label="Clients it can see"
                            :error="$errors->first('client_id')"
                            helper="Limit the agent to one client if it only works on that account."
                        >
                            <option value="">All clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) old('client_id') === (string) $client->id)>Only {{ $client->client_name }}</option>
                            @endforeach
                        </x-onyx.select>
                    @endunless

                    <fieldset class="onyx-field" style="border: 0; padding: 0; margin: 0;">
                        <legend class="onyx-field__label">Allowed to</legend>
                        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-top: var(--space-2);">
                            @foreach ($abilities as $ability)
                                <label style="display: flex; align-items: flex-start; gap: var(--space-3); min-height: 44px; cursor: pointer;">
                                    <input type="checkbox" name="abilities[]" value="{{ $ability->value }}"
                                        @checked(in_array($ability->value, old('abilities', []), true))
                                        style="width: 18px; height: 18px; margin-top: 2px; cursor: pointer;">
                                    <span>
                                        <span style="display: block; font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $ability->label() }}</span>
                                        <span style="display: block; font-size: var(--fs-13); color: var(--text-secondary);">{{ $ability->description() }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('abilities')
                            <span class="onyx-field__error" role="alert" style="font-size: var(--fs-13); color: var(--critical);">{{ $message }}</span>
                        @enderror
                        @error('abilities.*')
                            <span class="onyx-field__error" role="alert" style="font-size: var(--fs-13); color: var(--critical);">{{ $message }}</span>
                        @enderror
                    </fieldset>

                    <x-onyx.select
                        name="expires_in_days"
                        label="Key expires after"
                        :error="$errors->first('expires_in_days')"
                        helper="After this the key stops working and you issue a new one."
                        required
                    >
                        @foreach ($expiryOptions as $days)
                            <option value="{{ $days }}" @selected((int) old('expires_in_days', 30) === $days)>{{ $days }} days</option>
                        @endforeach
                    </x-onyx.select>

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-8);">
                    <x-onyx.button href="{{ route('agents.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">{{ $agent ? 'Issue key' : 'Create agent and key' }}</x-onyx.button>
                </div>
            </form>
        </x-onyx.card>
    </div>

</x-layouts.app>
