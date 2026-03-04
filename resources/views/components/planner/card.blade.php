@props([
    'name' => '',
    'initials' => '',
    'status' => 'success',
    'statusLabel' => '',
    'region' => '',
    'periodMiles' => 0,
    'quotaTarget' => 6.5,
    'dailyMiles' => [],
    'avatarColor' => null,
    'pendingOverThreshold' => 0,
    'overallPercent' => 0,
    'daysSinceLastEdit' => null,
    'streakWeeks' => 0,
])

{{--
    Planner Performance Card — Reimagined

    Ring avatar shows quota attainment. Colors follow attention-first:
    On Track = primary (quiet blue), Warning = warning, Behind = error.

    Usage:
    <x-planner.card
        name="J. Morales"
        initials="JM"
        status="success"
        statusLabel="On Track"
        :periodMiles="6.1"
        :quotaTarget="6.5"
        :dailyMiles="[['day' => 'Sun', 'miles' => 1.0], ...]"
        :pendingOverThreshold="0"
        :overallPercent="78"
        :daysSinceLastEdit="2"
        :streakWeeks="4"
    />
--}}

@php
    $quotaPercent = $quotaTarget > 0 ? round(($periodMiles / $quotaTarget) * 100) : 0;

    // Attention-first: On Track = primary (calm), not success (loud green)
    $statusColor = match($status) {
        'warning' => 'warning',
        'error' => 'error',
        default => 'primary',
    };

    // Badge: On Track gets a ghost badge (quiet), problems get colored badges
    $badgeClass = match($status) {
        'warning' => 'badge-warning badge-soft',
        'error' => 'badge-error badge-soft',
        default => 'badge-ghost',
    };

    // Max daily value for scaling sparkline heights
    $mileValues = array_column($dailyMiles, 'miles');
    $maxDaily = count($mileValues) > 0 ? max(max($mileValues), 0.1) : 1;
    $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];

    // Health indicator colors — only color problems, not normalcy
    $agingColor = match(true) {
        $pendingOverThreshold === 0 => 'text-base-content/40',
        $pendingOverThreshold < 5 => 'text-warning',
        default => 'text-error',
    };

    $editColor = match(true) {
        $daysSinceLastEdit === null => 'text-base-content/30',
        $daysSinceLastEdit < 3 => 'text-base-content/50',
        $daysSinceLastEdit < 7 => 'text-base-content/50',
        $daysSinceLastEdit < 14 => 'text-warning',
        default => 'text-error',
    };

    // Expanded state
    $isExpanded = $attributes->get('data-expanded') === 'true';
@endphp

<div {{ $attributes->except('data-expanded')->class([
    'card bg-base-100 shadow-sm border border-base-content/5 border-l-4 transition-all duration-150 hover:shadow-md cursor-pointer',
    'border-l-base-content/10' => $statusColor === 'primary',
    'border-l-warning' => $statusColor === 'warning',
    'border-l-error' => $statusColor === 'error',
    'ring-2 ring-primary/30 shadow-md' => $isExpanded,
]) }}>
    <div class="card-body p-4 gap-2">
        {{-- Row 1: Ring Avatar + Name + Badge --}}
        <div class="flex items-center gap-3">
            {{-- Radial Progress Ring as Avatar --}}
            <div
                @class([
                    'radial-progress shrink-0',
                    'text-primary' => $statusColor === 'primary',
                    'text-warning' => $statusColor === 'warning',
                    'text-error' => $statusColor === 'error',
                ])
                style="--value:{{ min($quotaPercent, 100) }}; --size:2.75rem; --thickness:2.5px;"
                role="progressbar"
                aria-label="{{ $quotaPercent }}% quota attainment for {{ $name }}"
            >
                <span class="text-[11px] font-bold text-base-content/70">{{ $initials }}</span>
            </div>

            {{-- Name + Miles --}}
            <div class="flex-1 min-w-0">
                <span class="font-semibold text-sm truncate block leading-tight">{{ $name }}</span>
                <span class="text-xs text-base-content/40 tabular-nums font-code">
                    {{ $periodMiles }} / {{ $quotaTarget }} mi
                </span>
            </div>

            {{-- Badges --}}
            <div class="flex items-center gap-1.5 shrink-0">
                @if($streakWeeks >= 2)
                    <span class="badge badge-sm badge-ghost gap-0.5 tabular-nums" title="{{ $streakWeeks }} week streak">
                        <x-heroicon-m-fire class="size-3 text-warning" />
                        {{ $streakWeeks }}
                    </span>
                @endif
                @if($statusLabel)
                    <span class="badge badge-sm {{ $badgeClass }}">{{ $statusLabel }}</span>
                @endif
            </div>
        </div>

        {{-- Row 2: Sparkline + Health --}}
        <div class="flex items-end gap-3">
            {{-- Mini Sparkline Bars --}}
            @if(count($dailyMiles) > 0)
                <div class="flex-1 min-w-0">
                    <div class="mini-bars" aria-label="Daily miles for {{ $name }}">
                        @foreach($dailyMiles as $day)
                            @php
                                $miles = $day['miles'] ?? 0;
                                $heightPct = $maxDaily > 0 ? max(($miles / $maxDaily) * 100, 4) : 4;
                                $dailyBenchmark = $quotaTarget / 4;

                                if ($miles >= $dailyBenchmark) {
                                    $barColor = 'var(--color-primary)';
                                } elseif ($miles > 0.09) {
                                    $barColor = 'var(--color-warning)';
                                } else {
                                    $barColor = 'var(--color-base-300)';
                                }
                            @endphp
                            <div
                                class="mini-bar"
                                style="height: {{ $heightPct }}%; background: {{ $barColor }};"
                                title="{{ $day['day'] ?? '' }}: {{ $miles }} mi"
                                aria-label="{{ $day['day'] ?? '' }} {{ $miles }} miles"
                            ></div>
                        @endforeach
                    </div>
                    <div class="flex justify-between mt-0.5" style="font-size: 0.6rem;">
                        @foreach($dayLabels as $label)
                            <span class="font-code text-base-content/30">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Health Indicators --}}
            <div class="flex flex-col items-end gap-px shrink-0 text-right">
                <span class="text-[11px] {{ $agingColor }} tabular-nums">
                    {{ $pendingOverThreshold }} aging
                </span>
                <span class="text-[11px] text-base-content/40 tabular-nums">
                    {{ $overallPercent }}%
                </span>
                <span class="text-[11px] {{ $editColor }} tabular-nums">
                    @if($daysSinceLastEdit !== null)
                        {{ $daysSinceLastEdit }}d ago
                    @else
                        &mdash;
                    @endif
                </span>
            </div>
        </div>
    </div>
</div>
