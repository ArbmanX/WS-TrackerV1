<div class="space-y-6">
    {{-- Regions --}}
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-lg">
                <x-heroicon-o-map class="size-5" />
                Assign Regions
                @if(count($selectedRegionIds) > 0)
                    <span class="badge badge-primary badge-sm">{{ count($selectedRegionIds) }}</span>
                @endif
            </h2>

            <div class="flex gap-2 mt-2">
                <button wire:click="selectAllRegions" class="btn btn-ghost btn-xs">Select All</button>
                <button wire:click="deselectAllRegions" class="btn btn-ghost btn-xs">Deselect All</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3">
                @foreach($this->availableRegions as $region)
                    <label class="flex items-center gap-2 p-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors {{ in_array($region->id, $selectedRegionIds) ? 'bg-primary/5' : '' }}">
                        <input
                            type="checkbox"
                            wire:click="toggleRegion({{ $region->id }})"
                            @checked(in_array($region->id, $selectedRegionIds))
                            class="checkbox checkbox-primary checkbox-sm"
                        />
                        <span class="text-sm">{{ $region->display_name }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Assessments --}}
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-lg">
                <x-heroicon-o-clipboard-document-list class="size-5" />
                Assign Assessments
                @if(count($selectedAssessmentIds) > 0)
                    <span class="badge badge-primary badge-sm">{{ count($selectedAssessmentIds) }}</span>
                @endif
            </h2>

            @if(!empty($detectedAssessmentIds))
                <div class="alert alert-info mt-2">
                    <x-heroicon-o-sparkles class="size-5" />
                    <span class="text-sm">{{ count($detectedAssessmentIds) }} assessments auto-detected from WS credentials. Review and adjust as needed.</span>
                </div>
            @endif

            {{-- Selected Assessments --}}
            @if(count($selectedAssessmentIds) > 0)
                <div class="mt-3 max-h-64 overflow-y-auto divide-y divide-base-200 border border-base-200 rounded-lg">
                    @foreach($this->selectedAssessments as $assessment)
                        <div class="flex items-center gap-3 py-2 px-3">
                            <input
                                type="checkbox"
                                wire:click="toggleAssessment({{ $assessment->id }})"
                                checked
                                class="checkbox checkbox-primary checkbox-sm"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">
                                    {{ $assessment->work_order }}{{ $assessment->extension !== '@' ? ' / ' . $assessment->extension : '' }}
                                </p>
                                <p class="text-xs text-base-content/50 truncate">
                                    {{ $assessment->circuit?->line_name ?? 'Unknown Circuit' }}
                                    &bull; {{ $assessment->status }}
                                    &bull; {{ $assessment->scope_year }}
                                </p>
                            </div>
                            @if(in_array($assessment->id, $detectedAssessmentIds))
                                <span class="badge badge-info badge-xs">Detected</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Search for More --}}
            <div class="mt-4">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="assessmentSearch"
                    placeholder="Search by work order or circuit name to add more..."
                    class="input input-bordered w-full"
                />

                @if($this->searchedAssessments->isNotEmpty())
                    <div class="mt-2 max-h-48 overflow-y-auto divide-y divide-base-200 border border-base-200 rounded-lg">
                        @foreach($this->searchedAssessments as $assessment)
                            <label class="flex items-center gap-3 py-2 px-3 cursor-pointer hover:bg-base-200 transition-colors">
                                <input
                                    type="checkbox"
                                    wire:click="toggleAssessment({{ $assessment->id }})"
                                    class="checkbox checkbox-primary checkbox-sm"
                                />
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate">
                                        {{ $assessment->work_order }}{{ $assessment->extension !== '@' ? ' / ' . $assessment->extension : '' }}
                                    </p>
                                    <p class="text-xs text-base-content/50 truncate">
                                        {{ $assessment->circuit?->line_name ?? 'Unknown Circuit' }}
                                        &bull; {{ $assessment->status }}
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex items-center justify-between">
        <button wire:click="previousStep" class="btn btn-ghost">
            <x-heroicon-o-arrow-left class="size-4" />
            Back
        </button>

        <button wire:click="nextStep" class="btn btn-primary">
            Next
            <x-heroicon-o-arrow-right class="size-4" />
        </button>
    </div>
</div>
