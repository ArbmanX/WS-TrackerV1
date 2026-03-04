<div class="space-y-4">
    {{-- ============================================================
         MOCK DATA — Replace each block with real Livewire properties.
         All queries below should filter to:
           - Active assessments only (assessment.status = 'Active')
           - In-progress work only (completed_miles > 0 OR has activity)
           - Current contract cycle / fiscal year
         ============================================================ --}}
    @php
        // ── System-wide totals ──
        // TODO: $this->systemMetrics — query should SUM miles across all
        //       active assessments. Only include assessments where
        //       status = 'Active' within the current contract cycle.
        //       total_miles = SUM(assessment.total_miles)
        //       completed_miles = SUM(assessment.completed_miles)
        //       active_planners = COUNT(DISTINCT planner) where planner
        //                         has activity in the current week
        $totalStats = $this->systemMetrics->first() ?? [];
        $totalMiles = $totalStats['total_miles'] ?? 0;
        $completedMiles = $totalStats['completed_miles'] ?? 0;
        $overallPercent = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;

        // ── Miles by Region (status pipeline) ──
        // TODO: Replace with real query. Group by region, SUM miles in each
        //       status bucket. Only include assessments where:
        //         - assessment.status = 'Active'
        //         - assessment.completed_miles > 0 (has work started)
        //       Status buckets map to assessment workflow states:
        //         not_started, in_progress, pending_qc, rework, closed
        $milesPipelineByRegion = [
            ['region' => 'Central',      'not_started' => 42.5,  'in_progress' => 108.3, 'pending_qc' => 65.2,  'rework' => 8.1,  'closed' => 295.9],
            ['region' => 'Harrisburg',   'not_started' => 38.0,  'in_progress' => 95.7,  'pending_qc' => 48.6,  'rework' => 0,    'closed' => 227.7],
            ['region' => 'Lancaster',    'not_started' => 55.2,  'in_progress' => 72.4,  'pending_qc' => 38.1,  'rework' => 12.3, 'closed' => 202.0],
            ['region' => 'Lehigh',       'not_started' => 28.6,  'in_progress' => 88.9,  'pending_qc' => 52.0,  'rework' => 0,    'closed' => 180.5],
            ['region' => 'Distribution', 'not_started' => 15.0,  'in_progress' => 32.1,  'pending_qc' => 18.4,  'rework' => 3.5,  'closed' => 71.0],
        ];

        // ── Permission Status (system-wide) ──
        // TODO: Replace with real query. COUNT permits grouped by
        //       permission_status. Only include permits belonging to
        //       assessments where:
        //         - assessment.status = 'Active'
        //         - assessment.completed_miles > 0 (in progress)
        //       This ensures we only show permission stats for work
        //       that is actively being planned/executed.
        $permissionsSystemWide = [
            ['status' => 'Approved',     'count' => 15265],
            ['status' => 'PPL Approved', 'count' => 1085],
            ['status' => 'Pending',      'count' => 1761],
            ['status' => 'No Contact',   'count' => 375],
            ['status' => 'Refused',      'count' => 91],
            ['status' => 'Deferred',     'count' => 178],
        ];

        // ── Work Type Breakdown ──
        // TODO: Replace with real query. SUM(quantity) grouped by work_type.
        //       Only include work items from assessments where:
        //         - assessment.status = 'Active'
        //         - assessment.completed_miles > 0
        //       Units: acres for brush/herbicide types, count for removals/VPS,
        //       miles for trim types. See docs/specs/ for unit mapping.
        $workTypeBreakdown = [
            ['work_type' => 'HCB',        'label' => 'Hazard Cut Back',    'total_qty' => 29080.42],
            ['work_type' => 'HERBNA',      'label' => 'Herbicide N/A',     'total_qty' => 61020.67],
            ['work_type' => 'HERBA',       'label' => 'Herbicide Applied', 'total_qty' => 4489.32],
            ['work_type' => 'MPB',         'label' => 'Mech. Power Brush', 'total_qty' => 11021.51],
            ['work_type' => 'MPM',         'label' => 'Mech. Power Mow',   'total_qty' => 3001.92],
            ['work_type' => 'SPB',         'label' => 'Side Prune Brush',  'total_qty' => 8091.93],
            ['work_type' => 'SPM',         'label' => 'Side Prune Mech.',  'total_qty' => 5719.93],
            ['work_type' => 'BRUSHTRIM',   'label' => 'Brush Trim',        'total_qty' => 2588.36],
            ['work_type' => 'FFP-CPM',     'label' => 'FFP Cost/Mile',     'total_qty' => 30941.35],
            ['work_type' => 'REM612',      'label' => 'Removal 6-12"',     'total_qty' => 66.00],
            ['work_type' => 'REM1218',     'label' => 'Removal 12-18"',    'total_qty' => 25.00],
            ['work_type' => 'VPS',         'label' => 'Veg. Problem Spot', 'total_qty' => 49.00],
            ['work_type' => 'NW',          'label' => 'No Work',           'total_qty' => 625.00],
        ];

        // ── Summary Stats (Work Breakdown panel) ──
        // TODO: Replace with real query. All values scoped to active
        //       assessments (status = 'Active', completed_miles > 0).
        //       - total_miles / completed_miles / remaining_miles: from systemMetrics
        //       - days_remaining: calculated from contract end date (currently hardcoded June 30)
        //       - herbicide_acres: SUM(qty) WHERE work_type IN ('HERBA','HERBNA') — units: acres
        //       - hcb_acres: SUM(qty) WHERE work_type = 'HCB' — units: acres
        //       - vps_count: COUNT WHERE work_type = 'VPS' — units: each
        //       - rem_6_12_count: COUNT WHERE work_type = 'REM612' — units: each
        //       - rem_other_count: COUNT WHERE work_type IN ('REM1218','REM1824','REM24P') — units: each
        //       - bucket_trim_miles: SUM(qty) WHERE work_type = 'MPB' — units: miles
        //       - manual_trim_miles: SUM(qty) WHERE work_type = 'MPM' — units: miles
        $summaryStats = [
            'total_miles'           => 1800.0,
            'completed_miles'       => 1131.3,
            'remaining_miles'       => 668.7,
            'days_remaining'        => (int) now()->diffInDays(\Carbon\Carbon::parse('2026-06-30'), false),
            'herbicide_acres'       => 7730.0,
            'hcb_acres'             => 29080.4,
            'vps_count'             => 49,
            'rem_6_12_count'        => 66,
            'rem_other_count'       => 32,
            'bucket_trim_miles'     => 11021.5,
            'manual_trim_miles'     => 3001.9,
            'single_phase_miles'    => 1120.0,
            'multi_phase_miles'     => 680.0,
        ];

        // Derived stats (keep as-is — computed from above)
        $weeksRemaining = max(1, ceil($summaryStats['days_remaining'] / 7));
        $milesPerWeekNeeded = $summaryStats['remaining_miles'] / $weeksRemaining;
        $activePlanners = $totalStats['active_planners'] ?? 0;

        // ── Miles Burndown (sparkline) ──
        // TODO: Replace with real query against system_wide_snapshots:
        //   SELECT captured_at::date as day, MAX(completed_miles) as completed, MAX(total_miles) as total
        //   FROM system_wide_snapshots
        //   GROUP BY day ORDER BY day
        //   Remaining = total - completed for each snapshot day.
        //   Target pace line: straight line from first snapshot remaining to 0 at contract end.
        //   Contract end date currently hardcoded to 2026-06-30.
        $burndownSnapshots = [
            ['date' => '2025-10-01', 'remaining' => 2205],
            ['date' => '2025-10-15', 'remaining' => 2100],
            ['date' => '2025-11-01', 'remaining' => 1980],
            ['date' => '2025-11-15', 'remaining' => 1850],
            ['date' => '2025-12-01', 'remaining' => 1710],
            ['date' => '2025-12-15', 'remaining' => 1580],
            ['date' => '2026-01-01', 'remaining' => 1420],
            ['date' => '2026-01-15', 'remaining' => 1280],
            ['date' => '2026-02-01', 'remaining' => 1100],
            ['date' => '2026-02-13', 'remaining' => 728],
            ['date' => '2026-02-18', 'remaining' => 712],
            ['date' => '2026-02-23', 'remaining' => 695],
            ['date' => '2026-02-25', 'remaining' => 687],
            ['date' => '2026-02-28', 'remaining' => 659],
            ['date' => '2026-03-03', 'remaining' => 655],
        ];
        $contractStart = '2025-10-01';
        $contractEnd = '2026-06-30';

        // ── CTA Hub Stats (Quick Actions) ──
        // TODO: Replace with real queries. Each stat scoped to active data.

        // Planners — two views: production (active/behind) and quota (target/actual mi/wk)
        // Source: PlannerMetricsService, PlannerCareerLedgerService
        $ctaPlanners = [
            'production' => ['active' => $activePlanners ?: 12, 'behind_quota' => 3],
            'quota'      => ['target_mi_wk' => 42.0, 'actual_mi_wk' => $milesPerWeekNeeded],
        ];

        // Assessments — per-region assessment counts (active, in_qc, rework)
        // Source: Assessment model grouped by region, status
        $ctaAssessmentsByRegion = [
            'all'          => ['active' => 247, 'in_qc' => 48, 'rework' => 23],
            'Central'      => ['active' => 62,  'in_qc' => 14, 'rework' => 5],
            'Harrisburg'   => ['active' => 55,  'in_qc' => 11, 'rework' => 0],
            'Lancaster'    => ['active' => 48,  'in_qc' => 9,  'rework' => 8],
            'Lehigh'       => ['active' => 52,  'in_qc' => 10, 'rework' => 7],
            'Distribution' => ['active' => 30,  'in_qc' => 4,  'rework' => 3],
        ];

        // Admin — alerts and system health
        // Source: AlertService, PlannerMetricsService
        $ctaAdmin = [
            'alerts' => 3,
            'stale_planners' => 2,
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
                    @foreach(['all' => 'All', 'Central' => 'Central', 'Harrisburg' => 'Hbg', 'Lancaster' => 'Lanc', 'Lehigh' => 'Lehigh', 'Distribution' => 'Dist'] as $key => $label)
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
