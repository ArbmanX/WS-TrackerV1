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

{{-- Header --}}
<div class="flex items-center justify-between mb-4">
    <h3 class="text-lg font-semibold">{{ $plannerName }}'s Circuits</h3>
    <button type="button" wire:click="closeDrawer" class="btn btn-sm btn-ghost btn-circle">
        <x-heroicon-m-x-mark class="size-4" />
    </button>
</div>

<p class="text-sm text-base-content/60 mb-4">
    {{ count($circuits) }} active {{ Str::plural('circuit', count($circuits)) }}
</p>

{{-- Circuit List --}}
@forelse($circuits as $circuit)
    <div class="card card-compact bg-base-200 mb-3">
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
                    @foreach($circuit['permission_breakdown'] as $status => $count)
                        @if($count > 0 && isset($permissionIcons[$status]))
                            <span
                                class="badge badge-xs {{ $permissionIcons[$status]['class'] }}"
                                title="{{ $permissionIcons[$status]['label'] }}"
                            >
                                {{ $count }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="text-center py-8 text-base-content/50">
        <x-heroicon-o-map class="size-8 mx-auto mb-2" />
        <p class="text-sm">No active circuits</p>
    </div>
@endforelse
