@php
    $status = $planner['status'] ?? 'success';
    $periodMiles = $planner['period_miles'] ?? 0;
    $quotaTarget = $planner['quota_target'] ?? 6.5;
    $percentComplete = $planner['percent_complete'] ?? 0;
    $streakWeeks = $planner['streak_weeks'] ?? 0;
    $gapMiles = $planner['gap_miles'] ?? 0;
    $daysSinceLastEdit = $planner['days_since_last_edit'] ?? null;
    $activeAssessmentCount = $planner['active_assessment_count'] ?? 0;

    $editColor = match(true) {
        $daysSinceLastEdit === null => 'text-base-content/50',
        $daysSinceLastEdit < 3 => 'text-success',
        $daysSinceLastEdit < 7 => 'text-base-content',
        $daysSinceLastEdit < 14 => 'text-warning',
        default => 'text-error',
    };
@endphp

<div class="flex">
    <div
        @class([
            'card card-compact bg-base-100 shadow-sm border-l-4 flex-1 min-w-0',
            'border-l-success' => $status === 'success',
            'border-l-warning' => $status === 'warning',
            'border-l-error' => $status === 'error',
        ])
    >
        <div class="card-body">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <h3 class="card-title text-sm font-semibold">{{ $planner['display_name'] }}</h3>
                @if($streakWeeks >= 1)
                    <span class="badge badge-sm badge-success gap-1">
                        {{ $streakWeeks }}wk
                    </span>
                @endif
            </div>

            {{-- Progress Bar --}}
            <progress
                @class([
                    'progress w-full h-2',
                    'progress-success' => $status === 'success',
                    'progress-warning' => $status === 'warning',
                    'progress-error' => $status === 'error',
                ])
                value="{{ min($percentComplete, 100) }}"
                max="100"
            ></progress>

            {{-- Metric Row --}}
            <div class="flex items-baseline justify-between">
                <span class="text-3xl font-bold tabular-nums">{{ $periodMiles }}</span>
                <span class="text-base-content/60 text-sm">/ {{ $quotaTarget }} mi</span>
                <span class="text-sm font-medium tabular-nums">{{ $percentComplete }}%</span>
            </div>

            {{-- Supporting Row --}}
            <div class="flex items-center gap-2 text-xs text-base-content/60">
                <span>{{ $activeAssessmentCount }} {{ Str::plural('job', $activeAssessmentCount) }}</span>
                <span>&middot;</span>
                <span class="{{ $editColor }}">
                    @if($daysSinceLastEdit !== null)
                        Last edit {{ $daysSinceLastEdit }}d ago
                    @else
                        No active assessments
                    @endif
                </span>
            </div>

            {{-- Coaching Message --}}
            @if($coachingMessage ?? null)
                @include('livewire.planner-metrics._coaching-message', ['message' => $coachingMessage])
            @endif
        </div>
    </div>
    <button
        type="button"
        wire:click="openDrawer({{ \Illuminate\Support\Js::from($planner['username']) }})"
        class="flex items-center px-1.5 rounded-r-lg bg-base-200/50 hover:bg-primary/10 transition-colors"
        title="View {{ $planner['display_name'] }}'s circuits"
    >
        <x-heroicon-m-chevron-right class="size-4 text-base-content/40" />
    </button>
</div>
