<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <h2 class="card-title text-lg">
            <x-heroicon-o-clipboard-document-check class="size-5" />
            Review & Save
        </h2>

        <p class="text-sm text-base-content/60 mt-1">
            Review all details before creating the user.
        </p>

        @error('save')
            <div class="alert alert-error mt-3">
                <x-heroicon-o-exclamation-circle class="size-5" />
                <span class="text-sm">{{ $message }}</span>
            </div>
        @enderror

        <div class="space-y-4 mt-4">
            {{-- User Info --}}
            <div class="p-4 rounded-lg border border-base-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-sm">User Information</h3>
                    <button wire:click="goToStep(2)" class="btn btn-ghost btn-xs">Edit</button>
                </div>
                <div class="mt-2 space-y-1">
                    <p class="text-sm"><span class="text-base-content/50">Name:</span> {{ $userName }}</p>
                    <p class="text-sm"><span class="text-base-content/50">Email:</span> {{ $userEmail }}</p>
                </div>
            </div>

            {{-- WS Credentials --}}
            <div class="p-4 rounded-lg border border-base-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-sm">
                        WS Credentials
                        <span class="badge badge-ghost badge-xs">{{ count($selectedWsUserIds) }}</span>
                    </h3>
                    <button wire:click="goToStep(1)" class="btn btn-ghost btn-xs">Edit</button>
                </div>
                @if(count($selectedWsUserIds) > 0)
                    <div class="mt-2 space-y-1">
                        @foreach($this->selectedWsUsers as $wsUser)
                            <p class="text-sm">
                                {{ $wsUser->display_name ?? $wsUser->username }}
                                <span class="text-xs text-base-content/40">({{ $wsUser->username }})</span>
                                @if($wsUser->id === $primaryWsUserId)
                                    <span class="badge badge-primary badge-xs">Primary</span>
                                @endif
                            </p>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-base-content/40 mt-2">No credentials selected</p>
                @endif
            </div>

            {{-- Role --}}
            <div class="p-4 rounded-lg border border-base-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-sm">Role</h3>
                    <button wire:click="goToStep(3)" class="btn btn-ghost btn-xs">Edit</button>
                </div>
                @if($this->selectedRoleDetails)
                    <p class="text-sm mt-2 font-medium">{{ ucwords(str_replace('-', ' ', $this->selectedRoleDetails['name'])) }}</p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($this->selectedRoleDetails['permissions'] as $permission)
                            <span class="badge badge-ghost badge-xs">{{ $permission }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Regions --}}
            <div class="p-4 rounded-lg border border-base-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-sm">
                        Regions
                        <span class="badge badge-ghost badge-xs">{{ count($selectedRegionIds) }}</span>
                    </h3>
                    <button wire:click="goToStep(4)" class="btn btn-ghost btn-xs">Edit</button>
                </div>
                @if(count($selectedRegionIds) > 0)
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($this->selectedRegions as $region)
                            <span class="badge badge-outline badge-sm">{{ $region->display_name }}</span>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-base-content/40 mt-2">No regions selected</p>
                @endif
            </div>

            {{-- Assessments --}}
            <div class="p-4 rounded-lg border border-base-200">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-sm">
                        Assessments
                        <span class="badge badge-ghost badge-xs">{{ count($selectedAssessmentIds) }}</span>
                    </h3>
                    <button wire:click="goToStep(4)" class="btn btn-ghost btn-xs">Edit</button>
                </div>
                @if(count($selectedAssessmentIds) > 0)
                    <div class="mt-2 max-h-32 overflow-y-auto space-y-1">
                        @foreach($this->selectedAssessments as $assessment)
                            <p class="text-sm truncate">
                                {{ $assessment->work_order }}{{ $assessment->extension !== '@' ? ' / ' . $assessment->extension : '' }}
                                <span class="text-xs text-base-content/40">{{ $assessment->circuit?->line_name ?? '' }}</span>
                            </p>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-base-content/40 mt-2">No assessments selected</p>
                @endif
            </div>
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between mt-6">
            <button wire:click="previousStep" class="btn btn-ghost">
                <x-heroicon-o-arrow-left class="size-4" />
                Back
            </button>

            <button
                wire:click="saveUser"
                class="btn btn-primary btn-wide"
                wire:loading.attr="disabled"
                wire:target="saveUser"
            >
                <span wire:loading.remove wire:target="saveUser" class="inline-flex items-center gap-2">
                    <x-heroicon-o-check class="size-5" />
                    Create User
                </span>
                <span wire:loading wire:target="saveUser" class="inline-flex items-center gap-2">
                    <span class="loading loading-spinner loading-sm"></span>
                    Creating...
                </span>
            </button>
        </div>
    </div>
</div>
