<x-layouts.app title="Preview — {{ $slot->label() }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('settings.edit') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Settings</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('email-templates.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Email Templates</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">Preview</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('email-templates.edit', $slot->value) }}" variant="outline" size="sm">Edit</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">{{ $slot->label() }}</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">Rendered with representative sample data — no email is sent.</p>
    </div>

    <x-onyx.card variant="default" padding="xl" style="max-width: 640px;">
        {{-- $rendered values are already fully escaped/sanitised by EmailTemplateRenderer
             (variable values via e(), literal text via strip_tags() at save time) — rendered
             raw here to match exactly what the real email will show, avoiding double-escaping. --}}
        <div style="border-bottom: 1px solid var(--border-subtle); padding-bottom: var(--space-4); margin-bottom: var(--space-4);">
            <span style="font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Subject</span>
            <p style="font-size: var(--fs-16); font-weight: var(--weight-semibold); color: var(--text-primary); margin-top: var(--space-1);">{!! $rendered['subject'] !!}</p>
        </div>
        <div>
            <span style="font-size: var(--fs-12); color: var(--text-secondary); text-transform: uppercase; letter-spacing: var(--tracking-wide);">Body</span>
            <p style="font-size: var(--fs-14); color: var(--text-primary); white-space: pre-wrap; margin-top: var(--space-1);">{!! nl2br($rendered['body']) !!}</p>
        </div>
    </x-onyx.card>

</x-layouts.app>
