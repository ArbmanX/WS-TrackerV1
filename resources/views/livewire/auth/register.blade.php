<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <div class="form-control w-full">
                <label class="label" for="name">
                    <span class="label-text">{{ __('Name') }}</span>
                </label>
                <input
                    id="name"
                    name="name"
                    type="text"
                    value="{{ old('name') }}"
                    class="input input-bordered w-full @error('name') input-error @enderror"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="{{ __('Full name') }}"
                />
                @error('name')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <!-- Email Address -->
            <div class="form-control w-full">
                <label class="label" for="email">
                    <span class="label-text">{{ __('Email address') }}</span>
                </label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    class="input input-bordered w-full @error('email') input-error @enderror"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                @error('email')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <!-- Password -->
            <div class="form-control w-full">
                <label class="label" for="password">
                    <span class="label-text">{{ __('Password') }}</span>
                </label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    class="input input-bordered w-full @error('password') input-error @enderror"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Password') }}"
                />
                @error('password')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <!-- Confirm Password -->
            <div class="form-control w-full">
                <label class="label" for="password_confirmation">
                    <span class="label-text">{{ __('Confirm password') }}</span>
                </label>
                <input
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    class="input input-bordered w-full"
                    required
                    autocomplete="new-password"
                    placeholder="{{ __('Confirm password') }}"
                />
            </div>

            <button type="submit" class="btn btn-primary w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="link link-primary">{{ __('Log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
