{{--
    Mock A — "Command Center" Dashboard Redesign
    Dense, data-forward layout with radial progress indicators,
    inline sparkline-style metrics, and a region comparison strip.

    To preview: temporarily change Overview.php render() to return view('livewire.dashboard.overview-mock-a')
--}}

<div class="space-y-6">
    @php
        $totalStats = $this->systemMetrics->first() ?? [];
        $totalMiles = $totalStats['total_miles'] ?? 0;
        $completedMiles = $totalStats['completed_miles'] ?? 0;
        $overallPercent = $totalMiles > 0 ? round(($completedMiles / $totalMiles) * 100) : 0;
        $remainingMiles = $totalMiles - $completedMiles;
        $totalAssessments = $totalStats['total_assessments'] ?? 0;
        $activePlanners = $totalStats['active_planners'] ?? 0;
    @endphp

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- HERO HEADER: Title + Quick Actions                     --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3 mb-1">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-content shadow-sm">
                    <x-heroicon-s-chart-bar-square class="size-5" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Command Center</h1>
                    <p class="text-sm text-base-content/50">Circuit assessment progress &mdash; all regions</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.view-toggle :current="$viewMode" />
            <div class="divider divider-horizontal mx-0"></div>
            <button
                type="button"
                class="btn btn-ghost btn-sm gap-2"
                wire:click="$refresh"
                wire:loading.attr="disabled"
            >
                <x-heroicon-o-arrow-path class="size-4" wire:loading.class="animate-spin" />
                <span class="hidden sm:inline">Refresh</span>
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- KPI STRIP: 4 stats with radial progress hero           --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- Hero: Overall Progress (radial) --}}
        <div class="card bg-base-100 shadow md:row-span-1">
            <div class="card-body items-center text-center p-4">
                <div
                    class="radial-progress text-primary"
                    style="--value:{{ $overallPercent }}; --size:5rem; --thickness:6px;"
                    role="progressbar"
                    aria-valuenow="{{ $overallPercent }}"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <span class="text-xl font-bold">{{ $overallPercent }}%</span>
                </div>
                <p class="text-xs text-base-content/60 mt-1">Overall Progress</p>
                <p class="text-xs text-base-content/40">
                    {{ number_format($completedMiles) }} / {{ number_format($totalMiles) }} mi
                </p>
            </div>
        </div>

        {{-- Stat: Total Assessments --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body p-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        <x-heroicon-o-bolt class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold tabular-nums">{{ number_format($totalAssessments) }}</p>
                        <p class="text-xs text-base-content/60">Total Assessments</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Stat: Miles Remaining --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body p-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                        <x-heroicon-o-map class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold tabular-nums">{{ number_format($remainingMiles) }}</p>
                        <p class="text-xs text-base-content/60">Miles Remaining</p>
                    </div>
                </div>
                <progress
                    class="progress progress-warning h-1 mt-1"
                    value="{{ $completedMiles }}"
                    max="{{ max($totalMiles, 1) }}"
                ></progress>
            </div>
        </div>

        {{-- Stat: Active Planners --}}
        <div class="card bg-base-100 shadow">
            <div class="card-body p-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent">
                        <x-heroicon-o-users class="size-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-bold tabular-nums">{{ number_format($activePlanners) }}</p>
                        <p class="text-xs text-base-content/60">Active Planners</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- REGION COMPARISON STRIP (always visible, horizontal)   --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if ($this->regionalMetrics->isNotEmpty())
        <div class="card bg-base-100 shadow">
            <div class="card-body p-4">
                <h2 class="text-sm font-semibold text-base-content/70 mb-3">Region Comparison</h2>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->regionalMetrics as $region)
                        @php
                            $rMiles = $region['Total_Miles'] ?? 0;
                            $rCompleted = $region['Completed_Miles'] ?? 0;
                            $rPercent = $rMiles > 0 ? round(($rCompleted / $rMiles) * 100) : 0;
                            $rColor = $rPercent >= 75 ? 'success' : ($rPercent >= 50 ? 'warning' : 'primary');
                        @endphp
                        <div
                            class="flex items-center gap-3 rounded-xl border border-base-200 px-4 py-3 hover:bg-base-200/50 transition-colors cursor-pointer flex-1 min-w-[180px]"
                            wire:click="openPanel('{{ $region['Region'] ?? '' }}')"
                            wire:key="strip-{{ $region['Region'] ?? $loop->index }}"
                        >
                            <div
                                class="radial-progress text-{{ $rColor }}"
                                style="--value:{{ $rPercent }}; --size:2.5rem; --thickness:3px;"
                                role="progressbar"
                            >
                                <span class="text-xs font-bold">{{ $rPercent }}%</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-sm truncate">{{ $region['Region'] ?? 'Unknown' }}</p>
                                <p class="text-xs text-base-content/50">
                                    {{ number_format($rCompleted) }} / {{ number_format($rMiles) }} mi
                                </p>
                            </div>
                            <div class="ml-auto flex flex-col items-end gap-0.5">
                                <span class="badge badge-sm badge-{{ $rColor }} badge-outline">
                                    {{ $region['Active_Count'] ?? 0 }} active
                                </span>
                                <span class="text-xs text-base-content/40">
                                    {{ $region['Active_Planners'] ?? 0 }} planners
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- MAIN CONTENT: Cards or Table + Active Assessments      --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if ($viewMode === 'cards')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Region Detail Cards --}}
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
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($this->regionalMetrics as $region)
                            @php
                                $rMiles = $region['Total_Miles'] ?? 0;
                                $rCompleted = $region['Completed_Miles'] ?? 0;
                                $rPercent = $rMiles > 0 ? round(($rCompleted / $rMiles) * 100) : 0;
                                $rRemaining = $rMiles - $rCompleted;
                                $rColor = $rPercent >= 75 ? 'success' : ($rPercent >= 50 ? 'warning' : 'primary');
                            @endphp
                            <div
                                class="card bg-base-100 shadow hover:shadow-lg transition-all cursor-pointer group"
                                wire:click="openPanel('{{ $region['Region'] ?? '' }}')"
                                wire:key="card-{{ $region['Region'] ?? $loop->index }}"
                            >
                                <div class="card-body p-4 gap-3">
                                    {{-- Card Header --}}
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <x-heroicon-o-map-pin class="size-5" />
                                            </div>
                                            <div>
                                                <h3 class="font-bold text-sm">{{ $region['Region'] ?? 'Unknown' }}</h3>
                                                <p class="text-xs text-base-content/50">{{ $region['Total_Circuits'] ?? 0 }} circuits</p>
                                            </div>
                                        </div>
                                        <x-heroicon-o-chevron-right class="size-4 text-base-content/30 group-hover:text-primary transition-colors" />
                                    </div>

                                    {{-- Inline Stats Row --}}
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-1 text-base-content/60">
                                            <x-heroicon-o-bolt class="size-3.5" />
                                            <span class="font-semibold text-base-content">{{ $region['Active_Count'] ?? 0 }}</span>
                                            active
                                        </div>
                                        <div class="flex items-center gap-1 text-base-content/60">
                                            <x-heroicon-o-users class="size-3.5" />
                                            <span class="font-semibold text-base-content">{{ $region['Active_Planners'] ?? 0 }}</span>
                                            planners
                                        </div>
                                        <div class="flex items-center gap-1 text-base-content/60">
                                            <span class="font-semibold text-base-content">{{ number_format($region['Total_Units'] ?? 0) }}</span>
                                            units
                                        </div>
                                    </div>

                                    {{-- Progress Bar --}}
                                    <div>
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-base-content/50">{{ number_format($rCompleted) }} mi done</span>
                                            <span class="font-bold text-{{ $rColor }}">{{ $rPercent }}%</span>
                                        </div>
                                        <progress
                                            class="progress progress-{{ $rColor }} h-2 w-full"
                                            value="{{ $rPercent }}"
                                            max="100"
                                        ></progress>
                                        <p class="text-xs text-base-content/40 mt-1 text-right">
                                            {{ number_format($rRemaining) }} mi remaining
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Active Assessments Sidebar --}}
            <div class="relative lg:col-span-1">
                <livewire:dashboard.active-assessments />
            </div>
        </div>
    @else
        {{-- Table View --}}
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
        class="fixed inset-0 z-40 items-center justify-center bg-base-100/50 backdrop-blur-sm"
    >
        <div class="flex flex-col items-center gap-3">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <span class="text-sm text-base-content/60">Loading...</span>
        </div>
    </div>
</div>
