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
])

{{--
    Planner Performance Card

    Compact card showing a planner's weekly performance: avatar, status,
    quota progress bar, and a mini sparkline bar chart of daily miles.

    Usage:
    <x-planner.card
        name="J. Morales"
        initials="JM"
        status="warning"
        statusLabel="Progressing"
        region="North Region"
        :periodMiles="6.1"
        :quotaTarget="6.5"
        :dailyMiles="[
            ['day' => 'Sun', 'miles' => 1.0],
            ['day' => 'Mon', 'miles' => 1.4],
            ...
        ]"
    />
--}}

@php
    $quotaPercent = $quotaTarget > 0 ? ($periodMiles / $quotaTarget) * 100 : 0;

    // Status → DaisyUI v5 CSS variable name
    $statusColor = match($status) {
        'warning' => 'warning',
        'error' => 'error',
        default => 'success',
    };
    $cssVar = "--color-{$statusColor}";

    // Avatar color: use provided or derive from name via hue rotation
    $hue = abs(crc32($name)) % 360;
    $avatarFg = $avatarColor ?? "hsl({$hue}, 60%, 55%)";
    $avatarBg = $avatarColor ? "{$avatarColor}20" : "hsla({$hue}, 60%, 55%, 0.12)";

    // Max daily value for scaling bar heights (minimum 1 to avoid division by zero)
    $mileValues = array_column($dailyMiles, 'miles');
    $maxDaily = count($mileValues) > 0 ? max(max($mileValues), 1) : 1;
    $dayLabels = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
@endphp

<div {{ $attributes->merge(['class' => 'bg-base-200 border border-base-300 rounded-box p-4 flex items-center gap-4 transition-colors hover:border-primary cursor-pointer']) }}>
    {{-- Avatar --}}
    <div class="avatar avatar-placeholder">
        <div
            class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
            style="background: {{ $avatarBg }}; color: {{ $avatarFg }};"
        >
            {{ $initials }}
        </div>
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        {{-- Name + Status Badge --}}
        <div class="flex items-center gap-2 mb-1">
            <span class="font-code font-semibold text-sm truncate">{{ $name }}</span>
            @if($statusLabel)
                <span class="badge badge-{{ $statusColor }} badge-sm">{{ $statusLabel }}</span>
            @endif
        </div>

        {{-- Region --}}
        @if($region)
            <div class="text-base-content/50 text-xs mb-1.5">{{ $region }}</div>
        @endif

        {{-- Progress Bar --}}
        <div class="flex items-center gap-2 mb-2">
            <div class="progress-bar-track">
                <div
                    class="progress-bar-fill"
                    style="width: {{ min($quotaPercent, 100) }}%; background: var({{ $cssVar }});"
                ></div>
            </div>
            <span
                class="font-code text-xs whitespace-nowrap"
                style="color: var({{ $cssVar }});"
            >
                {{ $periodMiles }}/{{ $quotaTarget }}
            </span>
        </div>

        {{-- Mini Sparkline Bars --}}
        @if(count($dailyMiles) > 0)
            <div class="mini-bars" aria-label="Daily miles for {{ $name }}">
                @foreach($dailyMiles as $day)
                    @php
                        $miles = $day['miles'] ?? 0;
                        $heightPct = $maxDaily > 0 ? max(($miles / $maxDaily) * 100, 4) : 4;


                        $dailyBenchmark = $quotaTarget / 4;

                        if ($miles >= $dailyBenchmark) {
                            $barColor = 'var(--color-success)';
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

            {{-- Day Labels --}}
            <div class="flex justify-between text-base-content/50 mt-1" style="font-size: 0.625rem;">
                @foreach($dayLabels as $label)
                    <span class="font-code">{{ $label }}</span>
                @endforeach
            </div>
        @endif
    </div>
</div>
