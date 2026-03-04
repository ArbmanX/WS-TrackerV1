@php
    $stats = $this->summaryStats;
    $onTrack = $stats['on_track'];
    $total = $stats['total_planners'];
    $avgPercent = $stats['team_avg_percent'];
    $totalAging = $stats['total_aging'];
    $totalMiles = $stats['total_miles'];

    $onTrackRatio = $total > 0 ? $onTrack / $total : 0;
    $onTrackColor = match(true) {
        $onTrackRatio > 0.5 => 'text-primary',
        $onTrackRatio > 0.25 => 'text-warning',
        default => 'text-error',
    };

    $avgColor = match(true) {
        $avgPercent >= 80 => 'text-primary',
        $avgPercent >= 50 => 'text-warning',
        default => 'text-error',
    };

    $needsAttention = $total - $onTrack;
@endphp

<div class="stats stats-vertical sm:stats-horizontal bg-base-100 shadow-sm border border-base-content/5 w-full">
    {{-- On Track --}}
    <div class="stat gap-0 py-3 px-5">
        <div class="stat-title text-[11px] font-medium uppercase tracking-wider">On Track</div>
        <div class="stat-value text-xl tabular-nums {{ $onTrackColor }}">
            {{ $onTrack }}<span class="text-sm font-normal text-base-content/30">/{{ $total }}</span>
        </div>
        <div class="stat-desc text-xs">
            @if($needsAttention > 0)
                <span class="text-warning">{{ $needsAttention }} need attention</span>
            @else
                all planners
            @endif
        </div>
    </div>

    {{-- Team Avg --}}
    <div class="stat gap-0 py-3 px-5">
        <div class="stat-title text-[11px] font-medium uppercase tracking-wider">Team Avg</div>
        <div class="stat-value text-xl tabular-nums {{ $avgColor }}">
            {{ $avgPercent }}<span class="text-sm font-normal text-base-content/30">%</span>
        </div>
        <div class="stat-desc text-xs">attainment</div>
    </div>

    {{-- Aging --}}
    <div class="stat gap-0 py-3 px-5">
        <div class="stat-title text-[11px] font-medium uppercase tracking-wider">Aging</div>
        <div class="stat-value text-xl tabular-nums {{ $totalAging > 0 ? 'text-warning' : '' }}">
            {{ number_format($totalAging) }}
        </div>
        <div class="stat-desc text-xs">pending units</div>
    </div>

    {{-- Miles --}}
    <div class="stat gap-0 py-3 px-5">
        <div class="stat-title text-[11px] font-medium uppercase tracking-wider">Miles</div>
        <div class="stat-value text-xl tabular-nums">{{ $totalMiles }}</div>
        <div class="stat-desc text-xs">this week</div>
    </div>
</div>
