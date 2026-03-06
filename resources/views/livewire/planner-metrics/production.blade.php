<div class="space-y-4">
    @php
        $chartData = $this->dailyData;
        $summary = $this->summaryTable;
        $planners = $this->availablePlanners;
    @endphp

    {{-- Header --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Production</h1>
            <p class="text-sm text-base-content/50">Individual planner output by day</p>
        </div>
        <a href="{{ route('planner-metrics.overview') }}" wire:navigate class="btn btn-sm btn-ghost gap-1.5 text-base-content/60">
            <x-heroicon-m-arrow-left class="size-4" />
            Back to Overview
        </a>
    </div>

    {{-- Controls Row --}}
    <div class="card bg-base-100 border-l-[3px] border-l-primary shadow-md">
        <div class="bg-base-200/80 px-4 py-2">
            <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Filters</h3>
        </div>
        <div class="p-3 sm:p-4">
            <div class="flex flex-wrap items-end gap-3" x-data>
                {{-- Date From --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">From</label>
                    <input
                        type="date"
                        wire:model.live.debounce.500ms="from"
                        class="input input-sm input-bordered font-code text-sm w-36"
                    />
                </div>

                {{-- Date To --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">To</label>
                    <input
                        type="date"
                        wire:model.live.debounce.500ms="to"
                        class="input input-sm input-bordered font-code text-sm w-36"
                    />
                </div>

                {{-- Planner Filter --}}
                <div class="flex flex-col gap-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">Planner</label>
                    <select wire:model.live="planner" class="select select-sm select-bordered text-sm min-w-40">
                        <option value="all">All Planners</option>
                        @foreach($planners as $p)
                            <option value="{{ $p['value'] }}">{{ $p['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Chart type toggle --}}
                <div class="flex flex-col gap-1 ml-auto">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-base-content/50">View</label>
                    <div class="join" x-data="{ chartType: 'bar' }" x-ref="toggleWrap">
                        <button
                            class="btn btn-sm join-item gap-1"
                            :class="chartType === 'line' ? 'btn-active' : ''"
                            @click="chartType = 'line'; $dispatch('chart-type-changed', { type: 'line' })"
                        >Line</button>
                        <button
                            class="btn btn-sm join-item gap-1"
                            :class="chartType === 'bar' ? 'btn-active' : ''"
                            @click="chartType = 'bar'; $dispatch('chart-type-changed', { type: 'bar' })"
                        >Bar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart + Scope Year Side by Side --}}
    @php $topPlanners = $this->scopeYearTopPlanners; @endphp
    <div class="flex gap-4 items-stretch">
        {{-- Main Chart --}}
        <div class="flex-1 min-w-0">
            @if(count($chartData['series']) > 0)
                <div
                    class="card bg-base-100 border-l-[3px] border-l-secondary shadow-md h-full"
                    x-data="{
                        chart: null,
                        chartType: 'bar',
                        dates: @js($chartData['dates']),
                        series: @js($chartData['series']),
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
                        colorPalette() {
                            return [
                                this.resolveHex('--color-primary'),
                                this.resolveHex('--color-secondary'),
                                this.resolveHex('--color-accent'),
                                this.resolveHex('--color-info'),
                                this.resolveHex('--color-success'),
                                this.resolveHex('--color-warning'),
                                this.resolveHex('--color-error'),
                                this.toRgba(this.resolveHex('--color-base-content'), 0.6),
                                this.toRgba(this.resolveHex('--color-primary'), 0.5),
                                this.toRgba(this.resolveHex('--color-secondary'), 0.5),
                            ];
                        },
                        buildChart() {
                            if (this.chart) this.chart.destroy();
                            const colors = this.colorPalette();
                            const textColor = this.resolveHex('--color-base-content');
                            const isBar = this.chartType === 'bar';

                            const labels = this.dates.map(d => {
                                const dt = new Date(d + 'T12:00:00');
                                return dt.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
                            });

                            const datasets = this.series.map((s, i) => {
                                const color = colors[i % colors.length];
                                if (isBar) {
                                    return {
                                        label: s.display_name,
                                        data: s.data,
                                        backgroundColor: color,
                                        borderRadius: 2,
                                    };
                                } else {
                                    return {
                                        label: s.display_name,
                                        data: s.data,
                                        borderColor: color,
                                        backgroundColor: 'transparent',
                                        borderWidth: 2,
                                        tension: 0.3,
                                        pointRadius: 3,
                                        pointHoverRadius: 5,
                                        pointBackgroundColor: color,
                                    };
                                }
                            });

                            this.chart = new Chart(this.$refs.canvas, {
                                type: isBar ? 'bar' : 'line',
                                data: { labels, datasets },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    interaction: {
                                        mode: isBar ? 'index' : 'nearest',
                                        intersect: !isBar,
                                    },
                                    scales: {
                                        x: {
                                            stacked: isBar,
                                            grid: { display: false },
                                            border: { display: false },
                                            ticks: {
                                                font: { size: 10, family: 'Fira Code, monospace' },
                                                color: this.toRgba(textColor, 0.4),
                                                maxRotation: 45,
                                            },
                                        },
                                        y: {
                                            stacked: isBar,
                                            beginAtZero: true,
                                            grid: { color: this.toRgba(textColor, 0.06) },
                                            border: { display: false },
                                            title: {
                                                display: true,
                                                text: 'Miles',
                                                font: { size: 11 },
                                                color: this.toRgba(textColor, 0.5),
                                            },
                                            ticks: {
                                                font: { size: 10, family: 'Fira Code, monospace' },
                                                color: this.toRgba(textColor, 0.4),
                                            },
                                        },
                                    },
                                    plugins: {
                                        legend: {
                                            position: 'top',
                                            labels: {
                                                usePointStyle: true,
                                                pointStyle: 'circle',
                                                boxWidth: 8,
                                                boxHeight: 8,
                                                padding: 16,
                                                font: { size: 11 },
                                                color: this.toRgba(textColor, 0.7),
                                            },
                                        },
                                        tooltip: {
                                            titleFont: { family: 'Fira Code, monospace', size: 11 },
                                            bodyFont: { family: 'Fira Code, monospace', size: 10 },
                                            callbacks: {
                                                label: ctx => ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(1) + ' mi',
                                            },
                                        },
                                    },
                                },
                            });
                        },
                        init() {
                            requestAnimationFrame(() => this.buildChart());
                        },
                        destroy() {
                            this.chart?.destroy();
                        }
                    }"
                    x-on:destroy="destroy()"
                    @chart-type-changed.window="chartType = $event.detail.type; buildChart()"
                >
                    <div class="bg-base-200/80 px-4 py-2">
                        <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Daily Output</h3>
                    </div>
                    <div class="p-3 sm:p-4">
                        <div class="h-48 sm:h-56">
                            <canvas x-ref="canvas"></canvas>
                        </div>
                    </div>
                </div>
            @else
                <div class="card bg-base-100 border-l-[3px] border-l-secondary shadow-md h-full">
                    <div class="card-body items-center text-center py-12">
                        <x-heroicon-o-chart-bar class="size-10 text-base-content/20 mb-2" />
                        <p class="text-sm text-base-content/50">No production data for this date range.</p>
                    </div>
                </div>
            @endif
        </div>

        {{-- Scope Year Top Contributors --}}
        @if(count($topPlanners) > 0)
            @php
                $maxMiles = max(array_column($topPlanners, 'total_miles')) ?: 1;
                $barColors = ['bg-error', 'bg-info', 'bg-warning', 'bg-success'];
            @endphp
            <div class="card bg-base-100 border-l-[3px] border-l-accent shadow-md w-64 shrink-0 hidden lg:flex">
                <div class="bg-base-200/80 px-4 py-2">
                    <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Scope Year Top Contributors</h3>
                </div>
                <div class="p-4 flex-1 flex flex-col justify-center gap-3">
                    @foreach($topPlanners as $i => $tp)
                        @php
                            $widthPct = $maxMiles > 0 ? round(($tp['total_miles'] / $maxMiles) * 100) : 0;
                            $barColor = $barColors[$i] ?? 'bg-primary';
                        @endphp
                        <div>
                            <span class="text-xs font-semibold truncate block mb-1">{{ $tp['display_name'] }}</span>
                            <div class="w-full bg-base-300/20 rounded-full h-1.5 overflow-hidden">
                                <div
                                    class="{{ $barColor }} h-full rounded-full transition-all duration-500 ease-out"
                                    style="width: {{ $widthPct }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Production Summary Table --}}
    @if(count($summary) > 0)
        <div class="card bg-base-100 border-l-[3px] border-l-info shadow-md">
            <div class="bg-base-200/80 px-4 py-2">
                <h3 class="text-xs font-bold uppercase tracking-[0.15em] text-base-content/75">Production Summary</h3>
            </div>
            <div class="p-4 sm:p-5">
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wider text-base-content/50">
                                <th class="font-bold">Planner</th>
                                <th class="font-bold text-right">Total Miles</th>
                                <th class="font-bold text-right">Avg/Day</th>
                                <th class="font-bold text-right">Peak Day</th>
                                <th class="font-bold text-right">Active Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($summary as $row)
                                <tr class="border-base-content/5">
                                    <td class="font-semibold text-sm">{{ $row['display_name'] }}</td>
                                    <td class="text-right font-code tabular-nums text-sm">{{ number_format($row['total_miles'], 1) }} mi</td>
                                    <td class="text-right font-code tabular-nums text-sm">{{ number_format($row['avg_per_day'], 2) }} mi</td>
                                    <td class="text-right font-code tabular-nums text-sm">
                                        {{ number_format($row['peak_miles'], 1) }} mi
                                        @if($row['peak_date'])
                                            <span class="text-base-content/40 text-xs">({{ \Carbon\Carbon::parse($row['peak_date'])->format('Y-m-d') }})</span>
                                        @endif
                                    </td>
                                    <td class="text-right font-code tabular-nums text-sm">{{ $row['active_days'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Loading Overlay --}}
    <div
        wire:loading.flex
        wire:target="from, to, planner"
        class="fixed inset-0 z-40 items-center justify-center bg-base-100/50"
    >
        <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>
</div>
