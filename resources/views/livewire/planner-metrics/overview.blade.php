<div class="max-w-5xl mx-auto space-y-6">
    {{-- Header --}}
    <div>
        <h1 class="text-2xl font-bold">Planner Metrics</h1>
        <p class="text-base-content/60">Weekly planner performance overview</p>
    </div>

    {{-- Stat Cards --}}
    @if(!empty($this->planners))
        @include('livewire.planner-metrics._stat-cards')
    @endif

    {{-- Controls Row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        {{-- Period Navigation --}}
        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="navigateOffset(-1)"
                class="btn btn-sm btn-ghost btn-circle"
                aria-label="Previous week"
            >
                <x-heroicon-m-chevron-left class="size-4" />
            </button>

            <button
                type="button"
                wire:click="resetOffset"
                class="btn btn-sm btn-ghost font-medium min-w-48 tabular-nums"
            >
                {{ $this->periodLabel }}
            </button>

            <button
                type="button"
                wire:click="navigateOffset(1)"
                class="btn btn-sm btn-ghost btn-circle"
                @disabled($this->resolvedOffset >= 0)
                aria-label="Next week"
            >
                <x-heroicon-m-chevron-right class="size-4" />
            </button>
        </div>

        {{-- Sort Toggle --}}
        <div class="join">
            <button
                type="button"
                wire:click="switchSort('alpha')"
                @class(['btn btn-sm join-item', 'btn-primary' => $sortBy === 'alpha'])
            >
                A-Z
            </button>
            <button
                type="button"
                wire:click="switchSort('attention')"
                @class(['btn btn-sm join-item', 'btn-primary' => $sortBy === 'attention'])
            >
                Needs Attention
            </button>
        </div>
    </div>

    {{-- Planner List --}}
    <div class="relative">
        {{-- Loading Overlay --}}
        <div
            wire:loading.flex
            wire:target="switchSort, navigateOffset, resetOffset"
            class="absolute inset-0 z-10 items-center justify-center rounded-box bg-base-100/60"
        >
            <span class="loading loading-spinner loading-lg text-primary"></span>
        </div>

        <div wire:loading.remove wire:target="switchSort, navigateOffset, resetOffset">
            @if(empty($this->planners))
                {{-- Empty State --}}
                <div class="card bg-base-100 shadow">
                    <div class="card-body text-center py-12">
                        <x-heroicon-o-users class="size-12 mx-auto mb-4 text-base-content/30" />
                        <h3 class="text-lg font-medium mb-1">No Planner Data Available</h3>
                        <p class="text-base-content/60">
                            Run <code class="badge badge-sm">ws:export-planner-career</code> to populate planner career data.
                        </p>
                    </div>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($this->planners as $planner)
                        @php
                            $username = $planner['username'];
                            $displayName = $planner['display_name'];
                            $initials = strtoupper(mb_substr($displayName, 0, 2));
                            $statusLabel = match($planner['status']) {
                                'success' => 'On Track',
                                'warning' => 'Progressing',
                                'error' => 'Behind',
                                default => '',
                            };
                            $isExpanded = $this->expandedPlanner === $username;
                        @endphp

                        <div wire:key="planner-{{ $username }}">
                            <x-planner.card
                                :name="$displayName"
                                :initials="$initials"
                                :status="$planner['status']"
                                :statusLabel="$statusLabel"
                                :periodMiles="$planner['period_miles']"
                                :quotaTarget="$planner['quota_target']"
                                :dailyMiles="$planner['daily_miles'] ?? []"
                                wire:click="toggleAccordion({{ \Illuminate\Support\Js::from($username) }})"
                            />

                            @if($isExpanded)
                                <div class="mt-2" wire:key="accordion-{{ $username }}">
                                    @include('livewire.planner-metrics._circuit-accordion', [
                                        'circuits' => $this->expandedCircuits,
                                    ])
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-xs text-base-content/40 text-right" wire:loading.remove wire:target="switchSort, navigateOffset, resetOffset">
        Last updated: {{ now()->format('M j, Y g:i A') }}
    </div>
</div>
