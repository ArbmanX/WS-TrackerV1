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
