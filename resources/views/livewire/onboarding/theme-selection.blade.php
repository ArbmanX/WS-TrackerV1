<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Choose Your Theme')"
        :description="__('Select a visual theme for the application. You can change this anytime in settings.')"
    />

    <x-ui.theme-picker :selected="$selectedTheme" />

    <div class="flex gap-3">
        <button type="button" wire:click="goBack" class="btn btn-ghost flex-1">
            {{ __('Back') }}
        </button>
        <button type="button" wire:click="continueToNext" class="btn btn-primary flex-1" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="continueToNext">{{ __('Continue') }}</span>
            <span wire:loading wire:target="continueToNext" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <x-onboarding.progress :currentStep="2" />
</div>

@script
<script>
    // Bridge Livewire's set-theme event to Alpine's theme store for live preview
    $wire.on('set-theme', ({ theme }) => {
        if (Alpine.store('theme')) {
            Alpine.store('theme').set(theme);
        }
    });
</script>
@endscript
