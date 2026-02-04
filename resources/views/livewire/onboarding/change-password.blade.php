<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Set Your Password')"
        :description="__('Please create a new password for your account. This will replace the temporary password provided by your administrator.')"
    />

    <form wire:submit="setPassword" class="flex flex-col gap-6">
        <!-- New Password -->
        <div class="form-control w-full">
            <label class="label" for="password">
                <span class="label-text">{{ __('New Password') }}</span>
            </label>
            <input
                wire:model="password"
                id="password"
                type="password"
                class="input input-bordered w-full @error('password') input-error @enderror"
                required
                autofocus
                autocomplete="new-password"
                placeholder="{{ __('Enter your new password') }}"
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
                <span class="label-text">{{ __('Confirm Password') }}</span>
            </label>
            <input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                class="input input-bordered w-full"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm your new password') }}"
            />
        </div>

        <!-- Password Requirements -->
        <div class="text-sm text-base-content/70">
            <p class="font-medium mb-2">{{ __('Password requirements:') }}</p>
            <ul class="list-disc list-inside space-y-1">
                <li>{{ __('At least 8 characters') }}</li>
                <li>{{ __('Mix of letters, numbers, and symbols recommended') }}</li>
            </ul>
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled">
            <span wire:loading.remove>{{ __('Set Password & Continue') }}</span>
            <span wire:loading class="loading loading-spinner loading-sm"></span>
        </button>
    </form>

    <div class="text-center text-sm text-base-content/60">
        <p>{{ __('Step 1 of 2') }}</p>
    </div>
</div>
