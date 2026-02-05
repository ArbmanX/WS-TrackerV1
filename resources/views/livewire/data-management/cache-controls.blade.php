<div>
    {{-- Flash Message --}}
    @if($flashMessage)
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, 5000)"
            x-show="show"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @class([
                'alert mb-6 shadow-sm',
                'alert-success' => $flashType === 'success',
                'alert-error' => $flashType === 'error',
                'alert-warning' => $flashType === 'warning',
            ])
        >
            @if($flashType === 'success')
                <x-heroicon-o-check-circle class="size-5" />
            @elseif($flashType === 'error')
                <x-heroicon-o-x-circle class="size-5" />
            @else
                <x-heroicon-o-exclamation-triangle class="size-5" />
            @endif
            <span>{{ $flashMessage }}</span>
        </div>
    @endif

    {{-- Header Row --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold">Cache Controls</h1>
            <p class="text-base-content/60 text-sm mt-1">Manage WorkStudio API data cache</p>
        </div>
        <div class="flex gap-2">
            <button
                wire:click="warmAll"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-sm"
            >
                <span wire:loading.remove wire:target="warmAll">
                    <x-heroicon-o-fire class="size-4" />
                </span>
                <span wire:loading wire:target="warmAll" class="loading loading-spinner loading-xs"></span>
                Warm Cache
            </button>
            <button
                wire:click="clearAll"
                wire:confirm="Are you sure you want to clear all cached data?"
                wire:loading.attr="disabled"
                class="btn btn-outline btn-error btn-sm"
            >
                <span wire:loading.remove wire:target="clearAll">
                    <x-heroicon-o-trash class="size-4" />
                </span>
                <span wire:loading wire:target="clearAll" class="loading loading-spinner loading-xs"></span>
                Clear All
            </button>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <x-ui.stat-card
            label="Cache Driver"
            :value="ucfirst($this->cacheDriver)"
            icon="server-stack"
            color="primary"
            size="sm"
        />
        <x-ui.stat-card
            label="Scope Year"
            :value="$this->scopeYear"
            icon="calendar"
            color="secondary"
            size="sm"
        />
        <x-ui.stat-card
            label="Datasets Cached"
            :value="$this->datasetsCached . '/' . $this->totalDatasets"
            icon="circle-stack"
            color="accent"
            size="sm"
        />
        <x-ui.stat-card
            label="Total Hits"
            :value="number_format($this->totalHits)"
            icon="bolt"
            color="info"
            size="sm"
        />
    </div>

    {{-- Dataset Table --}}
    <div class="bg-base-100 rounded-box shadow overflow-x-auto">
        <table class="table table-zebra w-full">
            <thead>
                <tr>
                    <th>Dataset</th>
                    <th>Status</th>
                    <th>Last Cached</th>
                    <th>TTL Remaining</th>
                    <th>Hits / Misses</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($this->cacheStatus as $key => $dataset)
                    <tr wire:key="dataset-{{ $key }}">
                        {{-- Dataset Name & Description --}}
                        <td>
                            <div class="font-medium">{{ $dataset['label'] }}</div>
                            <div class="text-xs text-base-content/50">{{ $dataset['description'] }}</div>
                            <div class="text-xs font-mono text-base-content/40 mt-0.5">{{ $dataset['key'] }}</div>
                        </td>

                        {{-- Status Badge --}}
                        <td>
                            @if($dataset['cached'])
                                <span class="badge badge-success badge-sm gap-1">
                                    <x-heroicon-s-check-circle class="size-3" />
                                    Cached
                                </span>
                            @elseif($dataset['cached_at'])
                                <span class="badge badge-warning badge-sm gap-1">
                                    <x-heroicon-s-clock class="size-3" />
                                    Expired
                                </span>
                            @else
                                <span class="badge badge-ghost badge-sm gap-1">
                                    <x-heroicon-s-minus-circle class="size-3" />
                                    Never Cached
                                </span>
                            @endif
                        </td>

                        {{-- Last Cached --}}
                        <td class="text-sm">
                            @if($dataset['cached_at'])
                                <span title="{{ $dataset['cached_at'] }}">
                                    {{ \Carbon\Carbon::parse($dataset['cached_at'])->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-base-content/40">&mdash;</span>
                            @endif
                        </td>

                        {{-- TTL Remaining --}}
                        <td>
                            @if($dataset['cached'] && $dataset['ttl_remaining'] !== null)
                                @php
                                    $pct = $dataset['ttl_seconds'] > 0
                                        ? round(($dataset['ttl_remaining'] / $dataset['ttl_seconds']) * 100)
                                        : 0;
                                @endphp
                                <div class="flex items-center gap-2">
                                    <progress
                                        class="progress progress-primary w-20"
                                        value="{{ $pct }}"
                                        max="100"
                                    ></progress>
                                    <span class="text-xs tabular-nums">
                                        {{ gmdate('H:i:s', $dataset['ttl_remaining']) }}
                                    </span>
                                </div>
                            @else
                                <span class="text-base-content/40 text-sm">&mdash;</span>
                            @endif
                        </td>

                        {{-- Hits / Misses --}}
                        <td class="text-sm tabular-nums">
                            <span class="text-success">{{ $dataset['hit_count'] }}</span>
                            <span class="text-base-content/40"> / </span>
                            <span class="text-warning">{{ $dataset['miss_count'] }}</span>
                        </td>

                        {{-- Refresh Button --}}
                        <td class="text-right">
                            <button
                                wire:click="refreshDataset('{{ $key }}')"
                                wire:loading.attr="disabled"
                                wire:target="refreshDataset('{{ $key }}')"
                                class="btn btn-ghost btn-xs"
                                title="Refresh {{ $dataset['label'] }}"
                            >
                                <span wire:loading.remove wire:target="refreshDataset('{{ $key }}')">
                                    <x-heroicon-o-arrow-path class="size-4" />
                                </span>
                                <span wire:loading wire:target="refreshDataset('{{ $key }}')" class="loading loading-spinner loading-xs"></span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Loading Overlay for bulk operations --}}
    <div
        wire:loading.flex
        wire:target="warmAll, clearAll"
        class="fixed inset-0 z-50 items-center justify-center bg-base-300/60 backdrop-blur-sm"
    >
        <div class="flex flex-col items-center gap-3 bg-base-100 rounded-box p-8 shadow-xl">
            <span class="loading loading-spinner loading-lg text-primary"></span>
            <span class="text-sm font-medium">Processing...</span>
        </div>
    </div>
</div>
