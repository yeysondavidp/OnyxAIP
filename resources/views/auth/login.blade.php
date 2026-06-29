<x-layouts.guest title="Sign In">

    <h1 style="font-size: var(--fs-20); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-2);">
        Welcome back
    </h1>
    <p style="font-size: var(--fs-14); color: var(--text-secondary); margin-bottom: var(--space-7);">
        Sign in to ONYX Asset Intelligence Platform
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

    <form method="POST" action="{{ route('login.post') }}" style="display: flex; flex-direction: column; gap: var(--space-5);">
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

        <div>
            <x-onyx.input
                name="password"
                type="password"
                label="Password"
                autocomplete="current-password"
                :error="$errors->first('password')"
            />
            <div style="margin-top: var(--space-2); text-align: right;">
                <a href="{{ route('password.request') }}" style="font-size: var(--fs-13); color: var(--bronze-600); text-decoration: none;">
                    Forgot password?
                </a>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: var(--space-3);">
            <input
                type="checkbox"
                id="remember"
                name="remember"
                style="width: 16px; height: 16px; accent-color: var(--bronze-500); cursor: pointer; flex-shrink: 0;"
            >
            <label for="remember" style="font-size: var(--fs-14); color: var(--text-secondary); cursor: pointer; user-select: none;">
                Keep me signed in
            </label>
        </div>

        <x-onyx.button type="submit" variant="accent" :fullWidth="true" size="lg">
            Sign in
        </x-onyx.button>

    </form>

</x-layouts.guest>
