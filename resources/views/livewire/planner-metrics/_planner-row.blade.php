@php
    $status = $planner['status'] ?? 'success';
    $periodMiles = $planner['period_miles'] ?? 0;
    $quotaTarget = $planner['quota_target'] ?? 6.5;
    $quotaPercent = $planner['quota_percent'] ?? 0;
    $streakWeeks = $planner['streak_weeks'] ?? 0;
    $gapMiles = $planner['gap_miles'] ?? 0;
    $daysSinceLastEdit = $planner['days_since_last_edit'] ?? null;
    $pendingOverThreshold = $planner['pending_over_threshold'] ?? 0;
    $overallPercent = $planner['overall_percent'] ?? 0;
    $circuitCount = count($planner['circuits'] ?? []);
    $username = $planner['username'];
    $isExpanded = $this->expandedPlanner === $username;

    $agingColor = match(true) {
        $pendingOverThreshold === 0 => 'text-success',
        $pendingOverThreshold < 5 => 'text-warning',
        default => 'text-error',
    };
    $editColor = match(true) {
        $daysSinceLastEdit === null => 'text-base-content/50',
        $daysSinceLastEdit < 3 => 'text-success',
        $daysSinceLastEdit < 7 => 'text-base-content',
        $daysSinceLastEdit < 14 => 'text-warning',
        default => 'text-error',
    };
@endphp

<div
    @class([
        'card card-compact bg-base-100 shadow-sm border-l-4',
        'border-l-success' => $status === 'success',
        'border-l-warning' => $status === 'warning',
        'border-l-error' => $status === 'error',
    ])
>
    <div class="card-body">
        {{-- Row 1: Header --}}
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span @class([
                    'size-2 rounded-full',
                    'bg-success' => $status === 'success',
                    'bg-warning' => $status === 'warning',
                    'bg-error' => $status === 'error',
                ])></span>
                <h3 class="font-semibold text-sm">{{ $planner['display_name'] }}</h3>
            </div>
            <div class="flex items-center gap-2">
                @if($streakWeeks >= 1)
                    <span class="badge badge-sm badge-success">{{ $streakWeeks }}wk</span>
                @endif
                <button
                    type="button"
                    wire:click="toggleAccordion({{ \Illuminate\Support\Js::from($username) }})"
                    class="btn btn-xs btn-ghost gap-1 tabular-nums"
                    title="Toggle circuits for {{ $planner['display_name'] }}"
                >
                    {{ $circuitCount }}
                    <x-heroicon-m-chevron-down @class(['size-3 transition-transform', 'rotate-180' => $isExpanded]) />
                </button>
            </div>
        </div>

        {{-- Row 2: Quota Progress --}}
        <progress
            @class([
                'progress w-full h-2',
                'progress-success' => $status === 'success',
                'progress-warning' => $status === 'warning',
                'progress-error' => $status === 'error',
            ])
            value="{{ min($quotaPercent, 100) }}"
            max="100"
        ></progress>

        <div class="flex items-baseline justify-between">
            <div>
                <span class="text-lg font-bold tabular-nums">{{ $periodMiles }}</span>
                <span class="text-base-content/60 text-sm"> / {{ $quotaTarget }} mi</span>
            </div>
            <span class="text-sm font-medium tabular-nums">{{ $quotaPercent }}%</span>
        </div>

        {{-- Row 3: Health Indicators --}}
        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-base-content/60">
            <span class="{{ $agingColor }}">{{ $pendingOverThreshold }} aging</span>
            <span>&middot;</span>
            <span>{{ $overallPercent }}% complete</span>
            <span>&middot;</span>
            <span class="{{ $editColor }}">
                @if($daysSinceLastEdit !== null)
                    Edit {{ $daysSinceLastEdit }}d ago
                @else
                    No active assessments
                @endif
            </span>
        </div>
    </div>

    {{-- Accordion: Circuit Detail --}}
    @if($isExpanded)
        <div class="border-t border-base-200 px-4 py-3" wire:key="accordion-{{ $username }}">
            @include('livewire.planner-metrics._circuit-accordion', [
                'circuits' => $this->expandedCircuits,
            ])
        </div>
    @endif
</div>
