{{--
    Mock B — "Clean Dashboard"
    Spacious, card-focused layout with prominent hero chart, planner cards grid,
    and permission aging visualization. Generous whitespace throughout.

    Preview: /design/planner-analytics?design=b
--}}

<div class="space-y-8">

    {{-- ============================================================ --}}
    {{-- HEADER --}}
    {{-- ============================================================ --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Planner Analytics</h1>
            <p class="mt-1 text-base-content/60">
                Week of Jan 6 &ndash; 12, 2026 &bull; 8 Active Planners
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <button class="btn btn-primary btn-sm rounded-full">This Week</button>
            <button class="btn btn-ghost btn-sm rounded-full">Last Week</button>
            <button class="btn btn-ghost btn-sm rounded-full">This Month</button>
            <button class="btn btn-ghost btn-sm rounded-full">Custom Range</button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- FILTER ROW --}}
    {{-- ============================================================ --}}
    <div class="flex flex-wrap items-center gap-3 rounded-2xl bg-base-200/60 px-4 py-3">
        <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/50"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
            <input type="date" value="2026-01-06" class="input input-bordered input-sm bg-base-100">
            <span class="text-base-content/40">to</span>
            <input type="date" value="2026-01-12" class="input input-bordered input-sm bg-base-100">
        </div>
        <div class="divider divider-horizontal mx-0 h-6"></div>
        <select class="select select-bordered select-sm bg-base-100">
            <option selected>All Regions</option>
            <option>CENTRAL</option>
            <option>HARRISBURG</option>
            <option>LANCASTER</option>
            <option>LEHIGH</option>
            <option>NORTHEAST</option>
            <option>SUSQUEHANNA</option>
        </select>
        <select class="select select-bordered select-sm bg-base-100">
            <option selected>All Planners</option>
            <option>Alice Johnson</option>
            <option>Bob Martinez</option>
            <option>Carol Chen</option>
            <option>David Park</option>
            <option>Eva Williams</option>
            <option>Frank Lopez</option>
            <option>Grace Kim</option>
            <option>Henry Davis</option>
        </select>
        <button class="btn btn-ghost btn-sm ml-auto">Reset Filters</button>
    </div>

    {{-- ============================================================ --}}
    {{-- HERO CHART: Weekly Planning Trends --}}
    {{-- ============================================================ --}}
    <div class="card rounded-2xl bg-base-100 shadow">
        <div class="card-body p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold">Weekly Planning Trends</h2>
                    <p class="text-sm text-base-content/60">Daily miles across 4 weeks</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-base-content/50">
                    <span class="inline-block w-6 border-t-2 border-dashed border-warning"></span>
                    <span>6.5 mi/week quota</span>
                </div>
            </div>
            <div style="height: 320px;">
                <canvas id="heroTrendChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- THREE KPI CARDS --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">

        {{-- Card 1: This Week --}}
        <div class="card rounded-2xl bg-base-100 shadow">
            <div class="card-body items-center text-center">
                <div
                    class="radial-progress text-primary"
                    style="--value:65; --size:7rem; --thickness:6px;"
                    role="progressbar"
                >
                    <span class="text-2xl font-bold text-base-content">65%</span>
                </div>
                <h3 class="mt-3 text-sm font-medium text-base-content/60">This Week</h3>
                <p class="text-4xl font-extrabold tracking-tight">42.3 <span class="text-lg font-normal text-base-content/50">mi</span></p>
                <p class="text-sm text-base-content/60">1,847 units across 3,412 stations</p>
                <div class="badge badge-primary badge-outline mt-2">Week quota: 65% complete</div>
            </div>
        </div>

        {{-- Card 2: Permissions --}}
        <div class="card rounded-2xl bg-base-100 shadow">
            <div class="card-body items-center text-center">
                <h3 class="text-sm font-medium text-base-content/60">Permissions</h3>
                <p class="text-4xl font-extrabold tracking-tight">156 <span class="text-lg font-normal text-base-content/50">units</span></p>
                <p class="text-sm text-base-content/60">Needing permission &bull; Oldest: <span class="font-semibold text-error">47 days</span></p>
                <div class="mx-auto mt-3" style="width: 140px; height: 140px;">
                    <canvas id="permissionDonut"></canvas>
                </div>
                <div class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-base-content/60">
                    <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-success"></span> Granted 64</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-warning"></span> Pending 58</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2 rounded-full bg-error"></span> Denied 34</span>
                </div>
            </div>
        </div>

        {{-- Card 3: Top Performer --}}
        <div class="card rounded-2xl bg-base-100 shadow">
            <div class="card-body items-center text-center">
                <div class="avatar avatar-placeholder">
                    <div class="w-16 rounded-full bg-primary text-primary-content">
                        <span class="text-xl font-bold">AJ</span>
                    </div>
                </div>
                <h3 class="mt-2 text-lg font-semibold">Alice Johnson</h3>
                <p class="text-sm text-base-content/60">Top Performer This Week</p>
                <p class="text-3xl font-extrabold tracking-tight text-primary">8.2 <span class="text-base font-normal text-base-content/50">mi</span></p>

                <div class="mt-3 w-full space-y-1">
                    <div class="flex justify-between text-xs text-base-content/60">
                        <span>Team average</span>
                        <span>5.3 mi</span>
                    </div>
                    <div class="relative h-3 w-full overflow-hidden rounded-full bg-base-200">
                        <div class="absolute inset-y-0 left-0 rounded-full bg-base-300" style="width: 64%;"></div>
                        <div class="absolute inset-y-0 left-0 rounded-full bg-primary/70" style="width: 100%;"></div>
                    </div>
                    <div class="flex justify-between text-xs">
                        <span class="font-medium text-primary">Alice: 8.2 mi</span>
                        <span class="text-success">+55% above avg</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- PLANNER CARDS GRID --}}
    {{-- ============================================================ --}}
    <div>
        <h2 class="mb-4 text-xl font-semibold">Planners</h2>

        @php
            $planners = [
                ['name' => 'Alice Johnson', 'initials' => 'AJ', 'miles' => 8.2, 'units' => 342, 'assessments' => 3, 'quota' => 'on-track', 'regions' => 'CENTRAL, LEHIGH', 'progress' => 100],
                ['name' => 'Bob Martinez', 'initials' => 'BM', 'miles' => 7.1, 'units' => 298, 'assessments' => 2, 'quota' => 'on-track', 'regions' => 'HARRISBURG', 'progress' => 87],
                ['name' => 'Carol Chen', 'initials' => 'CC', 'miles' => 6.8, 'units' => 287, 'assessments' => 4, 'quota' => 'on-track', 'regions' => 'LANCASTER, NORTHEAST', 'progress' => 83],
                ['name' => 'David Park', 'initials' => 'DP', 'miles' => 5.4, 'units' => 231, 'assessments' => 2, 'quota' => 'behind', 'regions' => 'SUSQUEHANNA', 'progress' => 66],
                ['name' => 'Eva Williams', 'initials' => 'EW', 'miles' => 4.9, 'units' => 198, 'assessments' => 3, 'quota' => 'behind', 'regions' => 'CENTRAL', 'progress' => 60],
                ['name' => 'Frank Lopez', 'initials' => 'FL', 'miles' => 4.2, 'units' => 187, 'assessments' => 2, 'quota' => 'behind', 'regions' => 'LEHIGH, HARRISBURG', 'progress' => 51],
                ['name' => 'Grace Kim', 'initials' => 'GK', 'miles' => 3.8, 'units' => 164, 'assessments' => 1, 'quota' => 'behind', 'regions' => 'LANCASTER', 'progress' => 46],
                ['name' => 'Henry Davis', 'initials' => 'HD', 'miles' => 1.9, 'units' => 140, 'assessments' => 1, 'quota' => 'behind', 'regions' => 'NORTHEAST', 'progress' => 23],
            ];
        @endphp

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($planners as $p)
                @php
                    $quotaBadge = $p['quota'] === 'on-track' ? 'badge-success' : 'badge-warning';
                    $progressColor = $p['quota'] === 'on-track' ? 'progress-success' : 'progress-warning';
                @endphp
                <div class="card rounded-2xl bg-base-100 shadow transition-shadow hover:shadow-lg cursor-pointer group">
                    <div class="card-body p-4 gap-3">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-placeholder">
                                <div class="w-10 rounded-full bg-base-200 text-base-content/70">
                                    <span class="text-sm font-semibold">{{ $p['initials'] }}</span>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate font-semibold">{{ $p['name'] }}</h3>
                                <span class="badge {{ $quotaBadge }} badge-sm">{{ $p['quota'] }}</span>
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0 text-base-content/30 transition-transform group-hover:translate-x-0.5 group-hover:text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </div>

                        <div class="flex items-baseline gap-2">
                            <span class="badge badge-primary badge-lg font-bold">{{ $p['miles'] }} mi</span>
                            <span class="text-sm text-base-content/60">{{ number_format($p['units']) }} units</span>
                        </div>

                        <div class="space-y-1">
                            <div class="flex justify-between text-xs text-base-content/60">
                                <span>Weekly quota</span>
                                <span>{{ $p['progress'] }}%</span>
                            </div>
                            <progress class="progress {{ $progressColor }} h-2 w-full" value="{{ $p['progress'] }}" max="100"></progress>
                        </div>

                        <p class="text-xs text-base-content/50">
                            {{ $p['assessments'] }} assessments &bull; {{ $p['regions'] }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- BOTTOM: Daily Activity + Permission Aging --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Daily Activity Table --}}
        <div class="card rounded-2xl bg-base-100 shadow">
            <div class="card-body p-6">
                <h2 class="text-lg font-semibold">Daily Activity</h2>
                <p class="mb-3 text-sm text-base-content/60">This week, day by day</p>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr class="text-base-content/60">
                                <th>Date</th>
                                <th class="text-right">Miles</th>
                                <th class="text-right">Units</th>
                                <th>Top Planner</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $daily = [
                                    ['date' => 'Mon, Jan 6', 'miles' => 5.1, 'units' => 218, 'top' => 'Carol Chen'],
                                    ['date' => 'Tue, Jan 7', 'miles' => 6.3, 'units' => 274, 'top' => 'Alice Johnson'],
                                    ['date' => 'Wed, Jan 8', 'miles' => 7.4, 'units' => 312, 'top' => 'Bob Martinez'],
                                    ['date' => 'Thu, Jan 9', 'miles' => 8.1, 'units' => 347, 'top' => 'Alice Johnson'],
                                    ['date' => 'Fri, Jan 10', 'miles' => 9.2, 'units' => 398, 'top' => 'Carol Chen'],
                                    ['date' => 'Sat, Jan 11', 'miles' => 4.4, 'units' => 192, 'top' => 'Bob Martinez'],
                                    ['date' => 'Sun, Jan 12', 'miles' => 1.8, 'units' => 106, 'top' => 'David Park'],
                                ];
                            @endphp
                            @foreach ($daily as $day)
                                <tr class="hover">
                                    <td class="font-medium">{{ $day['date'] }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($day['miles'], 1) }}</td>
                                    <td class="text-right tabular-nums">{{ number_format($day['units']) }}</td>
                                    <td class="text-sm text-base-content/70">{{ $day['top'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td>Total</td>
                                <td class="text-right tabular-nums">42.3</td>
                                <td class="text-right tabular-nums">1,847</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Permission Aging Chart --}}
        <div class="card rounded-2xl bg-base-100 shadow">
            <div class="card-body p-6">
                <h2 class="text-lg font-semibold">Permission Aging</h2>
                <p class="mb-3 text-sm text-base-content/60">Units by days awaiting permission</p>
                <div style="height: 280px;">
                    <canvas id="permissionAgingChart"></canvas>
                </div>
                <div class="mt-2 flex flex-wrap justify-center gap-x-4 gap-y-1 text-xs text-base-content/60">
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded bg-success"></span> 0&ndash;7 days</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded bg-info"></span> 8&ndash;14 days</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded bg-warning"></span> 15&ndash;30 days</span>
                    <span class="flex items-center gap-1"><span class="inline-block size-2.5 rounded bg-error"></span> 30+ days</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- Design Switcher --}}
    {{-- ============================================================ --}}
    <div class="flex justify-center py-4">
        <div class="join">
            <a href="?design=a" class="btn btn-sm join-item {{ $this->design === 'a' ? 'btn-primary' : 'btn-ghost' }}">Option A: Command Center</a>
            <a href="?design=b" class="btn btn-sm join-item {{ $this->design === 'b' ? 'btn-primary' : 'btn-ghost' }}">Option B: Clean Dashboard</a>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- CHART.JS --}}
    {{-- ============================================================ --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function resolveColor(className) {
                var el = document.createElement('div');
                el.className = className;
                el.style.display = 'none';
                document.body.appendChild(el);
                var color = getComputedStyle(el).color;
                document.body.removeChild(el);
                return color;
            }

            function withAlpha(color, alpha) {
                if (color.startsWith('rgb(')) return color.replace('rgb(', 'rgba(').replace(')', ', ' + alpha + ')');
                if (color.startsWith('oklch(')) return color.replace(')', ' / ' + alpha + ')');
                return color;
            }

            var primary = resolveColor('text-primary');
            var success = resolveColor('text-success');
            var warning = resolveColor('text-warning');
            var error = resolveColor('text-error');
            var info = resolveColor('text-info');
            var baseContent = resolveColor('text-base-content');
            var gridColor = withAlpha(baseContent, 0.1);

            Chart.defaults.font.family = "system-ui, sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = resolveColor('text-base-content/50');
            Chart.defaults.borderColor = withAlpha(baseContent, 0.08);

            // Hero Trend Chart (4 weeks)
            var heroCtx = document.getElementById('heroTrendChart');
            if (heroCtx) {
                new Chart(heroCtx, {
                    type: 'line',
                    data: {
                        labels: [
                            'Mon W1','Tue','Wed','Thu','Fri','Sat','Sun',
                            'Mon W2','Tue','Wed','Thu','Fri','Sat','Sun',
                            'Mon W3','Tue','Wed','Thu','Fri','Sat','Sun',
                            'Mon W4','Tue','Wed','Thu','Fri','Sat','Sun'
                        ],
                        datasets: [{
                            label: 'Daily Miles',
                            data: [
                                4.1,5.8,6.2,3.9,7.1,5.5,2.8,
                                5.2,6.8,7.1,4.9,8.3,6.1,3.9,
                                4.8,5.5,6.9,5.2,7.8,5.9,4.1,
                                5.1,6.3,7.4,null,null,null,null
                            ],
                            borderColor: primary,
                            backgroundColor: withAlpha(primary, 0.1),
                            fill: true,
                            tension: 0.35,
                            pointRadius: 3,
                            pointHoverRadius: 6,
                            borderWidth: 2.5,
                            spanGaps: false
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { intersect: false, mode: 'index' },
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function(c) { return c.parsed.y !== null ? c.parsed.y.toFixed(1) + ' mi' : ''; } } }
                        },
                        scales: {
                            x: { grid: { color: gridColor }, ticks: { maxRotation: 45, font: { size: 10 } } },
                            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { callback: function(v) { return v + ' mi'; } } }
                        }
                    },
                    plugins: [{
                        id: 'quotaLine',
                        afterDraw: function(chart) {
                            var yScale = chart.scales.y;
                            var ctx = chart.ctx;
                            var yPos = yScale.getPixelForValue(6.5);
                            ctx.save();
                            ctx.setLineDash([6, 4]);
                            ctx.strokeStyle = warning;
                            ctx.lineWidth = 2;
                            ctx.beginPath();
                            ctx.moveTo(chart.chartArea.left, yPos);
                            ctx.lineTo(chart.chartArea.right, yPos);
                            ctx.stroke();
                            ctx.restore();
                            ctx.save();
                            ctx.fillStyle = warning;
                            ctx.font = '11px system-ui, sans-serif';
                            ctx.textAlign = 'right';
                            ctx.fillText('6.5 mi quota', chart.chartArea.right - 4, yPos - 6);
                            ctx.restore();
                        }
                    }]
                });
            }

            // Permission Donut
            var donutCtx = document.getElementById('permissionDonut');
            if (donutCtx) {
                new Chart(donutCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Granted', 'Pending', 'Denied'],
                        datasets: [{ data: [64, 58, 34], backgroundColor: [success, warning, error], borderWidth: 0, hoverOffset: 6 }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, cutout: '65%', plugins: { legend: { display: false } } }
                });
            }

            // Permission Aging Bar
            var agingCtx = document.getElementById('permissionAgingChart');
            if (agingCtx) {
                new Chart(agingCtx, {
                    type: 'bar',
                    data: {
                        labels: ['0-7 days', '8-14 days', '15-30 days', '30+ days'],
                        datasets: [{
                            label: 'Units',
                            data: [42, 38, 51, 25],
                            backgroundColor: [success, info, warning, error],
                            borderRadius: 6,
                            borderSkipped: false,
                            barThickness: 36
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(c) { return c.parsed.x + ' units'; } } } },
                        scales: {
                            x: { beginAtZero: true, grid: { color: gridColor } },
                            y: { grid: { display: false }, ticks: { font: { size: 12, weight: '500' } } }
                        }
                    }
                });
            }
        });
    </script>
</div>
