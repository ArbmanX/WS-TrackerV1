@php
    $permissionMeta = [
        'Approved' => ['label' => 'Approved', 'class' => 'badge-primary badge-soft'],
        'Pending' => ['label' => 'Pending', 'class' => 'badge-warning badge-soft'],
        'No Contact' => ['label' => 'No Contact', 'class' => 'badge-ghost'],
        'Refused' => ['label' => 'Refused', 'class' => 'badge-error badge-soft'],
        'Deferred' => ['label' => 'Deferred', 'class' => 'badge-ghost'],
        'PPL Approved' => ['label' => 'PPL', 'class' => 'badge-primary badge-outline'],
    ];
@endphp

@if(count($circuits) > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($circuits as $circuit)
            @php
                $pct = $circuit['percent_complete'] ?? 0;
                $progressColor = match(true) {
                    $pct >= 75 => 'progress-primary',
                    $pct >= 40 => 'progress-warning',
                    default => 'progress-error',
                };
            @endphp
            <div class="card card-compact bg-base-100 border border-base-content/5">
                <div class="card-body gap-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h4 class="font-semibold text-sm truncate">{{ $circuit['line_name'] }}</h4>
                            <span class="text-xs text-base-content/40">{{ $circuit['region'] }}</span>
                        </div>
                        <span class="text-xs font-code tabular-nums text-base-content/60 shrink-0">
                            {{ $pct }}%
                        </span>
                    </div>

                    <progress
                        class="progress {{ $progressColor }} w-full h-1.5"
                        value="{{ min($pct, 100) }}"
                        max="100"
                    ></progress>

                    <div class="flex items-center justify-between">
                        <span class="text-xs text-base-content/40 tabular-nums font-code">
                            {{ $circuit['completed_miles'] }} / {{ $circuit['total_miles'] }} mi
                        </span>

                        @if(!empty($circuit['permission_breakdown']))
                            <div class="flex flex-wrap gap-1 justify-end">
                                @foreach($circuit['permission_breakdown'] as $permStatus => $count)
                                    @if($count > 0 && isset($permissionMeta[$permStatus]))
                                        <span
                                            class="badge badge-xs {{ $permissionMeta[$permStatus]['class'] }} tabular-nums"
                                            title="{{ $permissionMeta[$permStatus]['label'] }}: {{ $count }}"
                                        >
                                            {{ $count }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center py-8 text-base-content/30">
        <x-heroicon-o-map class="size-8 mx-auto mb-2" />
        <p class="text-sm">No active circuits</p>
    </div>
@endif
