<x-layouts.app title="Edit — {{ $slot->label() }}">

    <x-slot:breadcrumbs>
        <a href="{{ route('settings.edit') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Settings</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <a href="{{ route('email-templates.index') }}" style="font-size: var(--fs-14); color: var(--text-secondary); text-decoration: none;">Email Templates</a>
        <span style="font-size: var(--fs-14); color: var(--text-tertiary); margin: 0 var(--space-2);">/</span>
        <span style="font-size: var(--fs-14); font-weight: var(--weight-medium); color: var(--text-primary);">{{ $slot->label() }}</span>
    </x-slot:breadcrumbs>

    <x-slot:headerActions>
        <x-onyx.button href="{{ route('email-templates.preview', $slot->value) }}" variant="outline" size="sm">Preview</x-onyx.button>
    </x-slot:headerActions>

    <div style="margin-bottom: var(--space-6);">
        <h1 style="font-size: var(--fs-24); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-1);">{{ $slot->label() }}</h1>
        <p style="font-size: var(--fs-14); color: var(--text-secondary);">
            @if ($template)
                Customised — a PM has edited this template.
            @else
                Using the built-in default shown below.
            @endif
        </p>
    </div>

    @if (session('missingRequiredWarning'))
        <div style="margin-bottom: var(--space-5);">
            <x-onyx.alert tone="caution">
                This template is missing a required variable:
                @foreach (session('missingRequiredWarning') as $missing)
                    <code>{{ '{{'.$missing.'}}' }}</code>@if (!$loop->last), @endif
                @endforeach
                — without it, the email may not work as expected. Tick the box below to save anyway.
            </x-onyx.alert>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 1fr 320px; gap: var(--space-6); align-items: start;">

        <x-onyx.card variant="default" padding="xl">
            <form method="POST" action="{{ route('email-templates.update', $slot->value) }}" novalidate>
                @csrf
                @method('PATCH')

                <div style="display: flex; flex-direction: column; gap: var(--space-5);">

                    <x-onyx.input
                        name="subject"
                        label="Subject"
                        type="text"
                        maxlength="200"
                        :value="old('subject', $template->subject ?? $slot->defaultSubject())"
                        :error="$errors->first('subject')"
                        required
                    />

                    <x-onyx.textarea
                        name="body"
                        label="Body"
                        :error="$errors->first('body')"
                        rows="10"
                        helper="Plain text only — no HTML. Use the variables listed on the right."
                    >{{ old('body', $template->body ?? $slot->defaultBody()) }}</x-onyx.textarea>

                    @if (session('missingRequiredWarning'))
                        <label style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--fs-14); color: var(--text-primary); cursor: pointer;">
                            <input type="checkbox" name="confirm_missing_required" value="1" style="width: 16px; height: 16px; cursor: pointer;">
                            Save anyway, without the required variable
                        </label>
                    @endif

                </div>

                <div style="display: flex; justify-content: flex-end; gap: var(--space-3); margin-top: var(--space-7);">
                    <x-onyx.button href="{{ route('email-templates.index') }}" variant="ghost">Cancel</x-onyx.button>
                    <x-onyx.button type="submit" variant="accent">Save template</x-onyx.button>
                </div>
            </form>
        </x-onyx.card>

        <x-onyx.card variant="default" padding="md">
            <h2 style="font-size: var(--fs-14); font-weight: var(--weight-semibold); color: var(--text-primary); margin-bottom: var(--space-3);">Available variables</h2>
            <div style="display: flex; flex-direction: column; gap: var(--space-3);">
                @foreach ($slot->allowedVariables() as $key => $description)
                    <div>
                        <code style="font-size: var(--fs-13); color: var(--bronze-600);">{{ '{{'.$key.'}}' }}</code>
                        @if (in_array($key, $slot->requiredVariables(), true))
                            <x-onyx.badge tone="caution" variant="soft" style="margin-left: var(--space-2);">Required</x-onyx.badge>
                        @endif
                        <p style="font-size: var(--fs-13); color: var(--text-secondary); margin-top: var(--space-1);">{{ $description }}</p>
                    </div>
                @endforeach
            </div>
        </x-onyx.card>

    </div>

</x-layouts.app>
