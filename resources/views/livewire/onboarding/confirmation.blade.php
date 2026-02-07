<div class="flex flex-col gap-6">
    <x-auth-header
        :title="__('Confirm Your Setup')"
        :description="__('Review your account details below. If everything looks correct, click Confirm to complete setup.')"
    />

    <!-- Summary Card -->
    <div class="bg-base-100 border border-base-300 rounded-lg divide-y divide-base-300">
        <!-- Account Info -->
        <div class="p-4 space-y-2">
            <h3 class="text-sm font-semibold text-base-content/50 uppercase tracking-wide">{{ __('Account') }}</h3>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Name') }}</span>
                <span class="text-sm font-medium">{{ $summary['name'] }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Email') }}</span>
                <span class="text-sm font-medium">{{ $summary['email'] }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Theme') }}</span>
                <span class="text-sm font-medium">{{ $summary['theme'] }}</span>
            </div>
        </div>

        <!-- WorkStudio Info -->
        <div class="p-4 space-y-2">
            <h3 class="text-sm font-semibold text-base-content/50 uppercase tracking-wide">{{ __('WorkStudio') }}</h3>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Username') }}</span>
                <span class="text-sm font-medium">{{ $summary['ws_username'] }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Full Name') }}</span>
                <span class="text-sm font-medium">{{ $summary['ws_full_name'] }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-base-content/70">{{ __('Domain') }}</span>
                <span class="text-sm font-medium">{{ $summary['ws_domain'] }}</span>
            </div>
            @if(count($summary['regions']) > 0)
                <div class="flex justify-between items-start">
                    <span class="text-sm text-base-content/70">{{ __('Regions') }}</span>
                    <div class="flex flex-wrap gap-1 justify-end">
                        @foreach($summary['regions'] as $region)
                            <span class="badge badge-sm badge-outline">{{ $region }}</span>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="flex gap-3">
        <button type="button" wire:click="goBack" class="btn btn-ghost flex-1">
            {{ __('Back') }}
        </button>
        <button type="button" wire:click="confirm" class="btn btn-primary flex-1" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="confirm">{{ __('Confirm & Start') }}</span>
            <span wire:loading wire:target="confirm" class="loading loading-spinner loading-sm"></span>
        </button>
    </div>

    <x-onboarding.progress :currentStep="4" />
</div>
