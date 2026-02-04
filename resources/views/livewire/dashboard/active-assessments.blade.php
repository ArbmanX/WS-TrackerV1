<div class="card bg-base-100 shadow h-full flex flex-col">
    {{-- Header --}}
    <div class="flex items-center justify-between p-4 pb-0">
        <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-warning/10 text-warning">
                <x-heroicon-o-clock class="size-5" />
            </div>
            <div>
                <h2 class="card-title text-base">Active Assessments</h2>
                <p class="text-xs text-base-content/60">Oldest checked-out circuits</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->assessments->isNotEmpty())
                <span class="badge badge-warning badge-sm font-semibold">{{ $this->assessments->count() }}</span>
            @endif
            <button
                type="button"
                class="btn btn-ghost btn-sm btn-square"
                wire:click="refresh"
                wire:loading.attr="disabled"
            >
                <x-heroicon-o-arrow-path class="size-4" wire:loading.class="animate-spin" wire:target="refresh" />
            </button>
        </div>
    </div>

    {{-- Assessment List --}}
    <div class="flex-1 overflow-y-auto min-h-0 p-4">
        @if ($this->assessments->isEmpty())
            <div class="text-center py-12">
                <x-heroicon-o-clipboard-document-list class="size-12 mx-auto mb-4 text-base-content/30" />
                <h3 class="text-lg font-medium mb-1">No Active Assessments</h3>
                <p class="text-base-content/60 text-sm">There are no active assessments to display.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach ($this->assessments as $assessment)
                    <x-dashboard.assessment-row
                        :assessment="$assessment"
                        wire:key="assessment-{{ $assessment['Work_Order'] ?? $loop->index }}"
                    />
                @endforeach
            </div>
        @endif
    </div>

    {{-- Loading Overlay --}}
    <div
        wire:loading.flex
        wire:target="refresh"
        class="absolute inset-0 z-20 items-center justify-center bg-base-100/50 rounded-box"
    >
        <span class="loading loading-spinner loading-md text-primary"></span>
    </div>
</div>
