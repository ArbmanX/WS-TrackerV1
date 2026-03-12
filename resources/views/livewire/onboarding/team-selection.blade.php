<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Set Up Your Teams')"
        :description="__('Create your teams. You can rename them or add more. Team management can be updated later.')"
    />

    <div class="space-y-3">
        @foreach ($teamNames as $index => $name)
            <fieldset class="fieldset w-full">
                <legend class="fieldset-legend">{{ __('Team') }} {{ $index + 1 }}</legend>
                <div class="flex gap-2">
                    <input
                        type="text"
                        wire:model="teamNames.{{ $index }}"
                        class="input w-full @error("teamNames.{$index}") input-error @enderror"
                        placeholder="{{ __('Team name') }}"
                    />
                    @if (count($teamNames) > 1)
                        <button
                            type="button"
                            wire:click="removeTeam({{ $index }})"
                            class="btn btn-ghost btn-square"
                            title="{{ __('Remove team') }}"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @endif
                </div>
                @error("teamNames.{$index}")
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>
        @endforeach
    </div>

    <button type="button" wire:click="addTeam" class="btn btn-ghost btn-sm self-start">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        {{ __('Add Team') }}
    </button>

    <div class="flex gap-3">
        <button type="button" wire:click="goBack" class="btn btn-ghost flex-1">
            {{ __('Back') }}
        </button>
        <button type="button" wire:click="continueToNext" class="btn btn-primary flex-1" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="continueToNext">{{ __('Continue') }}</span>
            <span wire:loading wire:target="continueToNext" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <x-onboarding.progress :currentStep="4" />
</div>
