    <div class="space-y-4">
    @php
        // ── System-wide totals (from WorkStudio API via CachedQueryService) ──
        $totalStats = $this->systemMetrics->first() ?? [];
        $totalMiles = $totalStats['total_miles'] ?? 0;
        $completedMiles = $totalStats['completed_miles'] ?? 0;
        $overallPercent = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;

        // ── Local DB computed properties ──
        $milesPipelineByRegion = $this->milesPipelineByRegion;
        $permissionsSystemWide = $this->permissionsSystemWide;
        $workTypeBreakdown = $this->workTypeBreakdown;
        $summaryStats = $this->summaryStats;
        $burndownSnapshots = $this->burndownSnapshots;
        $contractEnd = $this->contractEnd;
        $ctaAssessmentsByRegion = $this->ctaAssessmentsByRegion;

        // Derived stats
        $weeksRemaining = max(1, ceil($summaryStats['days_remaining'] / 7));
        $milesPerWeekNeeded = $summaryStats['remaining_miles'] / $weeksRemaining;
        $activePlanners = $totalStats['active_planners'] ?? 0;

        // Planners CTA — still partially mock (PlannerMetricsService not yet wired)
        $ctaPlanners = [
            'production' => ['active' => $activePlanners ?: 0, 'behind_quota' => 0],
            'quota'      => ['target_mi_wk' => 42.0, 'actual_mi_wk' => $milesPerWeekNeeded],
        ];

        // Admin CTA — still mock (AlertService not yet wired)
        $ctaAdmin = [
            'alerts' => 0,
            'stale_planners' => 0,
        ];
    @endphp

    {{-- Charts Row --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- ═══ 1. System Wide Overview (3 cols) ═══ --}}
        <div class="card bg-base-100 border-l-[3px] border-l-primary shadow-md" x-data="{ open: true }">
            {{-- Header ribbon --}}
            <div class="bg-base-200/80 px-4 py-2 flex items-center justify-between cursor-pointer md:pointer-events-none" @click="open = !open">
                <h1 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">System Wide Overview</h1>
                <svg class="size-4 text-base-content/50 transition-transform md:hidden" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </div>

            <div class="p-4 space-y-4" x-show="$store.sidebar.breakpoint !== 'mobile' || open" x-transition>
                {{-- Stat row — Renders: overallPercent, completedMiles, totalMiles,
                     remaining_miles, weeksRemaining, activePlanners, milesPerWeekNeeded
                     Source: $this->systemMetrics (active assessments only) --}}
                <div class="border-t-2 border-primary pt-3">
                    <div class="grid grid-cols-2 gap-y-3 md:grid-cols-5 md:divide-x md:divide-base-300">
                        <div class="text-center px-2">
                            <p class="text-2xl font-black font-code tabular-nums text-base-content">{{ number_format($overallPercent, 0) }}%</p>
                            <span class="text-xs text-base-content/65 tabular-nums">{{ number_format($completedMiles, 0) }} / {{ number_format($totalMiles, 0) }} mi</span>
                        </div>
                        <div class="text-center px-2">
                            <p class="text-2xl font-black font-code tabular-nums text-base-content">{{ number_format($summaryStats['remaining_miles'], 1) }}</p>
                            <span class="text-xs text-base-content/65">mi remaining</span>
                        </div>
                        <div class="text-center px-2">
                            <p class="text-2xl font-black font-code tabular-nums text-base-content">{{ $weeksRemaining }}</p>
                            <span class="text-xs text-base-content/65">wks left</span>
                        </div>
                        <div class="text-center px-2">
                            <p class="text-2xl font-black font-code tabular-nums text-base-content">{{ $activePlanners }}</p>
                            <span class="text-xs text-base-content/65">planners</span>
                        </div>
                        <div class="text-center px-2 col-span-2 md:col-span-1">
                            <p class="text-2xl font-black font-code tabular-nums text-base-content">{{ number_format($milesPerWeekNeeded, 1) }}</p>
                            <span class="text-xs text-base-content/65">mi/wk need</span>
                        </div>
                    </div>
                </div>

                {{-- Miles Burndown — Renders: $burndownSnapshots
                     Area chart showing remaining miles over time.
                     Dashed line = target pace from contract start to end (0 mi).
                     Source: system_wide_snapshots table, one point per snapshot day.
                     remaining = total_miles - completed_miles per snapshot. --}}
                <div
                    x-data="{
                        chart: null,
                        resolveHex(cssVar) {
                            const value = getComputedStyle(document.documentElement).getPropertyValue(cssVar).trim();
                            const c = document.createElement('canvas');
                            c.width = c.height = 1;
                            const ctx = c.getContext('2d');
                            ctx.fillStyle = value;
                            ctx.fillRect(0, 0, 1, 1);
                            const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
                            return '#' + [r, g, b].map(n => n.toString(16).padStart(2, '0')).join('');
                        },
                        toRgba(hex, a) {
                            const r = parseInt(hex.slice(1,3), 16);
                            const g = parseInt(hex.slice(3,5), 16);
                            const b = parseInt(hex.slice(5,7), 16);
                            return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
                        },
                        buildChart() {
                            const snapshots = @js($burndownSnapshots);
                            const contractEnd = @js($contractEnd);
                            const primary = this.resolveHex('--color-primary');
                            const textColor = this.resolveHex('--color-base-content');
                            const errorColor = this.resolveHex('--color-error');

                            const labels = snapshots.map(s => s.date);
                            const remaining = snapshots.map(s => s.remaining);

                            // Target pace: straight line from first remaining to 0 at contract end
                            const startRemaining = remaining[0];
                            const startDate = new Date(labels[0]);
                            const endDate = new Date(contractEnd);
                            const totalDays = (endDate - startDate) / 86400000;

                            const paceData = labels.map(d => {
                                const elapsed = (new Date(d) - startDate) / 86400000;
                                return Math.max(0, startRemaining * (1 - elapsed / totalDays));
                            });

                            this.chart = new Chart(this.$refs.burndown, {
                                type: 'line',
                                data: {
                                    labels,
                                    datasets: [
                                        {
                                            label: 'Remaining',
                                            data: remaining,
                                            borderColor: primary,
                                            backgroundColor: this.toRgba(primary, 0.1),
                                            fill: true,
                                            tension: 0.3,
                                            pointRadius: 0,
                                            pointHitRadius: 8,
                                            borderWidth: 2,
                                        },
                                        {
                                            label: 'Target Pace',
                                            data: paceData,
                                            borderColor: this.toRgba(errorColor, 0.5),
                                            borderDash: [4, 4],
                                            borderWidth: 1.5,
                                            pointRadius: 0,
                                            fill: false,
                                        }
                                    ]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    scales: {
                                        x: {
                                            grid: { display: false },
                                            border: { display: false },
                                            ticks: {
                                                font: { size: 9, family: 'Fira Code, monospace' },
                                                color: this.toRgba(textColor, 0.4),
                                                maxTicksLimit: 5,
                                                callback: function(val, i) {
                                                    const d = new Date(labels[i]);
                                                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                                                }
                                            }
                                        },
                                        y: {
                                            grid: { color: this.toRgba(textColor, 0.06) },
                                            border: { display: false },
                                            ticks: {
                                                font: { size: 9, family: 'Fira Code, monospace' },
                                                color: this.toRgba(textColor, 0.4),
                                                maxTicksLimit: 4,
                                                callback: v => v.toLocaleString() + ' mi'
                                            }
                                        }
                                    },
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            titleFont: { family: 'Fira Code, monospace', size: 10 },
                                            bodyFont: { family: 'Fira Code, monospace', size: 10 },
                                            callbacks: {
                                                title: (items) => {
                                                    const d = new Date(items[0].label);
                                                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                                                },
                                                label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y.toLocaleString()} mi`
                                            }
                                        }
                                    }
                                }
                            });
                        },
                        init() { requestAnimationFrame(() => this.buildChart()); },
                        destroy() { this.chart?.destroy(); }
                    }"
                    x-on:destroy="destroy()"
                >
                    <div class="flex items-baseline justify-between mb-1">
                        <h3 class="text-xs font-bold uppercase tracking-[0.1em] text-base-content/70">Miles Burndown</h3>
                        <div class="flex items-center gap-3 text-[10px] text-base-content/50">
                            <span class="flex items-center gap-1"><span class="inline-block w-3 h-0.5 bg-primary rounded"></span> Actual</span>
                            <span class="flex items-center gap-1"><span class="inline-block w-3 h-0 border-t-[1.5px] border-dashed border-error/50 rounded"></span> Target</span>
                        </div>
                    </div>
                    <div class="h-32">
                        <canvas x-ref="burndown"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══ 2. Miles Pipeline by Region (2 cols) ═══
             Renders: $milesPipelineByRegion — Chart.js stacked bar.
             Each region shows miles in each status bucket as % of total.
             Source: Active assessments only, grouped by region.
             Statuses: not_started, in_progress, pending_qc, rework, closed --}}
        <div class="card bg-base-100 border-l-[3px] border-l-secondary shadow-md" x-data="{ open: false }">
            <div class="bg-base-200/80 px-4 py-2 flex items-center justify-between cursor-pointer md:pointer-events-none" @click="open = !open">
                <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Miles by Region</h3>
                <svg class="size-4 text-base-content/50 transition-transform md:hidden" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </div>
            <div
                class="p-4"
                x-show="$store.sidebar.breakpoint !== 'mobile' || open"
                x-collapse
                x-data="{
                    chart: null,
                    colors: {},
                    pctData: [],
                    lastAxis: null,
                    resolveHex(cssVar) {
                        const value = getComputedStyle(document.documentElement).getPropertyValue(cssVar).trim();
                        const c = document.createElement('canvas');
                        c.width = c.height = 1;
                        const ctx = c.getContext('2d');
                        ctx.fillStyle = value;
                        ctx.fillRect(0, 0, 1, 1);
                        const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
                        return '#' + [r, g, b].map(n => n.toString(16).padStart(2, '0')).join('');
                    },
                    toRgba(hex, a) {
                        const r = parseInt(hex.slice(1,3), 16);
                        const g = parseInt(hex.slice(3,5), 16);
                        const b = parseInt(hex.slice(5,7), 16);
                        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
                    },
                    isMobile() { return window.innerWidth < 768; },
                    buildChart() {
                        const data = @js($milesPipelineByRegion);
                        const textColor = this.resolveHex('--color-base-content');
                        const mobile = this.isMobile();
                        this.lastAxis = mobile ? 'x' : 'y';

                        const primary = this.resolveHex('--color-primary');
                        const secondary = this.resolveHex('--color-secondary');
                        const error = this.resolveHex('--color-error');
                        const warning = this.resolveHex('--color-warning');
                        const content = this.resolveHex('--color-base-content');

                        this.colors = {
                            closed:      primary,
                            pending_qc:  secondary,
                            rework:      error,
                            in_progress: warning,
                            not_started: this.toRgba(content, 0.55),
                        };

                        this.pctData = data.map(d => {
                            const total = d.closed + d.pending_qc + d.rework + d.in_progress + d.not_started;
                            return {
                                region: d.region, total,
                                closed:      total > 0 ? (d.closed / total) * 100 : 0,
                                pending_qc:  total > 0 ? (d.pending_qc / total) * 100 : 0,
                                rework:      total > 0 ? (d.rework / total) * 100 : 0,
                                in_progress: total > 0 ? (d.in_progress / total) * 100 : 0,
                                not_started: total > 0 ? (d.not_started / total) * 100 : 0,
                                raw: d,
                            };
                        });

                        const pctData = this.pctData;
                        const axis = this.lastAxis;
                        const pctAxis = mobile ? 'y' : 'x';

                        this.chart = new Chart(this.$refs.canvas, {
                            type: 'bar',
                            data: {
                                labels: pctData.map(d => d.region),
                                datasets: [
                                    { label: 'Closed',      data: pctData.map(d => d.closed),      backgroundColor: this.colors.closed,      barPercentage: 0.6 },
                                    { label: 'QC',          data: pctData.map(d => d.pending_qc),  backgroundColor: this.colors.pending_qc,  barPercentage: 0.6 },
                                    { label: 'Rework',      data: pctData.map(d => d.rework),      backgroundColor: this.colors.rework,      barPercentage: 0.6 },
                                    { label: 'In Progress', data: pctData.map(d => d.in_progress), backgroundColor: this.colors.in_progress, barPercentage: 0.6 },
                                    { label: 'Not Started', data: pctData.map(d => d.not_started), backgroundColor: this.colors.not_started, barPercentage: 0.6 },
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: axis,
                                scales: {
                                    [pctAxis]: {
                                        stacked: true,
                                        max: 100,
                                        grid: { display: false },
                                        ticks: { font: { size: 10, family: 'Fira Code, monospace' }, color: textColor, callback: v => v + '%' },
                                        border: { display: false },
                                    },
                                    [axis]: {
                                        stacked: true,
                                        grid: { display: false },
                                        ticks: { font: { size: 11 }, color: textColor },
                                        border: { display: false },
                                    }
                                },
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        titleFont: { family: 'Fira Code, monospace', size: 11 },
                                        bodyFont: { family: 'Fira Code, monospace', size: 10 },
                                        callbacks: {
                                            label: ctx => {
                                                const raw = pctData[ctx.dataIndex].raw;
                                                const key = ['closed','pending_qc','rework','in_progress','not_started'][ctx.datasetIndex];
                                                const mi = raw[key];
                                                const parsed = mobile ? ctx.parsed.y : ctx.parsed.x;
                                                const pct = parsed.toFixed(1);
                                                return ` ${ctx.dataset.label}: ${mi.toLocaleString()} mi (${pct}%)`;
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    },
                    handleResize() {
                        const mobile = this.isMobile();
                        const newAxis = mobile ? 'x' : 'y';
                        if (newAxis !== this.lastAxis) {
                            this.chart?.destroy();
                            this.buildChart();
                        }
                    },
                    init() { requestAnimationFrame(() => this.buildChart()); },
                    destroy() { this.chart?.destroy(); }
                }"
                x-on:destroy="destroy()"
                @resize.window.debounce.300ms="handleResize()"
            >
                <div>
                    <div class="h-56 md:h-44">
                        <canvas x-ref="canvas"></canvas>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-base-content/60 mt-1.5">
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="'background:' + colors.closed"></span> Closed</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="'background:' + colors.pending_qc"></span> QC</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="'background:' + colors.rework"></span> Rework</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="'background:' + colors.in_progress"></span> In Progress</span>
                        <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm" :style="'background:' + colors.not_started"></span> Not Started</span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══ Quick Actions — Hub CTA Cards ═══
         Role-aware links to hub pages. Each card shows 2 key stats
         and an action link. Disabled gracefully if route not yet built.
         Source: $ctaPlanners, $ctaAssessments, $ctaAdmin (mock data above). --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        {{-- Planners CTA — toggle between Production and Quota views --}}
        @php
            $plannersRoute = 'planner-metrics.overview';
            $plannersEnabled = Route::has($plannersRoute);
        @endphp
        <div
            class="card bg-base-100 border-l-[3px] border-l-info shadow-md"
            x-data="{ view: 'production', data: @js($ctaPlanners) }"
        >
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-info/10 text-info">
                            <x-ui.icon name="users" size="sm" />
                        </div>
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-base-content/70">Planners</h3>
                    </div>
                </div>

                {{-- Toggle chips --}}
                <div class="flex gap-1.5 mb-3">
                    <button
                        class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                        :class="view === 'production' ? 'bg-info/15 text-info' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                        @click="view = 'production'"
                    >Production</button>
                    <button
                        class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                        :class="view === 'quota' ? 'bg-info/15 text-info' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                        @click="view = 'quota'"
                    >Weekly Quota</button>
                </div>

                {{-- Production view --}}
                <div class="flex gap-6 mb-3" x-show="view === 'production'" x-transition.opacity>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-base-content" x-text="data.production.active"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Active</span>
                    </div>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-warning" x-text="data.production.behind_quota"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Behind</span>
                    </div>
                </div>

                {{-- Quota view --}}
                <div class="flex gap-6 mb-3" x-show="view === 'quota'" x-transition.opacity>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-base-content" x-text="data.quota.target_mi_wk.toFixed(1)"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Target mi/wk</span>
                    </div>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-info" x-text="data.quota.actual_mi_wk.toFixed(1)"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Actual mi/wk</span>
                    </div>
                </div>

                @if($plannersEnabled)
                    <a href="{{ route($plannersRoute) }}" wire:navigate class="flex items-center gap-1 text-xs font-semibold text-info hover:underline">
                        <span>Review roster</span>
                        <x-heroicon-m-arrow-right class="size-3.5" />
                    </a>
                @else
                    <span class="flex items-center gap-1 text-xs font-semibold text-base-content/30">
                        <span>Review roster</span>
                        <x-heroicon-m-arrow-right class="size-3.5" />
                    </span>
                @endif
            </div>
        </div>

        {{-- Assessments CTA — filter by region --}}
        @php
            $assessmentsRoute = 'assessments.index';
            $assessmentsEnabled = Route::has($assessmentsRoute);
        @endphp
        <div
            class="card bg-base-100 border-l-[3px] border-l-primary shadow-md"
            x-data="{ region: 'all', data: @js($ctaAssessmentsByRegion), get stats() { return this.data[this.region]; } }"
        >
            <div class="p-4">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                            <x-ui.icon name="clipboard-document-list" size="sm" />
                        </div>
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-base-content/70">Assessments</h3>
                    </div>
                </div>

                {{-- Region filter chips --}}
                <div class="flex flex-wrap gap-1.5 mb-3">
                    @foreach(['all' => 'All', 'Central' => 'Central', 'Harrisburg' => 'Hbg', 'Lancaster' => 'Lanc', 'Lehigh' => 'Lehigh', 'Northeast' => 'NE', 'Susquehanna' => 'Susq'] as $key => $label)
                        <button
                            class="px-2 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                            :class="region === '{{ $key }}' ? 'bg-primary/15 text-primary' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                            @click="region = '{{ $key }}'"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                {{-- Stats (reactive to region) --}}
                <div class="flex gap-6 mb-3">
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-base-content" x-text="stats.active"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Active</span>
                    </div>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-secondary" x-text="stats.in_qc"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">In QC</span>
                    </div>
                    <div>
                        <p class="text-xl font-black font-code tabular-nums text-error" x-text="stats.rework"></p>
                        <span class="text-[10px] text-base-content/50 uppercase tracking-wide">Rework</span>
                    </div>
                </div>

                @if($assessmentsEnabled)
                    <a href="{{ route($assessmentsRoute) }}" wire:navigate class="flex items-center gap-1 text-xs font-semibold text-primary hover:underline">
                        <span>Review pipeline</span>
                        <x-heroicon-m-arrow-right class="size-3.5" />
                    </a>
                @else
                    <span class="flex items-center gap-1 text-xs font-semibold text-base-content/30">
                        <span>Review pipeline</span>
                        <x-heroicon-m-arrow-right class="size-3.5" />
                    </span>
                @endif
            </div>
        </div>

        {{-- Admin CTA — tabbed link groups for scalability --}}
        <div
            class="card bg-base-100 border-l-[3px] border-l-warning shadow-md"
            x-data="{ tab: 'tools' }"
        >
            <div class="p-4">
                <div class="flex items-center gap-2 mb-2">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-md bg-warning/10 text-warning">
                        <x-ui.icon name="shield-check" size="sm" />
                    </div>
                    <h3 class="text-[11px] font-bold uppercase tracking-[0.12em] text-base-content/70">Admin</h3>
                </div>

                {{-- Tab chips --}}
                <div class="flex gap-1.5 mb-3">
                    <button
                        class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                        :class="tab === 'tools' ? 'bg-warning/15 text-warning' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                        @click="tab = 'tools'"
                    >Tools</button>
                    <button
                        class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                        :class="tab === 'users' ? 'bg-warning/15 text-warning' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                        @click="tab = 'users'"
                    >Users</button>
                    <button
                        class="px-2.5 py-1 rounded text-[10px] font-bold uppercase tracking-wide transition-colors"
                        :class="tab === 'monitoring' ? 'bg-warning/15 text-warning' : 'bg-base-200 text-base-content/50 hover:text-base-content/70'"
                        @click="tab = 'monitoring'"
                    >Monitoring</button>
                </div>

                {{-- Tools tab --}}
                <div class="flex flex-col gap-2" x-show="tab === 'tools'" x-transition.opacity>
                    <a href="{{ route('data-management.query-explorer') }}" wire:navigate class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200/60 hover:bg-warning/10 border border-base-300/50 hover:border-warning/30 transition-colors group">
                        <span class="flex items-center gap-2 text-xs font-semibold text-base-content/75 group-hover:text-warning transition-colors">
                            <x-ui.icon name="magnifying-glass" size="sm" class="text-warning" />
                            Query Explorer
                        </span>
                        <x-heroicon-m-chevron-right class="size-3.5 text-base-content/30 group-hover:text-warning transition-colors" />
                    </a>
                    <a href="{{ route('data-management.cache') }}" wire:navigate class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200/60 hover:bg-warning/10 border border-base-300/50 hover:border-warning/30 transition-colors group">
                        <span class="flex items-center gap-2 text-xs font-semibold text-base-content/75 group-hover:text-warning transition-colors">
                            <x-ui.icon name="server-stack" size="sm" class="text-warning" />
                            Cache Controls
                        </span>
                        <x-heroicon-m-chevron-right class="size-3.5 text-base-content/30 group-hover:text-warning transition-colors" />
                    </a>
                </div>

                {{-- Users tab --}}
                <div class="flex flex-col gap-2" x-show="tab === 'users'" x-transition.opacity>
                    <a href="{{ route('user-management.create') }}" wire:navigate class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200/60 hover:bg-warning/10 border border-base-300/50 hover:border-warning/30 transition-colors group">
                        <span class="flex items-center gap-2 text-xs font-semibold text-base-content/75 group-hover:text-warning transition-colors">
                            <x-ui.icon name="user-plus" size="sm" class="text-warning" />
                            Create User
                        </span>
                        <x-heroicon-m-chevron-right class="size-3.5 text-base-content/30 group-hover:text-warning transition-colors" />
                    </a>
                </div>

                {{-- Monitoring tab (routes not built yet) --}}
                <div class="flex flex-col gap-2" x-show="tab === 'monitoring'" x-transition.opacity>
                    <span class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200/40 border border-base-300/30 opacity-50">
                        <span class="flex items-center gap-2 text-xs font-semibold text-base-content/50">
                            <x-ui.icon name="eye" size="sm" class="text-warning/50" />
                            Ghost Detections
                        </span>
                        <span class="text-[9px] text-base-content/40 uppercase">Soon</span>
                    </span>
                    <span class="flex items-center justify-between px-3 py-2 rounded-lg bg-base-200/40 border border-base-300/30 opacity-50">
                        <span class="flex items-center gap-2 text-xs font-semibold text-base-content/50">
                            <x-ui.icon name="arrow-path" size="sm" class="text-warning/50" />
                            Sync Status
                        </span>
                        <span class="text-[9px] text-base-content/40 uppercase">Soon</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══ Permissions — Mobile inline accordion ═══ --}}
    <div class="card bg-base-100 border-l-[3px] border-l-warning shadow-md md:hidden" x-data="{ open: false }">
        <div class="bg-base-200/80 px-3 py-2 flex items-center justify-between cursor-pointer" @click="open = !open">
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Permissions</p>
            <svg class="size-4 text-base-content/50 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </div>
        <div x-show="open" x-collapse>
            @include('livewire.dashboard._permissions-body')
        </div>
    </div>

    {{-- ═══ Work Breakdown — Mobile inline accordion ═══ --}}
    <div class="card bg-base-100 border-l-[3px] border-l-accent shadow-md md:hidden" x-data="{ open: false }">
        <div class="bg-base-200/80 px-3 py-2 flex items-center justify-between cursor-pointer" @click="open = !open">
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Work Breakdown</p>
            <svg class="size-4 text-base-content/50 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </div>
        <div class="divide-y divide-base-200" x-show="open" x-collapse>
            @include('livewire.dashboard._quick-stats-body')
        </div>
    </div>

    {{-- ═══ Slide-out panels (md+) — stacked on right edge ═══ --}}
    <div
        class="fixed right-0 top-1/2 -translate-y-1/2 z-30 hidden md:flex flex-col gap-4 w-9"
        x-data="{ panel: null }"
        @keydown.escape.window="panel = null"
        @click.outside="panel = null"
    >
        <x-ui.slide-out-panel key="perm" label="Permissions" accent="warning">
            @include('livewire.dashboard._permissions-body')
        </x-ui.slide-out-panel>

        <x-ui.slide-out-panel key="work" label="Work Breakdown" accent="accent">
            @include('livewire.dashboard._quick-stats-body')
        </x-ui.slide-out-panel>
    </div>

    {{-- Loading Overlay --}}
    <div
        wire:loading.flex
        wire:target="openPanel, closePanel, sort"
        class="fixed inset-0 z-40 items-center justify-center bg-base-100/50"
    >
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>
</div>
