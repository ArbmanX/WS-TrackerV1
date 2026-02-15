@php
    $status = $planner['status'] ?? 'success';
    $daysSinceLastEdit = $planner['days_since_last_edit'] ?? null;
    $pendingOverThreshold = $planner['pending_over_threshold'] ?? 0;
    $permissionBreakdown = $planner['permission_breakdown'] ?? [];
    $totalMiles = $planner['total_miles'] ?? 0;
    $percentComplete = $planner['percent_complete'] ?? 0;
    $activeCount = $planner['active_assessment_count'] ?? 0;

    $stalenessColor = match(true) {
        $daysSinceLastEdit === null => 'text-base-content/50',
        $daysSinceLastEdit < 7 => 'text-success',
        $daysSinceLastEdit < 14 => 'text-warning',
        default => 'text-error',
    };

    $agingColor = match(true) {
        $pendingOverThreshold === 0 => 'text-success',
        $pendingOverThreshold < 5 => 'text-warning',
        default => 'text-error',
    };

    $permissionIcons = [
        'Approved' => ['label' => 'Approved', 'class' => 'badge-success'],
        'Pending' => ['label' => 'Pending', 'class' => 'badge-warning'],
        'No Contact' => ['label' => 'No Contact', 'class' => 'badge-info'],
        'Refused' => ['label' => 'Refused', 'class' => 'badge-error'],
        'Deferred' => ['label' => 'Deferred', 'class' => 'badge-neutral'],
        'PPL Approved' => ['label' => 'PPL', 'class' => 'badge-success badge-outline'],
    ];
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
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h3 class="card-title text-sm font-semibold">{{ $planner['display_name'] }}</h3>
            <span class="text-xs text-base-content/60">{{ $activeCount }} {{ Str::plural('job', $activeCount) }}</span>
        </div>

        @if($activeCount === 0)
            <div class="py-4 text-center text-sm text-base-content/50">
                No active assessments
            </div>
        @else
            {{-- Aging Units --}}
            <div class="flex items-center justify-between text-sm">
                <span class="{{ $agingColor }} font-medium">
                    {{ $pendingOverThreshold }} {{ Str::plural('unit', $pendingOverThreshold) }} pending > {{ config('ws_data_collection.thresholds.aging_unit_days', 14) }}d
                </span>
            </div>

            {{-- Staleness --}}
            <div class="text-sm">
                <span class="{{ $stalenessColor }}">
                    Last edit: {{ $daysSinceLastEdit !== null ? $daysSinceLastEdit . ' days ago' : 'Unknown' }}
                </span>
            </div>

            {{-- Permission Badges --}}
            @if(!empty($permissionBreakdown))
                <div class="flex flex-wrap gap-1">
                    @foreach($permissionBreakdown as $permStatus => $count)
                        @if($count > 0 && isset($permissionIcons[$permStatus]))
                            <span
                                class="badge badge-sm {{ $permissionIcons[$permStatus]['class'] }}"
                                title="{{ $permissionIcons[$permStatus]['label'] }}"
                            >
                                {{ $count }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Progress Summary --}}
            <div class="text-sm text-base-content/60">
                {{ $totalMiles }} mi &mdash; {{ $percentComplete }}% complete
            </div>
        @endif
    </div>
</div>
