{{--
    Mock A — "Analytics Command Center"
    Dense, data-forward layout with compact stat cards, sortable comparison table,
    dual charts (line + horizontal bar), and collapsible daily breakdown.

    Preview: /design/planner-analytics?design=a
--}}

<div class="space-y-4">

    {{-- ============================================================ --}}
    {{-- HEADER BAR --}}
    {{-- ============================================================ --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">Planner Analytics</h1>
                <p class="text-xs text-base-content/50">Week of Feb 3 &ndash; Feb 9, 2026</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <div class="join">
                <button class="btn btn-xs join-item btn-ghost">Today</button>
                <button class="btn btn-xs join-item btn-active btn-primary">This Week</button>
                <button class="btn btn-xs join-item btn-ghost">Last Week</button>
                <button class="btn btn-xs join-item btn-ghost">This Month</button>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- FILTER BAR --}}
    {{-- ============================================================ --}}
    <div class="flex flex-wrap items-center gap-2 rounded-lg bg-base-200/50 px-3 py-2">
        <select class="select select-bordered select-xs bg-base-100">
            <option selected>All Regions</option>
            <option>CENTRAL</option>
            <option>HARRISBURG</option>
            <option>LANCASTER</option>
            <option>LEHIGH</option>
            <option>NORTHEAST</option>
            <option>SUSQUEHANNA</option>
        </select>
        <select class="select select-bordered select-xs bg-base-100">
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
        <div class="divider divider-horizontal mx-0 h-6"></div>
        <div class="join">
            <button class="btn btn-xs join-item btn-active btn-primary">Daily</button>
            <button class="btn btn-xs join-item btn-ghost">Weekly</button>
            <button class="btn btn-xs join-item btn-ghost">Monthly</button>
        </div>
        <div class="join">
            <button class="btn btn-xs join-item btn-active btn-primary">Miles</button>
            <button class="btn btn-xs join-item btn-ghost">Feet</button>
        </div>
        <div class="ml-auto flex items-center gap-1">
            <button class="btn btn-ghost btn-xs" title="Refresh">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M2.985 14.652" /></svg>
            </button>
            <button class="btn btn-ghost btn-xs" title="Export CSV">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- KPI STRIP: 6 stat cards --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @php
            $stats = [
                ['label' => 'Total Miles Planned', 'value' => '42.3', 'suffix' => 'mi', 'color' => 'primary', 'icon' => 'M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z'],
                ['label' => 'Total Units', 'value' => '1,847', 'suffix' => '', 'color' => 'secondary', 'icon' => 'm21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
                ['label' => 'Active Planners', 'value' => '8', 'suffix' => '', 'color' => 'accent', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
                ['label' => 'Avg Miles/Planner/Day', 'value' => '1.06', 'suffix' => 'mi', 'color' => 'info', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
                ['label' => 'Needs Permission', 'value' => '156', 'suffix' => '', 'color' => 'warning', 'icon' => 'M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.152c-3.196 0-6.1-1.25-8.25-3.286Zm0 13.036h.008v.008H12v-.008Z'],
                ['label' => 'Stations Completed', 'value' => '3,412', 'suffix' => '', 'color' => 'success', 'icon' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
            ];
        @endphp

        @foreach ($stats as $stat)
            <div class="bg-base-100 rounded-box shadow p-3">
                <div class="flex items-center gap-2">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-{{ $stat['color'] }}/10 text-{{ $stat['color'] }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $stat['icon'] }}" /></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-bold tabular-nums leading-tight">{{ $stat['value'] }} <span class="text-xs font-normal text-base-content/50">{{ $stat['suffix'] }}</span></p>
                        <p class="text-xs text-base-content/60 truncate">{{ $stat['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ============================================================ --}}
    {{-- MAIN CONTENT: Table (2/3) + Permissions (1/3) --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        {{-- LEFT: Planner Comparison Table --}}
        <div class="lg:col-span-2 bg-base-100 rounded-box shadow">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-200">
                <h2 class="font-semibold text-sm">Planner Comparison</h2>
                <span class="text-xs text-base-content/50">Sorted by miles desc</span>
            </div>
            <div class="overflow-x-auto">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr class="text-xs">
                            <th>Planner</th>
                            <th class="text-right">Miles</th>
                            <th class="text-right">Units</th>
                            <th class="text-right">Stations</th>
                            <th class="text-right">Avg/Day</th>
                            <th class="text-right">Assess.</th>
                            <th class="text-center">Quota</th>
                            <th class="text-center">Trend</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $planners = [
                                ['name' => 'Alice Johnson', 'initials' => 'AJ', 'miles' => 8.2, 'units' => 342, 'stations' => 486, 'avg' => 1.64, 'assessments' => 3, 'quota' => 'on-track', 'trend' => [1.3,1.1,1.6,0.9,1.8,0.8,0.7]],
                                ['name' => 'Bob Martinez', 'initials' => 'BM', 'miles' => 7.1, 'units' => 298, 'stations' => 421, 'avg' => 1.42, 'assessments' => 2, 'quota' => 'on-track', 'trend' => [1.0,1.5,1.2,0.8,1.3,0.6,0.7]],
                                ['name' => 'Carol Chen', 'initials' => 'CC', 'miles' => 6.8, 'units' => 287, 'stations' => 398, 'avg' => 1.36, 'assessments' => 4, 'quota' => 'on-track', 'trend' => [0.8,1.2,1.1,1.4,1.2,0.6,0.5]],
                                ['name' => 'David Park', 'initials' => 'DP', 'miles' => 5.4, 'units' => 231, 'stations' => 312, 'avg' => 1.08, 'assessments' => 2, 'quota' => 'behind', 'trend' => [0.9,0.7,1.1,0.6,1.0,0.5,0.6]],
                                ['name' => 'Eva Williams', 'initials' => 'EW', 'miles' => 4.9, 'units' => 198, 'stations' => 276, 'avg' => 0.98, 'assessments' => 3, 'quota' => 'behind', 'trend' => [0.7,0.8,0.6,0.9,0.8,0.5,0.6]],
                                ['name' => 'Frank Lopez', 'initials' => 'FL', 'miles' => 4.2, 'units' => 187, 'stations' => 254, 'avg' => 0.84, 'assessments' => 2, 'quota' => 'behind', 'trend' => [0.5,0.6,0.7,0.8,0.7,0.5,0.4]],
                                ['name' => 'Grace Kim', 'initials' => 'GK', 'miles' => 3.8, 'units' => 164, 'stations' => 198, 'avg' => 0.76, 'assessments' => 1, 'quota' => 'behind', 'trend' => [0.4,0.6,0.5,0.7,0.6,0.5,0.5]],
                                ['name' => 'Henry Davis', 'initials' => 'HD', 'miles' => 1.9, 'units' => 140, 'stations' => 167, 'avg' => 0.38, 'assessments' => 1, 'quota' => 'behind', 'trend' => [0.2,0.3,0.4,0.3,0.2,0.3,0.2]],
                            ];
                        @endphp
                        @foreach ($planners as $p)
                            <tr class="hover cursor-pointer">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="avatar avatar-placeholder">
                                            <div class="bg-neutral text-neutral-content rounded-full w-7">
                                                <span class="text-xs">{{ $p['initials'] }}</span>
                                            </div>
                                        </div>
                                        <span class="font-medium text-sm">{{ $p['name'] }}</span>
                                    </div>
                                </td>
                                <td class="text-right font-mono text-sm font-semibold">{{ number_format($p['miles'], 1) }}</td>
                                <td class="text-right font-mono text-sm">{{ number_format($p['units']) }}</td>
                                <td class="text-right font-mono text-sm">{{ number_format($p['stations']) }}</td>
                                <td class="text-right font-mono text-sm">{{ number_format($p['avg'], 2) }}</td>
                                <td class="text-right text-sm">{{ $p['assessments'] }}</td>
                                <td class="text-center">
                                    @if ($p['quota'] === 'on-track')
                                        <span class="badge badge-success badge-sm">On Track</span>
                                    @else
                                        <span class="badge badge-warning badge-sm">Behind</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $points = collect($p['trend'])->map(function($v, $i) {
                                            return ($i * 12) . ',' . (20 - $v * 12);
                                        })->implode(' ');
                                        $trendColor = $p['quota'] === 'on-track' ? 'text-success' : 'text-warning';
                                    @endphp
                                    <svg class="inline-block {{ $trendColor }}" width="72" height="24" viewBox="0 0 72 24">
                                        <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="text-xs font-semibold">
                            <td>8 Planners</td>
                            <td class="text-right font-mono">42.3</td>
                            <td class="text-right font-mono">1,847</td>
                            <td class="text-right font-mono">3,412</td>
                            <td class="text-right font-mono">1.06</td>
                            <td class="text-right">18</td>
                            <td class="text-center text-base-content/50">3/8 on track</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- RIGHT: Permissions Summary + Weekly Targets --}}
        <div class="space-y-4">
            {{-- Permission Status --}}
            <div class="bg-base-100 rounded-box shadow p-4">
                <h3 class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-3">Permission Status</h3>
                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div class="rounded-lg bg-base-200/50 p-2 text-center">
                        <p class="text-2xl font-bold text-error tabular-nums">47</p>
                        <p class="text-xs text-base-content/50">Days Oldest</p>
                    </div>
                    <div class="rounded-lg bg-base-200/50 p-2 text-center">
                        <p class="text-2xl font-bold text-warning tabular-nums">12</p>
                        <p class="text-xs text-base-content/50">Avg Days to Perm</p>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="flex justify-between text-xs mb-1">
                        <span>Weekly clearing progress</span>
                        <span class="font-semibold">23/156</span>
                    </div>
                    <progress class="progress progress-warning w-full h-2" value="23" max="156"></progress>
                </div>

                <h3 class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-2">Oldest Unpermissioned Units</h3>
                <div class="overflow-x-auto">
                    <table class="table table-xs">
                        <thead>
                            <tr class="text-xs">
                                <th>Unit</th>
                                <th>Circuit</th>
                                <th class="text-right">Days</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $oldUnits = [
                                    ['unit' => 'SPM', 'circuit' => 'Gettysburg 34.5kV', 'days' => 47],
                                    ['unit' => 'REM612', 'circuit' => 'Carlisle 12kV', 'days' => 39],
                                    ['unit' => 'SPB', 'circuit' => 'Lancaster 69kV', 'days' => 31],
                                    ['unit' => 'BRUSH', 'circuit' => 'Allentown 12kV', 'days' => 28],
                                    ['unit' => 'MPM', 'circuit' => 'Scranton 34.5kV', 'days' => 24],
                                ];
                            @endphp
                            @foreach ($oldUnits as $u)
                                <tr>
                                    <td class="font-mono text-xs">{{ $u['unit'] }}</td>
                                    <td class="text-xs">{{ $u['circuit'] }}</td>
                                    <td class="text-right font-mono text-xs font-semibold {{ $u['days'] > 30 ? 'text-error' : 'text-warning' }}">{{ $u['days'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Weekly Targets --}}
            <div class="bg-base-100 rounded-box shadow p-4">
                <h3 class="text-xs font-semibold text-base-content/60 uppercase tracking-wider mb-3">Weekly Targets</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span>Miles (target: 52 mi)</span>
                            <span class="font-semibold">42.3 mi <span class="text-warning">(81%)</span></span>
                        </div>
                        <progress class="progress progress-primary w-full" value="42.3" max="52"></progress>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span>Stations (target: 4,000)</span>
                            <span class="font-semibold">3,412 <span class="text-warning">(85%)</span></span>
                        </div>
                        <progress class="progress progress-success w-full" value="3412" max="4000"></progress>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- CHARTS SECTION --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {{-- Daily Miles Line Chart --}}
        <div class="bg-base-100 rounded-box shadow">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-200">
                <h2 class="font-semibold text-sm">Daily Miles (All Planners)</h2>
                <span class="text-xs text-base-content/50">Last 7 days</span>
            </div>
            <div class="p-4" style="height: 280px;">
                <canvas id="dailyMilesChart"></canvas>
            </div>
        </div>

        {{-- Miles by Planner Bar Chart --}}
        <div class="bg-base-100 rounded-box shadow">
            <div class="flex items-center justify-between px-4 py-3 border-b border-base-200">
                <h2 class="font-semibold text-sm">Miles by Planner</h2>
                <div class="flex items-center gap-1 text-xs text-base-content/50">
                    <span class="inline-block w-3 h-0.5 bg-error"></span>
                    <span>6.5 mi/wk quota</span>
                </div>
            </div>
            <div class="p-4" style="height: 280px;">
                <canvas id="milesByPlannerChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- DAILY BREAKDOWN TABLE (Collapsible) --}}
    {{-- ============================================================ --}}
    <div class="bg-base-100 rounded-box shadow" x-data="{ expanded: false }">
        <button
            class="flex items-center justify-between w-full px-4 py-3 text-left hover:bg-base-200/30 transition-colors"
            @click="expanded = !expanded"
        >
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/50"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                <h2 class="font-semibold text-sm">Daily Breakdown</h2>
                <span class="badge badge-ghost badge-sm">7 days</span>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 text-base-content/50 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
        </button>
        <div x-show="expanded" x-collapse>
            <div class="overflow-x-auto border-t border-base-200">
                <table class="table table-sm table-zebra">
                    <thead>
                        <tr class="text-xs">
                            <th>Date</th>
                            <th class="text-right">Total Miles</th>
                            <th class="text-right">Total Units</th>
                            <th class="text-right">Planners Active</th>
                            <th>Top Planner</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $dailyData = [
                                ['date' => 'Mon, Feb 3', 'miles' => 5.2, 'units' => 218, 'active' => 7, 'top' => 'Alice Johnson (1.3 mi)'],
                                ['date' => 'Tue, Feb 4', 'miles' => 6.8, 'units' => 284, 'active' => 8, 'top' => 'Bob Martinez (1.5 mi)'],
                                ['date' => 'Wed, Feb 5', 'miles' => 7.1, 'units' => 296, 'active' => 8, 'top' => 'Alice Johnson (1.6 mi)'],
                                ['date' => 'Thu, Feb 6', 'miles' => 4.9, 'units' => 205, 'active' => 6, 'top' => 'Carol Chen (1.4 mi)'],
                                ['date' => 'Fri, Feb 7', 'miles' => 8.3, 'units' => 347, 'active' => 8, 'top' => 'Alice Johnson (1.8 mi)'],
                                ['date' => 'Sat, Feb 8', 'miles' => 6.1, 'units' => 312, 'active' => 5, 'top' => 'Carol Chen (2.1 mi)'],
                                ['date' => 'Sun, Feb 9', 'miles' => 3.9, 'units' => 185, 'active' => 3, 'top' => 'Bob Martinez (1.6 mi)'],
                            ];
                        @endphp
                        @foreach ($dailyData as $day)
                            <tr>
                                <td class="font-medium text-sm">{{ $day['date'] }}</td>
                                <td class="text-right font-mono text-sm">{{ number_format($day['miles'], 1) }}</td>
                                <td class="text-right font-mono text-sm">{{ number_format($day['units']) }}</td>
                                <td class="text-right text-sm"><span class="font-mono">{{ $day['active'] }}</span><span class="text-base-content/40">/8</span></td>
                                <td class="text-sm">{{ $day['top'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="font-semibold text-xs">
                            <td>Weekly Total</td>
                            <td class="text-right font-mono">42.3</td>
                            <td class="text-right font-mono">1,847</td>
                            <td class="text-right font-mono">8 unique</td>
                            <td class="text-base-content/50">Alice Johnson (3 days top)</td>
                        </tr>
                    </tfoot>
                </table>
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
            var secondary = resolveColor('text-secondary');
            var accent = resolveColor('text-accent');
            var info = resolveColor('text-info');
            var success = resolveColor('text-success');
            var warning = resolveColor('text-warning');
            var error = resolveColor('text-error');
            var baseContent = resolveColor('text-base-content');
            var muted = resolveColor('text-base-content/50');

            Chart.defaults.font.family = "system-ui, sans-serif";
            Chart.defaults.font.size = 11;
            Chart.defaults.color = muted;
            Chart.defaults.borderColor = withAlpha(baseContent, 0.08);

            // Daily Miles Line Chart
            var ctx1 = document.getElementById('dailyMilesChart');
            if (ctx1) {
                new Chart(ctx1, {
                    type: 'line',
                    data: {
                        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                        datasets: [
                            { label: 'Alice', data: [1.3,1.1,1.6,0.9,1.8,0.8,0.7], borderColor: primary, backgroundColor: withAlpha(primary, 0.15), fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2 },
                            { label: 'Bob', data: [1.0,1.5,1.2,0.8,1.3,0.6,0.7], borderColor: secondary, backgroundColor: withAlpha(secondary, 0.12), fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2 },
                            { label: 'Carol', data: [0.8,1.2,1.1,1.4,1.2,0.6,0.5], borderColor: accent, backgroundColor: withAlpha(accent, 0.1), fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2 },
                            { label: 'Others (5)', data: [2.1,3.0,3.2,1.8,4.0,4.1,2.0], borderColor: info, backgroundColor: withAlpha(info, 0.08), fill: true, tension: 0.35, pointRadius: 3, borderWidth: 2, borderDash: [4,3] },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 6, padding: 16, font: { size: 10 } } },
                            tooltip: { callbacks: { label: function(c) { return ' ' + c.dataset.label + ': ' + c.parsed.y.toFixed(1) + ' mi'; } } }
                        },
                        scales: {
                            y: { beginAtZero: true, grid: { color: withAlpha(baseContent, 0.06) }, ticks: { callback: function(v) { return v + ' mi'; } } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

            // Miles by Planner Horizontal Bar
            var ctx2 = document.getElementById('milesByPlannerChart');
            if (ctx2) {
                var names = ['Henry Davis','Grace Kim','Frank Lopez','Eva Williams','David Park','Carol Chen','Bob Martinez','Alice Johnson'];
                var miles = [1.9,3.8,4.2,4.9,5.4,6.8,7.1,8.2];
                var quota = 6.5;

                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: names,
                        datasets: [{
                            data: miles,
                            backgroundColor: miles.map(function(m) { return m >= quota ? withAlpha(success, 0.7) : withAlpha(warning, 0.7); }),
                            borderColor: miles.map(function(m) { return m >= quota ? success : warning; }),
                            borderWidth: 1,
                            borderRadius: 4,
                            barPercentage: 0.7
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: { callbacks: { label: function(c) { return ' ' + c.parsed.x.toFixed(1) + ' mi' + (c.parsed.x >= quota ? ' (on track)' : ' (behind)'); } } }
                        },
                        scales: {
                            x: { beginAtZero: true, grid: { color: withAlpha(baseContent, 0.06) }, ticks: { callback: function(v) { return v + ' mi'; } } },
                            y: { grid: { display: false } }
                        }
                    },
                    plugins: [{
                        id: 'quotaLine',
                        afterDraw: function(chart) {
                            var xScale = chart.scales.x;
                            var ctx = chart.ctx;
                            var xPos = xScale.getPixelForValue(quota);
                            ctx.save();
                            ctx.setLineDash([6, 4]);
                            ctx.strokeStyle = error;
                            ctx.lineWidth = 2;
                            ctx.beginPath();
                            ctx.moveTo(xPos, chart.scales.y.top);
                            ctx.lineTo(xPos, chart.scales.y.bottom);
                            ctx.stroke();
                            ctx.setLineDash([]);
                            ctx.fillStyle = error;
                            ctx.font = '10px system-ui, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.fillText('6.5 mi quota', xPos, chart.scales.y.top - 6);
                            ctx.restore();
                        }
                    }]
                });
            }
        });
    </script>
</div>
