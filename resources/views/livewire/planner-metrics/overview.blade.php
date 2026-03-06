<div class="space-y-5">
    {{-- Header + Period Navigation --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Weekly Reports</h1>
            <p class="text-sm text-base-content/50">Weekly performance overview</p>
        </div>

        <div class="flex items-center gap-1 bg-base-200/50 rounded-btn px-1 py-0.5">
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
                class="btn btn-sm btn-ghost font-medium min-w-44 tabular-nums text-sm"
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
    </div>

    {{-- Team Summary Strip --}}
    @if(!empty($this->planners))
        @include('livewire.planner-metrics._stat-cards')
    @endif

    {{-- Controls Row --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="join join-horizontal">
            <button
                type="button"
                wire:click="switchSort('alpha')"
                @class([
                    'btn btn-sm join-item gap-1.5',
                    'btn-active' => $sortBy === 'alpha',
                ])
            >
                <x-heroicon-m-bars-arrow-down class="size-3.5" />
                A-Z
            </button>
            <button
                type="button"
                wire:click="switchSort('attention')"
                @class([
                    'btn btn-sm join-item gap-1.5',
                    'btn-active' => $sortBy === 'attention',
                ])
            >
                <x-heroicon-m-exclamation-triangle class="size-3.5" />
                Attention
            </button>
        </div>

    </div>

    {{-- Planner Grid --}}
    <div class="relative">
        {{-- Loading Overlay --}}
        <div
            wire:loading.flex
            wire:target="switchSort, navigateOffset, resetOffset"
            class="absolute inset-0 z-10 items-center justify-center rounded-box bg-base-100/70 backdrop-blur-sm"
        >
            <span class="loading loading-spinner loading-lg text-primary"></span>
        </div>

        <div wire:loading.remove wire:target="switchSort, navigateOffset, resetOffset">
            @if(empty($this->planners))
                {{-- Empty State --}}
                <div class="card bg-base-100 border border-base-content/5">
                    <div class="card-body items-center text-center py-16">
                        <div class="bg-base-200 rounded-full p-4 mb-2">
                            <x-heroicon-o-users class="size-10 text-base-content/25" />
                        </div>
                        <h3 class="text-lg font-semibold">No Planner Data Available</h3>
                        <p class="text-sm text-base-content/50 max-w-md">
                            Run <code class="badge badge-sm badge-ghost font-code">ws:export-planner-career</code> to populate planner career data.
                        </p>
                    </div>
                </div>
            @else
                @php $rows = array_chunk($this->planners, 3); @endphp
                <div class="space-y-3">
                    @foreach($rows as $row)
                        @php $rowHasExpanded = collect($row)->contains('username', $this->expandedPlanner); @endphp

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach($row as $planner)
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
                                        :pendingOverThreshold="$planner['pending_over_threshold'] ?? 0"
                                        :overallPercent="$planner['overall_percent'] ?? 0"
                                        :daysSinceLastEdit="$planner['days_since_last_edit'] ?? null"
                                        :streakWeeks="$planner['streak_weeks'] ?? 0"
                                        wire:click="toggleAccordion({{ \Illuminate\Support\Js::from($username) }})"
                                        :data-expanded="$isExpanded ? 'true' : null"
                                    />
                                </div>
                            @endforeach
                        </div>

                        {{-- Circuit detail panel --}}
                        @if($rowHasExpanded && $this->expandedPlanner)
                            <div
                                class="card bg-base-200/40 border border-base-content/6"
                                wire:key="accordion-{{ $this->expandedPlanner }}"
                            >
                                <div class="card-body p-4 gap-3">
                                    <div class="flex items-center justify-between">
                                        <h3 class="text-sm font-semibold flex items-center gap-2">
                                            <x-heroicon-m-map class="size-4 text-primary" />
                                            {{ collect($row)->firstWhere('username', $this->expandedPlanner)['display_name'] ?? '' }}
                                            <span class="text-base-content/40 font-normal">&mdash; Circuits</span>
                                        </h3>
                                        <button
                                            type="button"
                                            wire:click="toggleAccordion({{ \Illuminate\Support\Js::from($this->expandedPlanner) }})"
                                            class="btn btn-xs btn-ghost btn-circle"
                                            aria-label="Close circuit detail"
                                        >
                                            <x-heroicon-m-x-mark class="size-3.5" />
                                        </button>
                                    </div>
                                    @include('livewire.planner-metrics._circuit-accordion', [
                                        'circuits' => $this->expandedCircuits,
                                    ])
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-xs text-base-content/30 text-right tabular-nums" wire:loading.remove wire:target="switchSort, navigateOffset, resetOffset">
        Last updated: {{ now()->format('M j, Y g:i A') }}
    </div>
</div>
