@php
    $permissionIcons = [
        'Approved' => ['label' => 'Approved', 'class' => 'badge-success'],
        'Pending' => ['label' => 'Pending', 'class' => 'badge-warning'],
        'No Contact' => ['label' => 'No Contact', 'class' => 'badge-info'],
        'Refused' => ['label' => 'Refused', 'class' => 'badge-error'],
        'Deferred' => ['label' => 'Deferred', 'class' => 'badge-neutral'],
        'PPL Approved' => ['label' => 'PPL', 'class' => 'badge-success badge-outline'],
    ];
@endphp

@if(count($circuits) > 0)
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        @foreach($circuits as $circuit)
            <div class="card card-compact bg-base-200">
                <div class="card-body">
                    <div class="flex items-start justify-between">
                        <div>
                            <h4 class="font-medium text-sm">{{ $circuit['line_name'] }}</h4>
                            <span class="text-xs text-base-content/60">{{ $circuit['region'] }}</span>
                        </div>
                        <span class="text-xs font-medium tabular-nums">
                            {{ $circuit['percent_complete'] }}%
                        </span>
                    </div>

                    <progress
                        class="progress progress-primary w-full h-1.5"
                        value="{{ min($circuit['percent_complete'], 100) }}"
                        max="100"
                    ></progress>

                    <div class="text-xs text-base-content/60">
                        {{ $circuit['completed_miles'] }} / {{ $circuit['total_miles'] }} mi
                    </div>

                    @if(!empty($circuit['permission_breakdown']))
                        <div class="flex flex-wrap gap-1">
                            @foreach($circuit['permission_breakdown'] as $permStatus => $count)
                                @if($count > 0 && isset($permissionIcons[$permStatus]))
                                    <span
                                        class="badge badge-xs {{ $permissionIcons[$permStatus]['class'] }}"
                                        title="{{ $permissionIcons[$permStatus]['label'] }}"
                                    >
                                        {{ $count }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="text-center py-6 text-base-content/50">
        <x-heroicon-o-map class="size-8 mx-auto mb-2" />
        <p class="text-sm">No active circuits</p>
    </div>
@endif
