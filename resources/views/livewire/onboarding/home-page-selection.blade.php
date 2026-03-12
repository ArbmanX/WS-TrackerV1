<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Choose Your Home Page')"
        :description="__('Select which page you\'d like to see when you log in. You can change this anytime in settings.')"
    />

    <div class="grid gap-3">
        @foreach ($this->availablePages as $route => $page)
            <label class="flex items-center gap-4 p-4 rounded-lg border cursor-pointer transition-colors
                {{ $selectedPage === $route ? 'border-primary bg-primary/5' : 'border-base-300 hover:border-base-content/20' }}">
                <input
                    type="radio"
                    wire:model="selectedPage"
                    value="{{ $route }}"
                    class="radio radio-primary"
                />
                <div class="flex-1">
                    <div class="font-medium text-sm">{{ __($page['name']) }}</div>
                    <div class="text-xs text-base-content/60">{{ __($page['description']) }}</div>
                </div>
            </label>
        @endforeach
    </div>

    <div class="flex gap-3">
        <button type="button" wire:click="goBack" class="btn btn-ghost flex-1">
            {{ __('Back') }}
        </button>
        <button type="button" wire:click="continueToNext" class="btn btn-primary flex-1" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="continueToNext">{{ __('Continue') }}</span>
            <span wire:loading wire:target="continueToNext" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <x-onboarding.progress :currentStep="5" />
</div>
