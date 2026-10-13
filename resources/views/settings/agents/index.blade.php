<x-layouts.app title="Agent Access">

    <x-slot:breadcrumbs>
        <a href="{{ route('settings.edit') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Settings</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Agent Access</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('agents.create') }}" variant="accent" size="sm">Create agent</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Agent Access</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary); max-width: 640px;">
            Agents are AI assistants that work in the platform with their own access key. They follow the same rules as the forms,
            every change they make is recorded under their name, and you can switch a key off at any time.
        </p>
    </div>

    @if (session('new_agent_token'))
        @php $new = session('new_agent_token'); @endphp
        <div style="margin-bottom: var(--space-6);" x-data="{ copied: false }">
            <x-onyx.alert tone="caution" title="Copy this key now — it will not be shown again">
                <p style="margin-bottom: var(--space-3);">New access key for <strong>{{ $new['agent'] }}</strong>. Store it in the agent's configuration. If it is lost, revoke it and issue a new one.</p>
                <div style="display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;">
                    <input type="text" readonly value="{{ $new['token'] }}" x-ref="token" aria-label="New access key"
                        style="flex: 1; min-width: 240px; min-height: 44px; padding: 0 var(--space-3); font-family: monospace; font-size: var(--fs-13); border: 1px solid var(--border-default); border-radius: var(--radius-md); background: var(--surface-default); color: var(--text-primary);"
                        @focus="$el.select()">
                    <x-onyx.button type="button" variant="outline" size="sm"
                        x-on:click="navigator.clipboard.writeText($refs.token.value).then(() => copied = true)">
                        <span x-text="copied ? 'Copied' : 'Copy key'">Copy key</span>
                    </x-onyx.button>
                </div>
            </x-onyx.alert>
        </div>
    @endif

    @if ($agents->isEmpty())
        <x-onyx.card variant="default" padding="xl">
            <x-onyx.empty icon="shield" heading="No agents yet" body="Create an agent to let an AI assistant read or update the register without server access.">
                <x-onyx.button href="{{ route('agents.create') }}" variant="accent" size="sm">Create agent</x-onyx.button>
            </x-onyx.empty>
        </x-onyx.card>
    @else
        <div style="display: flex; flex-direction: column; gap: var(--space-5);">
            @foreach ($agents as $agent)
                <x-onyx.card variant="default" padding="none">
                    <div style="padding: var(--space-5) var(--space-6); border-bottom: 1px solid var(--border-subtle); display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); flex-wrap: wrap;">
                        <div>
                            <h2 style="font-size: var(--fs-15); font-weight: var(--weight-semibold); color: var(--text-primary);">{{ $agent->name }}</h2>
                            <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">
                                {{ $agent->client_id ? 'Only '.($clientNames[$agent->client_id] ?? 'one client') : 'All clients' }}
                            </p>
                        </div>
                        <x-onyx.button href="{{ route('agents.tokens.create', $agent) }}" variant="outline" size="sm">Issue new key</x-onyx.button>
                    </div>

                    @if ($agent->tokens->isEmpty())
                        <p style="padding: var(--space-5) var(--space-6); font-size: var(--fs-13); color: var(--text-tertiary);">No active keys. This agent cannot connect until you issue one.</p>
                    @else
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
                                <thead>
                                    <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Allowed to</th>
                                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Expires</th>
                                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Issued by</th>
                                        <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Last used</th>
                                        <th style="padding: var(--space-3) var(--space-4); text-align: right; font-weight: var(--weight-semibold); color: var(--text-secondary);"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($agent->tokens as $token)
                                        @php $expired = $token->expires_at?->isPast(); @endphp
                                        <tr style="border-bottom: 1px solid var(--border-subtle);">
                                            <td style="padding: var(--space-3) var(--space-4);">
                                                <div style="display: flex; gap: var(--space-1); flex-wrap: wrap;">
                                                    @foreach ($token->abilities ?? [] as $ability)
                                                        <x-onyx.badge tone="neutral" variant="soft">{{ \App\Enums\AgentAbility::tryFrom($ability)?->label() ?? $ability }}</x-onyx.badge>
                                                    @endforeach
                                                </div>
                                            </td>
                                            <td style="padding: var(--space-3) var(--space-4); white-space: nowrap;">
                                                @if ($expired)
                                                    <x-onyx.badge tone="critical" variant="soft">Expired</x-onyx.badge>
                                                @else
                                                    <span style="color: var(--text-primary);">{{ $token->expires_at?->format('d/m/Y') }}</span>
                                                @endif
                                            </td>
                                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">{{ $token->createdBy?->name ?? '—' }}</td>
                                            <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary); white-space: nowrap;">{{ $token->last_used_at?->format('d/m/Y g:ia') ?? 'Never' }}</td>
                                            <td style="padding: var(--space-3) var(--space-4); text-align: right;">
                                                <div x-data="{}" @onyx-dialog-confirm="$refs.revokeToken{{ $token->id }}.submit()">
                                                    <x-onyx.dialog title="Revoke this key?" confirmLabel="Revoke" confirmTone="critical">
                                                        <x-slot:trigger>
                                                            <span style="display: inline-flex; align-items: center; min-height: 44px; font-size: var(--fs-13); color: var(--critical); cursor: pointer;">Revoke</span>
                                                        </x-slot:trigger>
                                                        {{ $agent->name }} will stop working with this key immediately. Anything already saved stays as it is.
                                                    </x-onyx.dialog>
                                                    <form method="POST" action="{{ route('agents.tokens.destroy', [$agent, $token]) }}" x-ref="revokeToken{{ $token->id }}" style="display: none;">
                                                        @csrf @method('DELETE')
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-onyx.card>
            @endforeach
        </div>
    @endif

</x-layouts.app>
