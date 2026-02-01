<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Password Settings') }}</h2>

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <div class="form-control w-full">
                <label class="label" for="current_password">
                    <span class="label-text">{{ __('Current password') }}</span>
                </label>
                <input
                    wire:model="current_password"
                    id="current_password"
                    type="password"
                    class="input input-bordered w-full @error('current_password') input-error @enderror"
                    required
                    autocomplete="current-password"
                />
                @error('current_password')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

            <div class="form-control w-full">
                <label class="label" for="password">
                    <span class="label-text">{{ __('New password') }}</span>
                </label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    class="input input-bordered w-full @error('password') input-error @enderror"
                    required
                    autocomplete="new-password"
                />
                @error('password')
                    <label class="label">
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    </label>
                @enderror
            </div>

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
                />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>

                <x-action-message class="me-3" on="password-updated">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>
</section>
