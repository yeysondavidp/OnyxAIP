<x-layouts.guest title="Choose a New Password">

    <h1 style="font-size: var(--fs-20); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-2);">
        Choose a new password
    </h1>
    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-7);">
        Passwords must be at least 12 characters.
    </p>

    @if ($errors->isNotEmpty())
        <div style="margin-bottom: var(--space-5);">
            <x-onyx.alert tone="critical">
                {{ $errors->first() }}
            </x-onyx.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" style="display: flex; flex-direction: column; gap: var(--space-5);">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-onyx.input
            name="email"
            type="email"
            label="Email address"
            autocomplete="email"
            :value="old('email', $request->email)"
            :error="$errors->first('email')"
        />

        <x-onyx.input
            name="password"
            type="password"
            label="New password"
            autocomplete="new-password"
            :error="$errors->first('password')"
        />

        <x-onyx.input
            name="password_confirmation"
            type="password"
            label="Confirm new password"
            autocomplete="new-password"
            :error="$errors->first('password_confirmation')"
        />

        <x-onyx.button type="submit" variant="accent" :fullWidth="true" size="lg">
            Reset password
        </x-onyx.button>
    </form>

</x-layouts.guest>
