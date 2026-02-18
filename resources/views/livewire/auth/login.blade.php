<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">{{ __('Email address') }}</legend>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    class="input w-full @error('email') input-error @enderror"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                @error('email')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <!-- Password -->
            <fieldset class="fieldset w-full">
                <div class="flex items-center justify-between">
                    <legend class="fieldset-legend">{{ __('Password') }}</legend>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="link link-primary text-sm">
                            {{ __('Forgot your password?') }}
                        </a>
                    @endif
                </div>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="input w-full @error('password') input-error @enderror"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                />
                @error('password')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <!-- Remember Me -->
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="remember" class="checkbox checkbox-sm" {{ old('remember') ? 'checked' : '' }} />
                <span class="text-sm">{{ __('Remember me') }}</span>
            </label>

            <button type="submit" class="btn btn-primary w-full" data-test="login-button">
                {{ __('Log in') }}
            </button>
        </form>

        <div class="text-sm text-center text-base-content/60">
            <p>{{ __('Contact your administrator if you need an account.') }}</p>
        </div>
    </div>
</x-layouts::auth>
