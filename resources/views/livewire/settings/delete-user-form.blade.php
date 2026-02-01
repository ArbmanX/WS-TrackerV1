<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold text-base-content">{{ __('Delete account') }}</h3>
        <p class="text-sm text-base-content/70">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <button
        type="button"
        class="btn btn-error"
        onclick="delete_account_modal.showModal()"
    >
        {{ __('Delete account') }}
    </button>

    <dialog id="delete_account_modal" class="modal">
        <div class="modal-box">
            <form method="POST" wire:submit="deleteUser" class="space-y-6">
                <div>
                    <h3 class="text-lg font-bold">{{ __('Are you sure you want to delete your account?') }}</h3>
                    <p class="py-4 text-base-content/70">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>
                </div>

                <div class="form-control w-full">
                    <label class="label" for="delete_password">
                        <span class="label-text">{{ __('Password') }}</span>
                    </label>
                    <input
                        wire:model="password"
                        id="delete_password"
                        type="password"
                        class="input input-bordered w-full @error('password') input-error @enderror"
                    />
                    @error('password')
                        <label class="label">
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        </label>
                    @enderror
                </div>

                <div class="modal-action">
                    <button type="button" class="btn" onclick="delete_account_modal.close()">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-error">{{ __('Delete account') }}</button>
                </div>
            </form>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button>close</button>
        </form>
    </dialog>
</section>
