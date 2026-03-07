<div class="card bg-base-100 shadow-sm sticky top-4">
    <div class="card-body">
        <h2 class="card-title text-lg">
            <x-heroicon-o-document-text class="size-5" />
            Summary
        </h2>

        <div class="space-y-4 mt-3">
            {{-- Credentials --}}
            <div>
                <button wire:click="goToStep(1)" class="flex items-center gap-2 text-sm font-semibold hover:text-primary transition-colors">
                    <span class="badge badge-sm {{ $currentStep >= 1 ? 'badge-primary' : 'badge-ghost' }}">1</span>
                    WS Credentials
                </button>
                @if(count($selectedWsUserIds) > 0)
                    <div class="ml-7 mt-1 space-y-0.5">
                        @foreach($this->selectedWsUsers as $wsUser)
                            <p class="text-xs truncate">
                                {{ $wsUser->display_name ?? $wsUser->username }}
                                @if($wsUser->id === $primaryWsUserId)
                                    <span class="badge badge-primary badge-xs">P</span>
                                @endif
                            </p>
                        @endforeach
                    </div>
                @else
                    <p class="ml-7 mt-1 text-xs text-base-content/30">No credentials selected</p>
                @endif
            </div>

            <div class="divider my-0"></div>

            {{-- User Info --}}
            <div>
                <button wire:click="goToStep(2)" class="flex items-center gap-2 text-sm font-semibold hover:text-primary transition-colors">
                    <span class="badge badge-sm {{ $currentStep >= 2 ? 'badge-primary' : 'badge-ghost' }}">2</span>
                    User Info
                </button>
                @if($userName || $userEmail)
                    <div class="ml-7 mt-1">
                        @if($userName)
                            <p class="text-xs">{{ $userName }}</p>
                        @endif
                        @if($userEmail)
                            <p class="text-xs text-base-content/50">{{ $userEmail }}</p>
                        @endif
                    </div>
                @else
                    <p class="ml-7 mt-1 text-xs text-base-content/30">Not configured</p>
                @endif
            </div>

            <div class="divider my-0"></div>

            {{-- Role --}}
            <div>
                <button wire:click="goToStep(3)" class="flex items-center gap-2 text-sm font-semibold hover:text-primary transition-colors">
                    <span class="badge badge-sm {{ $currentStep >= 3 ? 'badge-primary' : 'badge-ghost' }}">3</span>
                    Role
                </button>
                @if($selectedRole)
                    <p class="ml-7 mt-1 text-xs font-medium">{{ ucwords(str_replace('-', ' ', $selectedRole)) }}</p>
                @else
                    <p class="ml-7 mt-1 text-xs text-base-content/30">Not selected</p>
                @endif
            </div>

            <div class="divider my-0"></div>

            {{-- Regions --}}
            <div>
                <button wire:click="goToStep(4)" class="flex items-center gap-2 text-sm font-semibold hover:text-primary transition-colors">
                    <span class="badge badge-sm {{ $currentStep >= 4 ? 'badge-primary' : 'badge-ghost' }}">4</span>
                    Regions & Assessments
                </button>
                <div class="ml-7 mt-1">
                    @if(count($selectedRegionIds) > 0)
                        <p class="text-xs">{{ count($selectedRegionIds) }} region(s)</p>
                    @else
                        <p class="text-xs text-base-content/30">No regions</p>
                    @endif

                    @if(count($selectedAssessmentIds) > 0)
                        <p class="text-xs">{{ count($selectedAssessmentIds) }} assessment(s)</p>
                    @else
                        <p class="text-xs text-base-content/30">No assessments</p>
                    @endif
                </div>
            </div>

            <div class="divider my-0"></div>

            {{-- Step 5 --}}
            <div>
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <span class="badge badge-sm {{ $currentStep >= 5 ? 'badge-primary' : 'badge-ghost' }}">5</span>
                    Review & Save
                </div>
            </div>
        </div>
    </div>
</div>
