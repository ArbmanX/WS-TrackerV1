@php
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $barColors = ['bg-primary', 'bg-secondary', 'bg-accent', 'bg-info', 'bg-success', 'bg-warning'];
    $plannerList = $this->planners;

    // Find max daily miles across all planners
    $maxVal = 0;
    foreach ($plannerList as $planner) {
        foreach ($planner['daily_miles'] as $dm) {
            $maxVal = max($maxVal, $dm['miles']);
        }
    }

    // Compute nice y-axis ticks
    if ($maxVal <= 0) {
        $yMax = 1;
        $step = 0.2;
    } else {
        $roughStep = $maxVal / 4;
        $magnitude = pow(10, floor(log10($roughStep)));
        $normalized = $roughStep / $magnitude;

        if ($normalized < 1.5) $niceStep = 1;
        elseif ($normalized < 3.5) $niceStep = 2;
        elseif ($normalized < 7.5) $niceStep = 5;
        else $niceStep = 10;

        $step = $niceStep * $magnitude;
        $yMax = ceil($maxVal / $step) * $step;
    }

    $decimals = max(0, (int) ceil(-log10($step)));
    $ticks = [];
    $numTicks = (int) round($yMax / $step);
    for ($i = 0; $i <= $numTicks; $i++) {
        $ticks[] = round($i * $step, $decimals + 1);
    }
@endphp

<div class="card bg-base-100 shadow">
    <div class="card-body p-4 sm:p-5">
        <h3 class="text-sm font-semibold">Week Production (Miles by Planner)</h3>

        <div class="flex items-end overflow-x-auto">
            {{-- Y-axis labels --}}
            <div class="relative shrink-0 w-9 sm:w-11 h-36 sm:h-44">
                @foreach($ticks as $tick)
                    <span
                        class="absolute right-1.5 text-[10px] sm:text-xs text-base-content/50 -translate-y-1/2 tabular-nums leading-none"
                        style="bottom: {{ $yMax > 0 ? ($tick / $yMax) * 100 : 0 }}%"
                    >
                        {{ number_format($tick, $decimals) }}
                    </span>
                @endforeach
            </div>

            {{-- Chart area --}}
            <div class="flex-1 relative h-36 sm:h-44 border-l border-b border-base-content/15 min-w-0">
                {{-- Grid lines --}}
                @foreach($ticks as $tick)
                    @if($tick > 0)
                        <div
                            class="absolute left-0 right-0 border-b border-base-content/10 border-dashed"
                            style="bottom: {{ ($tick / $yMax) * 100 }}%"
                        ></div>
                    @endif
                @endforeach

                {{-- Bar groups --}}
                <div class="absolute inset-0 grid grid-cols-7">
                    @foreach($days as $di => $day)
                        <div class="flex items-end justify-center gap-px h-full px-0.5">
                            @foreach($plannerList as $pi => $planner)
                                @php $miles = $planner['daily_miles'][$di]['miles'] ?? 0; @endphp
                                <div
                                    class="flex-1 max-w-3 rounded-t {{ $barColors[$pi % count($barColors)] }} opacity-85 hover:opacity-100 transition-opacity cursor-default"
                                    style="height: {{ $yMax > 0 ? ($miles > 0 ? max(2, ($miles / $yMax) * 100) : 0) : 0 }}%"
                                    title="{{ $planner['display_name'] }}: {{ number_format($miles, 2) }} mi"
                                ></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- X-axis labels --}}
        <div class="flex">
            <div class="shrink-0 w-9 sm:w-11"></div>
            <div class="flex-1 grid grid-cols-7 text-[10px] sm:text-xs text-base-content/50">
                @foreach($days as $day)
                    <span class="text-center">{{ $day }}</span>
                @endforeach
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex flex-wrap gap-x-4 gap-y-1 justify-center mt-1">
            @foreach($plannerList as $pi => $planner)
                <div class="flex items-center gap-1.5">
                    <span class="w-2.5 h-2.5 rounded-sm shrink-0 {{ $barColors[$pi % count($barColors)] }}"></span>
                    <span class="text-xs text-base-content/70">{{ $planner['display_name'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
