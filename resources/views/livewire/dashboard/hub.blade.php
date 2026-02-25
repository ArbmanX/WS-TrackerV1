@php
    $sys = $this->systemMetrics;
    $totalMiles = $sys['total_miles'] ?? 0;
    $completedMiles = $sys['completed_miles'] ?? 0;
    $overallPercent = $totalMiles > 0 ? round(($completedMiles / $totalMiles) * 100) : 0;
    $pipeline = $this->assessmentPipeline;
    $planners = $this->plannerSnapshot;
    $alerts = $this->alerts;
    $alertTotal = $alerts['ghost_count'] + $alerts['stale_planner_count'];
@endphp

<x-hub.layout title="Dashboard" subtitle="System overview and quick navigation">
    {{-- Stat Cards --}}
    <x-slot:stats>
        <x-ui.stat-card
            label="Total Circuits"
            :value="number_format($sys['total_assessments'] ?? 0)"
            icon="bolt"
            color="primary"
            size="sm"
        />

        <x-ui.stat-card
            label="Miles Complete"
            :value="number_format($completedMiles, 0)"
            suffix="/ {{ number_format($totalMiles, 0) }} mi"
            icon="map"
            size="sm"
        />

        <x-ui.stat-card
            label="Overall Progress"
            :value="number_format($overallPercent, 0)"
            suffix="%"
            icon="chart-bar"
            :color="$overallPercent >= 75 ? 'success' : ($overallPercent >= 50 ? 'warning' : 'primary')"
            size="sm"
        />

        <x-ui.stat-card
            label="Active Planners"
            :value="number_format($sys['active_planners'] ?? 0)"
            icon="users"
            size="sm"
        />
    </x-slot:stats>

    {{-- Card Grid (2-col via hub.layout) --}}

    {{-- 1. Planner Snapshot --}}
    <x-hub.card
        title="Planner Snapshot"
        summary="Quota progress across the team"
        icon="users"
        href="{{ route('planner-metrics.overview') }}"
        color="primary"
        :metric="$planners['total']"
        metricLabel="planners"
    >
        <div class="space-y-3">
            {{-- Status breakdown --}}
            <div class="flex gap-2">
                <div class="flex-1 rounded-lg bg-success/10 px-3 py-2 text-center">
                    <div class="text-lg font-bold tabular-nums text-success">{{ $planners['on_track'] }}</div>
                    <div class="text-xs text-base-content/60">on track</div>
                </div>
                <div class="flex-1 rounded-lg bg-warning/10 px-3 py-2 text-center">
                    <div class="text-lg font-bold tabular-nums text-warning">{{ $planners['warning'] }}</div>
                    <div class="text-xs text-base-content/60">at risk</div>
                </div>
                <div class="flex-1 rounded-lg bg-error/10 px-3 py-2 text-center">
                    <div class="text-lg font-bold tabular-nums text-error">{{ $planners['behind_quota'] }}</div>
                    <div class="text-xs text-base-content/60">behind</div>
                </div>
            </div>

            {{-- Visual ratio bar --}}
            @if($planners['total'] > 0)
                <div class="flex h-2 w-full overflow-hidden rounded-full bg-base-300">
                    @if($planners['on_track'] > 0)
                        <div class="bg-success" style="width: {{ ($planners['on_track'] / $planners['total']) * 100 }}%"></div>
                    @endif
                    @if($planners['warning'] > 0)
                        <div class="bg-warning" style="width: {{ ($planners['warning'] / $planners['total']) * 100 }}%"></div>
                    @endif
                    @if($planners['behind_quota'] > 0)
                        <div class="bg-error" style="width: {{ ($planners['behind_quota'] / $planners['total']) * 100 }}%"></div>
                    @endif
                </div>
            @endif
        </div>
    </x-hub.card>

    {{-- 2. Assessment Pipeline --}}
    <x-hub.card
        title="Assessment Pipeline"
        summary="Circuit status distribution"
        icon="clipboard-document-list"
        color="secondary"
        :metric="$pipeline['total']"
        metricLabel="total"
        :disabled="!Route::has('assessments.index')"
        :href="Route::has('assessments.index') ? route('assessments.index') : '#'"
    >
        <div class="space-y-2">
            @php
                $stages = [
                    ['label' => 'Active', 'count' => $pipeline['active'], 'color' => 'primary'],
                    ['label' => 'QC', 'count' => $pipeline['qc'], 'color' => 'info'],
                    ['label' => 'Rework', 'count' => $pipeline['rework'], 'color' => 'warning'],
                    ['label' => 'Closed', 'count' => $pipeline['closed'], 'color' => 'success'],
                ];
            @endphp

            @foreach($stages as $stage)
                <div class="flex items-center gap-3">
                    <span class="w-16 text-xs text-base-content/60">{{ $stage['label'] }}</span>
                    <div class="flex-1">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-base-300">
                            <div
                                class="h-full rounded-full bg-{{ $stage['color'] }} transition-all duration-500"
                                style="width: {{ $pipeline['total'] > 0 ? ($stage['count'] / $pipeline['total']) * 100 : 0 }}%"
                            ></div>
                        </div>
                    </div>
                    <span class="w-10 text-right text-sm font-semibold tabular-nums">{{ number_format($stage['count']) }}</span>
                </div>
            @endforeach
        </div>
    </x-hub.card>

    {{-- 3. Regional Summary --}}
    <x-hub.card
        title="Regional Summary"
        summary="{{ $this->regionalMetrics->count() }} regions tracked"
        icon="map-pin"
        :disabled="!Route::has('assessments.index')"
        :href="Route::has('assessments.index') ? route('assessments.index') : '#'"
        color="accent"
        :metric="$this->regionalMetrics->count()"
        metricLabel="regions"
    >
        <div class="space-y-2.5">
            @foreach($this->regionalMetrics as $region)
                @php
                    $rTotal = $region['Total_Miles'] ?? 0;
                    $rDone = $region['Completed_Miles'] ?? 0;
                    $rPct = $rTotal > 0 ? round(($rDone / $rTotal) * 100) : 0;
                    $rColor = $rPct >= 75 ? 'success' : ($rPct >= 50 ? 'warning' : 'primary');
                @endphp
                <div class="flex items-center gap-3">
                    <span class="w-24 truncate text-sm font-medium">{{ $region['Region'] ?? 'Unknown' }}</span>
                    <div class="flex-1">
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-base-300">
                            <div
                                class="h-full rounded-full bg-{{ $rColor }} transition-all duration-500"
                                style="width: {{ $rPct }}%"
                            ></div>
                        </div>
                    </div>
                    <span class="w-10 text-right text-xs font-semibold tabular-nums text-{{ $rColor }}">{{ $rPct }}%</span>
                </div>
            @endforeach

            @if($this->regionalMetrics->isEmpty())
                <p class="text-sm text-base-content/40 text-center py-2">No regional data available</p>
            @endif
        </div>
    </x-hub.card>

    {{-- 4. Scope Year Progress --}}
    <x-hub.card
        title="Scope Year Progress"
        summary="Completion by assessment year"
        icon="calendar-days"
        :disabled="!Route::has('assessments.index')"
        :href="Route::has('assessments.index') ? route('assessments.index') : '#'"
        color="info"
    >
        <div class="space-y-3">
            @forelse($this->scopeYearProgress as $sy)
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium">{{ $sy['year'] }}</span>
                        <span class="text-xs text-base-content/60">
                            {{ number_format($sy['closed']) }} / {{ number_format($sy['total']) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 h-2 overflow-hidden rounded-full bg-base-300">
                            <div
                                class="h-full rounded-full bg-info transition-all duration-500"
                                style="width: {{ $sy['percent'] }}%"
                            ></div>
                        </div>
                        <span class="w-10 text-right text-xs font-bold tabular-nums">{{ $sy['percent'] }}%</span>
                    </div>
                </div>
            @empty
                <p class="text-sm text-base-content/40 text-center py-2">No scope year data</p>
            @endforelse
        </div>
    </x-hub.card>

    {{-- 5. Alerts --}}
    <x-hub.card
        title="Alerts"
        summary="Issues requiring attention"
        icon="exclamation-triangle"
        :disabled="!Route::has('monitoring.index')"
        :href="Route::has('monitoring.index') ? route('monitoring.index') : '#'"
        color="warning"
        :metric="$alertTotal > 0 ? $alertTotal : null"
        metricLabel="active"
        :metricColor="$alertTotal > 0 ? 'warning' : null"
    >
        <div class="space-y-2">
            @forelse($alerts['items'] as $item)
                <div class="flex items-start gap-2.5 rounded-lg bg-{{ $item['color'] ?? 'base-200' }}/5 p-2.5">
                    <x-ui.icon :name="$item['icon'] ?? 'information-circle'" size="xs" class="mt-0.5 shrink-0 text-{{ $item['color'] ?? 'base-content' }}" />
                    <div>
                        <div class="text-sm font-medium">{{ $item['title'] }}</div>
                        @if(!empty($item['description']))
                            <div class="text-xs text-base-content/60">{{ $item['description'] }}</div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex items-center gap-2 py-2 text-sm text-success">
                    <x-heroicon-s-check-circle class="size-4" />
                    <span>All clear — no active alerts</span>
                </div>
            @endforelse
        </div>
    </x-hub.card>

    {{-- 6. Recent Activity --}}
    <x-hub.card
        title="Recent Activity"
        summary="Assessment syncs in the last 24 hours"
        icon="clock"
        href="{{ route('dashboard') }}"
        color="neutral"
        :metric="$this->recentActivity->count()"
        metricLabel="events"
    >
        <div class="space-y-1">
            @forelse($this->recentActivity->take(5) as $activity)
                <div class="flex items-center justify-between gap-2 rounded px-1 py-1.5 text-sm hover:bg-base-200/50">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="badge badge-xs badge-{{ match($activity->status) {
                            'ACTIV' => 'primary',
                            'QC' => 'info',
                            'REWRK' => 'warning',
                            'CLOSE' => 'success',
                            default => 'ghost',
                        } }}">{{ $activity->status }}</span>
                        <span class="truncate">{{ Str::limit($activity->raw_title, 30) }}</span>
                    </div>
                    <span class="shrink-0 text-xs text-base-content/50">
                        {{ $activity->last_synced_at?->diffForHumans(short: true) }}
                    </span>
                </div>
            @empty
                <p class="text-sm text-base-content/40 text-center py-2">No recent activity</p>
            @endforelse
        </div>
    </x-hub.card>

</x-hub.layout>
