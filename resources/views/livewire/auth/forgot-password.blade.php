<x-layouts::auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <div class="form-control w-full">
                <label class="label" for="email">
                    <span class="label-text">{{ __('Email Address') }}</span>
                </label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    class="input input-bordered w-full @error('email') input-error @enderror"
                    required
                    autofocus
                    placeholder="email@example.com"
                />
                @error('email')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <button type="submit" class="btn btn-primary w-full" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/70">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" wire:navigate class="link link-primary">{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
