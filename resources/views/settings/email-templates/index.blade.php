<x-layouts.app title="Email Templates">

    <x-slot:breadcrumbs>
        <a href="{{ route('settings.edit') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Settings</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Email Templates</span>
    </x-slot:breadcrumbs>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">Email Templates</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">Customise the subject and body of every system email. Unedited slots use a safe built-in default.</p>
    </div>

    <div style="overflow-x: auto; border: 1px solid var(--border-subtle); border-radius: var(--radius-lg);">
        <table style="width: 100%; border-collapse: collapse; font-size: var(--fs-14);">
            <thead>
                <tr style="background: var(--surface-sunken); border-bottom: 1px solid var(--border-subtle);">
                    <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Template</th>
                    <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Status</th>
                    <th style="padding: var(--space-3) var(--space-4); text-align: left; font-weight: var(--weight-semibold); color: var(--text-secondary);">Last edited</th>
                    <th style="padding: var(--space-3) var(--space-4); text-align: right; font-weight: var(--weight-semibold); color: var(--text-secondary);">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr style="border-bottom: 1px solid var(--border-subtle);">
                        <td style="padding: var(--space-3) var(--space-4); font-weight: var(--weight-medium); color: var(--text-primary);">
                            {{ $row['slot']->label() }}
                        </td>
                        <td style="padding: var(--space-3) var(--space-4);">
                            <x-onyx.badge :tone="$row['template'] ? 'accent' : 'neutral'" variant="soft">
                                {{ $row['template'] ? 'Customised' : 'Using default' }}
                            </x-onyx.badge>
                        </td>
                        <td style="padding: var(--space-3) var(--space-4); color: var(--text-secondary);">
                            {{ $row['template']?->updated_at?->format('d/m/Y g:ia') ?? '—' }}
                        </td>
                        <td style="padding: var(--space-3) var(--space-4); text-align: right; white-space: nowrap;">
                            <x-onyx.button href="{{ route('email-templates.preview', $row['slot']->value) }}" variant="ghost" size="sm">Preview</x-onyx.button>
                            <x-onyx.button href="{{ route('email-templates.edit', $row['slot']->value) }}" variant="ghost" size="sm">Edit</x-onyx.button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</x-layouts.app>
