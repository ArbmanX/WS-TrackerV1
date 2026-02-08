{{--
    Mock B — "Clean Minimalist" Dashboard Redesign
    Generous whitespace, large radial progress rings per region,
    gradient accent borders, and a timeline-style active assessments panel.

    To preview: temporarily change Overview.php render() to return view('livewire.dashboard.overview-mock-b')
--}}

<div class="space-y-8">
    @php
        $totalStats = $this->systemMetrics->first() ?? [];
        $totalMiles = $totalStats['total_miles'] ?? 0;
        $completedMiles = $totalStats['completed_miles'] ?? 0;
        $overallPercent = $totalMiles > 0 ? round(($completedMiles / $totalMiles) * 100) : 0;
        $remainingMiles = $totalMiles - $completedMiles;
        $totalAssessments = $totalStats['total_assessments'] ?? 0;
        $activePlanners = $totalStats['active_planners'] ?? 0;
        $totalCircuits = $this->regionalMetrics->sum('Total_Circuits');
    @endphp

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- HEADER: Minimal with greeting context                  --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
            <p class="text-base-content/50 mt-1">
                {{ number_format($totalAssessments) }} assessments across {{ $this->regionalMetrics->count() }} regions
            </p>
        </div>

        <div class="flex items-center gap-2">
            <x-ui.view-toggle :current="$viewMode" />
            <button
                type="button"
                class="btn btn-circle btn-ghost btn-sm"
                wire:click="$refresh"
                wire:loading.attr="disabled"
                title="Refresh data"
            >
                <x-heroicon-o-arrow-path class="size-4" wire:loading.class="animate-spin" />
            </button>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- HERO STATS: Large numbers with subtle accent borders   --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Overall Progress --}}
        <div class="card bg-base-100 shadow-sm border-l-4 border-primary">
            <div class="card-body p-5">
                <p class="text-xs font-medium uppercase tracking-wider text-base-content/40">Progress</p>
                <p class="text-3xl font-extrabold tabular-nums mt-1">
                    {{ $overallPercent }}<span class="text-lg font-normal text-base-content/40">%</span>
                </p>
                <progress
                    class="progress progress-primary h-1.5 mt-2"
                    value="{{ $overallPercent }}"
                    max="100"
                ></progress>
            </div>
        </div>

        {{-- Total Miles --}}
        <div class="card bg-base-100 shadow-sm border-l-4 border-secondary">
            <div class="card-body p-5">
                <p class="text-xs font-medium uppercase tracking-wider text-base-content/40">Total Miles</p>
                <p class="text-3xl font-extrabold tabular-nums mt-1">
                    {{ number_format($totalMiles) }}
                </p>
                <p class="text-xs text-base-content/50 mt-2">
                    {{ number_format($remainingMiles) }} remaining
                </p>
            </div>
        </div>

        {{-- Active Assessments --}}
        <div class="card bg-base-100 shadow-sm border-l-4 border-accent">
            <div class="card-body p-5">
                <p class="text-xs font-medium uppercase tracking-wider text-base-content/40">Assessments</p>
                <p class="text-3xl font-extrabold tabular-nums mt-1">
                    {{ number_format($totalAssessments) }}
                </p>
                <p class="text-xs text-base-content/50 mt-2">
                    {{ number_format($totalCircuits) }} circuits tracked
                </p>
            </div>
        </div>

        {{-- Active Planners --}}
        <div class="card bg-base-100 shadow-sm border-l-4 border-info">
            <div class="card-body p-5">
                <p class="text-xs font-medium uppercase tracking-wider text-base-content/40">Planners</p>
                <p class="text-3xl font-extrabold tabular-nums mt-1">
                    {{ number_format($activePlanners) }}
                </p>
                <p class="text-xs text-base-content/50 mt-2">
                    across {{ $this->regionalMetrics->count() }} regions
                </p>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- CONTENT AREA                                           --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    @if ($viewMode === 'cards')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Region Cards: Large radial ring focus --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Section label --}}
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold">Regions</h2>
                    <div class="flex-1 border-t border-base-200"></div>
                    <span class="text-xs text-base-content/40">{{ $this->regionalMetrics->count() }} total</span>
                </div>

                @if ($this->regionalMetrics->isEmpty())
                    <div class="card bg-base-100 shadow-sm">
                        <div class="card-body text-center py-16">
                            <x-heroicon-o-globe-americas class="size-16 mx-auto mb-4 text-base-content/20" />
                            <h3 class="text-lg font-medium mb-1">No Regions</h3>
                            <p class="text-base-content/50 text-sm">No active regions to display.</p>
                        </div>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                        @foreach ($this->regionalMetrics as $region)
                            @php
                                $rMiles = $region['Total_Miles'] ?? 0;
                                $rCompleted = $region['Completed_Miles'] ?? 0;
                                $rPercent = $rMiles > 0 ? round(($rCompleted / $rMiles) * 100) : 0;
                                $rRemaining = $rMiles - $rCompleted;
                                $rColor = $rPercent >= 75 ? 'success' : ($rPercent >= 50 ? 'warning' : 'primary');
                            @endphp
                            <div
                                class="card bg-base-100 shadow-sm hover:shadow-md transition-all cursor-pointer group border border-base-200 hover:border-{{ $rColor }}/30"
                                wire:click="openPanel('{{ $region['Region'] ?? '' }}')"
                                wire:key="min-card-{{ $region['Region'] ?? $loop->index }}"
                            >
                                <div class="card-body p-5 items-center text-center gap-4">
                                    {{-- Radial Progress Ring (large) --}}
                                    <div
                                        class="radial-progress text-{{ $rColor }} bg-base-200/50"
                                        style="--value:{{ $rPercent }}; --size:6rem; --thickness:5px;"
                                        role="progressbar"
                                        aria-valuenow="{{ $rPercent }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    >
                                        <div class="flex flex-col items-center">
                                            <span class="text-2xl font-extrabold">{{ $rPercent }}</span>
                                            <span class="text-[10px] text-base-content/50 -mt-0.5">percent</span>
                                        </div>
                                    </div>

                                    {{-- Region Name --}}
                                    <div>
                                        <h3 class="font-bold">{{ $region['Region'] ?? 'Unknown' }}</h3>
                                        <p class="text-xs text-base-content/50">{{ $region['Total_Circuits'] ?? 0 }} circuits</p>
                                    </div>

                                    {{-- Mini Stats --}}
                                    <div class="w-full grid grid-cols-3 divide-x divide-base-200 text-center">
                                        <div class="px-2">
                                            <p class="text-sm font-bold tabular-nums">{{ $region['Active_Count'] ?? 0 }}</p>
                                            <p class="text-[10px] text-base-content/40 uppercase">Active</p>
                                        </div>
                                        <div class="px-2">
                                            <p class="text-sm font-bold tabular-nums">{{ number_format($rMiles, 0) }}</p>
                                            <p class="text-[10px] text-base-content/40 uppercase">Miles</p>
                                        </div>
                                        <div class="px-2">
                                            <p class="text-sm font-bold tabular-nums">{{ $region['Active_Planners'] ?? 0 }}</p>
                                            <p class="text-[10px] text-base-content/40 uppercase">Planners</p>
                                        </div>
                                    </div>

                                    {{-- Miles detail --}}
                                    <div class="w-full text-xs text-base-content/50 flex justify-between">
                                        <span>{{ number_format($rCompleted) }} mi done</span>
                                        <span>{{ number_format($rRemaining) }} mi left</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Active Assessments: Timeline-style --}}
            <div class="lg:col-span-1 space-y-6">
                {{-- Section label --}}
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-semibold">Activity</h2>
                    <div class="flex-1 border-t border-base-200"></div>
                </div>

                <div class="card bg-base-100 shadow-sm border border-base-200">
                    <div class="card-body p-0">
                        {{-- Embedded Active Assessments --}}
                        <livewire:dashboard.active-assessments />
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- Table View --}}
        <div class="space-y-6">
            {{-- Section label --}}
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold">All Regions</h2>
                <div class="flex-1 border-t border-base-200"></div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                <div class="card bg-base-100 shadow-sm border border-base-200 overflow-hidden">
                    <x-dashboard.region-table
                        :regions="$this->regionalMetrics"
                        :sortBy="$sortBy"
                        :sortDir="$sortDir"
                    />
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-2">
                        <h2 class="text-lg font-semibold">Activity</h2>
                        <div class="flex-1 border-t border-base-200"></div>
                    </div>
                    <div class="relative">
                        <livewire:dashboard.active-assessments />
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Loading Overlay --}}
    <div
        wire:loading.flex
        wire:target="openPanel, closePanel, sort"
        class="fixed inset-0 z-40 items-center justify-center bg-base-100/60 backdrop-blur-sm"
    >
        <div class="card bg-base-100 shadow-xl p-6">
            <div class="flex items-center gap-4">
                <span class="loading loading-dots loading-md text-primary"></span>
                <span class="text-sm font-medium">Loading data...</span>
            </div>
        </div>
    </div>
</div>
