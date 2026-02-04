@props([
    'assessment' => [],
])

@php
    $owner = $assessment['Current_Owner'] ?? 'Unknown';
    $shortName = str_contains($owner, '\\') ? \Illuminate\Support\Str::after($owner, '\\') : $owner;
    $initials = collect(explode('.', $shortName))
        ->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))
        ->take(2)
        ->implode('');

    $lineName = $assessment['Line_Name'] ?? '—';
    $workOrder = $assessment['Work_Order'] ?? '—';
    $totalMiles = (float) ($assessment['Total_Miles'] ?? 0);
    $completedMiles = (float) ($assessment['Completed_Miles'] ?? 0);
    $percent = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
    $milesRemaining = $totalMiles - $completedMiles;

    $firstEdit = $assessment['First_Edit_Date'] ?? '—';
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg bg-base-200/50 p-3 hover:bg-base-200 transition-colors']) }}>
    <div class="flex items-center gap-3">
        {{-- Owner Avatar --}}
        <div class="avatar placeholder">
            <div class="bg-neutral text-neutral-content w-8 rounded-full">
                <span class="text-xs">{{ $initials }}</span>
            </div>
        </div>

        {{-- Info --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <span class="font-medium text-sm truncate" title="{{ $owner }}">{{ $shortName }}</span>
                <span class="text-xs text-base-content/50 whitespace-nowrap">{{ $firstEdit }}</span>
            </div>
            <div class="flex items-center gap-2 mt-0.5">
                <span class="text-xs text-base-content/70 truncate" title="{{ $lineName }}">{{ $lineName }}</span>
                <span class="text-base-content/30">&middot;</span>
                <span class="text-xs text-base-content/50 font-mono shrink-0">{{ $workOrder }}</span>
            </div>
        </div>
    </div>

    {{-- Progress --}}
    <div class="mt-2 flex items-center gap-3">
        <progress
            class="progress flex-1 h-1.5 {{ $percent >= 75 ? 'progress-success' : ($percent >= 50 ? 'progress-warning' : 'progress-primary') }}"
            value="{{ $percent }}"
            max="100"
        ></progress>
        <span class="text-xs font-semibold tabular-nums whitespace-nowrap {{ $percent >= 75 ? 'text-success' : ($percent >= 50 ? 'text-warning' : '') }}">
            {{ number_format($percent, 0) }}%
        </span>
        <span class="text-xs text-base-content/50 tabular-nums whitespace-nowrap">
            {{ number_format($milesRemaining, 1) }} mi left
        </span>
    </div>
</div>
