<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">{{ __('Email') }}</legend>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ request('email') }}"
                    class="input w-full @error('email') input-error @enderror"
                    required
                    autocomplete="email"
                />
                @error('email')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <!-- Password -->
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">{{ __('Password') }}</legend>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="input w-full @error('password') input-error @enderror"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Password') }}"
                />
                @error('password')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <!-- Confirm Password -->
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">{{ __('Confirm password') }}</legend>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="input w-full"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Confirm password') }}"
                />
            </fieldset>

            <button type="submit" class="btn btn-primary w-full" data-test="reset-password-button">
                {{ __('Reset password') }}
            </button>
        </form>
    </div>
</x-layouts::auth>
