<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Connect to WorkStudio')"
        :description="__('Enter your WorkStudio username to link your account. This is required to access assessment data and other WorkStudio features.')"
    />

    @if ($errorMessage)
        <div class="alert alert-error">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ $errorMessage }}</span>
        </div>
    @endif

    @if ($userDetails)
        <div class="alert alert-success">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <p class="font-medium">{{ __('Account verified!') }}</p>
                <p class="text-sm">{{ $userDetails['full_name'] }} ({{ $userDetails['domain'] }})</p>
            </div>
        </div>
    @endif

    <form wire:submit="validateWorkStudio" class="flex flex-col gap-6">
        <!-- WorkStudio Username -->
        <div class="form-control w-full">
            <label class="label" for="ws_username">
                <span class="label-text">{{ __('WorkStudio Username') }}</span>
            </label>
            <input
                wire:model="ws_username"
                id="ws_username"
                type="text"
                class="input input-bordered w-full @error('ws_username') input-error @enderror"
                required
                autofocus
                placeholder="DOMAIN\username"
                @if($isValidating) disabled @endif
            />
            <label class="label">
                <span class="label-text-alt text-base-content/60">{{ __('Example: ASPLUNDH\\jsmith') }}</span>
            </label>
            @error('ws_username')
                <label class="label">
                    <span class="label-text-alt text-error">{{ $message }}</span>
                </label>
            @enderror
        </div>

        <!-- Info Box -->
        <div class="bg-base-200 rounded-lg p-4">
            <div class="flex gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-info shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="text-sm text-base-content/70">
                    <p class="font-medium mb-1">{{ __('Why is this needed?') }}</p>
                    <p>{{ __('Your WorkStudio account determines which regions and assessments you can access. This validation ensures you have the correct permissions.') }}</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" @if($isValidating) disabled @endif>
            @if($isValidating)
                <span class="loading loading-spinner loading-sm"></span>
                {{ __('Validating...') }}
            @else
                {{ __('Validate & Complete Setup') }}
            @endif
        </button>
    </form>

    <div class="text-center text-sm text-base-content/60">
        <p>{{ __('Step 2 of 2') }}</p>
    </div>
</div>
