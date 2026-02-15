<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Planner Metrics</h1>
            <p class="text-base-content/60">Track planner footage quotas and assessment health</p>
        </div>
    </div>

    {{-- Controls Row --}}
    <div class="flex flex-wrap items-center gap-3">
        {{-- View Toggle --}}
        <div class="join">
            <button
                type="button"
                wire:click="switchView('quota')"
                @class(['btn btn-sm join-item', 'btn-primary' => $cardView === 'quota'])
            >
                Quota
            </button>
            <button
                type="button"
                wire:click="switchView('health')"
                @class(['btn btn-sm join-item', 'btn-primary' => $cardView === 'health'])
            >
                Health
            </button>
        </div>

        {{-- Period Toggle (quota view only) --}}
        @if($cardView === 'quota')
            <div class="join">
                @foreach(config('planner_metrics.periods', []) as $p)
                    <button
                        type="button"
                        wire:click="switchPeriod('{{ $p }}')"
                        @class(['btn btn-sm join-item', 'btn-primary' => $period === $p])
                    >
                        {{ str($p)->replace('-', ' ')->title() }}
                    </button>
                @endforeach
            </div>
        @endif

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

    {{-- Card Grid --}}
    <div class="relative">
        {{-- Loading Overlay --}}
        <div
            wire:loading.flex
            wire:target="switchView, switchPeriod, switchSort"
            class="absolute inset-0 z-10 items-center justify-center rounded-box bg-base-100/60"
        >
            <span class="loading loading-spinner loading-lg text-primary"></span>
        </div>

        <div wire:loading.remove wire:target="switchView, switchPeriod, switchSort">
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
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5 xl:gap-6">
                    @foreach($this->planners as $planner)
                        @if($cardView === 'health')
                            @include('livewire.planner-metrics._health-card', ['planner' => $planner])
                        @else
                            @include('livewire.planner-metrics._quota-card', [
                                'planner' => $planner,
                                'coachingMessage' => $this->coachingMessages[$planner['username']] ?? null,
                            ])
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Footer --}}
    <div class="text-xs text-base-content/40 text-right" wire:loading.remove wire:target="switchView, switchPeriod, switchSort">
        Last updated: {{ now()->format('M j, Y g:i A') }}
    </div>
</div>
