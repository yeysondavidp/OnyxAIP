<x-layouts.guest title="Reset Password">

    <h1 style="font-size: var(--fs-20); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-2);">
        Forgot your password?
    </h1>
    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-7);">
        Enter your email address and we'll send you a reset link if an account exists.
    </p>

    @if (session('status'))
        <div style="margin-bottom: var(--space-5);">
            <x-onyx.alert tone="success">
                {{ session('status') }}
            </x-onyx.alert>
        </div>
    @endif

    @if ($errors->isNotEmpty())
        <div style="margin-bottom: var(--space-5);">
            <x-onyx.alert tone="critical">
                {{ $errors->first() }}
            </x-onyx.alert>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" style="display: flex; flex-direction: column; gap: var(--space-5);">
        @csrf

        <x-onyx.input
            name="email"
            type="email"
            label="Email address"
            autocomplete="email"
            autofocus
            :value="old('email')"
            :error="$errors->first('email')"
        />

        <x-onyx.button type="submit" variant="accent" :fullWidth="true" size="lg">
            Send reset link
        </x-onyx.button>
    </form>

    <p style="margin-top: var(--space-6); font-size: var(--fs-14); color: var(--text-secondary); text-align: center;">
        <a href="{{ route('login') }}" style="color: var(--bronze-600); text-decoration: none;">Back to sign in</a>
    </p>

</x-layouts.guest>
