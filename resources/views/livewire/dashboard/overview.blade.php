<div class="space-y-6">
    {{-- Summary Stats --}}
    @php
        $totalStats = $this->systemMetrics->first() ?? [];
        $totalMiles = $totalStats['total_miles'] ?? 0;
        $completedMiles = $totalStats['completed_miles'] ?? 0;
        $overallPercent = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
    @endphp

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Regional Overview</h1>
            <p class="text-base-content/60">Circuit assessment progress across all regions</p>
        </div>

        <div class="flex items-center gap-3">
            <x-ui.view-toggle :current="$viewMode" />

            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                wire:click="$refresh"
                wire:loading.attr="disabled"
            >
                <x-heroicon-o-arrow-path class="size-4" wire:loading.class="animate-spin" />
            </button>
        </div>
    </div>

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <x-ui.stat-card
            label="Total Assessments"
            :value="number_format($totalStats['total_assessments'] ?? 0)"
            icon="bolt"
            color="primary"
            size="sm"
        />

        <x-ui.stat-card
            label="Total Miles"
            :value="number_format($totalMiles, 0)"
            suffix="mi"
            icon="map"
            size="sm"
        />

        <x-ui.stat-card
            label="Overall Progress"
            :value="number_format($overallPercent, 0)"
            suffix="%"
            icon="chart-bar"
            :color="$overallPercent >= 75 ? 'success' : ($overallPercent <= 50 ? 'warning' : 'primary')"
            size="sm"
        />

        <x-ui.stat-card
            label="Active Planners"
            :value="number_format($totalStats['active_planners'] ?? 0)"
            icon="users"
            size="sm"
        />
    </div>

    {{-- Content --}}
    @if ($viewMode === 'cards')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left: Region Cards (2x2 sub-grid) --}}
            <div class="lg:col-span-2">
                @if ($this->regionalMetrics->isEmpty())
                    <div class="card bg-base-100 shadow">
                        <div class="card-body text-center py-12">
                            <x-heroicon-o-map class="size-12 mx-auto mb-4 text-base-content/30" />
                            <h3 class="text-lg font-medium mb-1">No Regions Found</h3>
                            <p class="text-base-content/60">There are no active regions to display.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-4">
                        @foreach ($this->regionalMetrics as $region)
                            <x-dashboard.region-card
                                :region="$region"
                                wire:key="region-card-{{ $region['Region'] ?? $loop->index }}"
                            />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Right: Active Assessments --}}
            <div class="relative lg:col-span-1">
                <livewire:dashboard.active-assessments />
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
            <div class="bg-base-100 rounded-box shadow xl:col-span-1">
                <x-dashboard.region-table
                    :regions="$this->regionalMetrics"
                    :sortBy="$sortBy"
                    :sortDir="$sortDir"
                />
            </div>

            <div class="relative xl:col-span-1">
                <livewire:dashboard.active-assessments />
            </div>
        </div>
    @endif

    {{-- Loading Overlay --}}
    <div
        wire:loading.flex
        wire:target="openPanel, closePanel, sort"
        class="fixed inset-0 z-40 items-center justify-center bg-base-100/50"
    >
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>
</div>
