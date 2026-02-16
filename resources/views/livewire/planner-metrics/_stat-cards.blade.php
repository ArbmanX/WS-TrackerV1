@php
    $stats = $this->summaryStats;
    $onTrack = $stats['on_track'];
    $total = $stats['total_planners'];
    $avgPercent = $stats['team_avg_percent'];
    $totalAging = $stats['total_aging'];
    $totalMiles = $stats['total_miles'];

    $onTrackRatio = $total > 0 ? $onTrack / $total : 0;
    $onTrackColor = match(true) {
        $onTrackRatio > 0.5 => 'text-success',
        $onTrackRatio > 0.25 => 'text-warning',
        default => 'text-error',
    };
    $avgColor = match(true) {
        $avgPercent >= 100 => 'text-success',
        $avgPercent >= 50 => 'text-warning',
        default => 'text-error',
    };
    $agingColor = match(true) {
        $totalAging === 0 => 'text-success',
        $totalAging < 100 => 'text-warning',
        default => 'text-error',
    };
@endphp

<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    {{-- On Track --}}
    <div class="stat bg-base-100 shadow-sm rounded-box p-4">
        <div class="stat-title text-xs">On Track</div>
        <div class="stat-value text-2xl tabular-nums {{ $onTrackColor }}">{{ $onTrack }}/{{ $total }}</div>
        <div class="stat-desc text-xs">planners</div>
    </div>

    {{-- Team Avg --}}
    <div class="stat bg-base-100 shadow-sm rounded-box p-4">
        <div class="stat-title text-xs">Team Avg</div>
        <div class="stat-value text-2xl tabular-nums {{ $avgColor }}">{{ $avgPercent }}%</div>
        <div class="stat-desc text-xs">attainment</div>
    </div>

    {{-- Aging Units --}}
    <div class="stat bg-base-100 shadow-sm rounded-box p-4">
        <div class="stat-title text-xs">Aging Units</div>
        <div class="stat-value text-2xl tabular-nums {{ $agingColor }}">{{ number_format($totalAging) }}</div>
        <div class="stat-desc text-xs">units</div>
    </div>

    {{-- Team Miles --}}
    <div class="stat bg-base-100 shadow-sm rounded-box p-4">
        <div class="stat-title text-xs">Team Miles</div>
        <div class="stat-value text-2xl tabular-nums">{{ $totalMiles }}</div>
        <div class="stat-desc text-xs">this week</div>
    </div>
</div>
