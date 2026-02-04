@props([
    'regions' => collect(),
    'sortBy' => 'Region',
    'sortDir' => 'asc',
])

@php
    $sortIcon = $sortDir === 'asc' ? 'chevron-up' : 'chevron-down';
@endphp

<div class="overflow-x-auto">
    <table class="table table-zebra">
        <thead>
            <tr>
                <th>
                    <button type="button" class="btn btn-ghost btn-xs gap-1" wire:click="sort('Region')">
                        Region
                        @if($sortBy === 'Region')
                            <x-dynamic-component :component="'heroicon-o-' . $sortIcon" class="size-3" />
                        @endif
                    </button>
                </th>
                <th class="hidden md:table-cell text-center">
                    <button type="button" class="btn btn-ghost btn-xs gap-1" wire:click="sort('Active_Count')">
                        Active
                        @if($sortBy === 'Active_Count')
                            <x-dynamic-component :component="'heroicon-o-' . $sortIcon" class="size-3" />
                        @endif
                    </button>
                </th>
                <th class="text-right">
                    <button type="button" class="btn btn-ghost btn-xs gap-1" wire:click="sort('Total_Miles')">
                        Miles
                        @if($sortBy === 'Total_Miles')
                            <x-dynamic-component :component="'heroicon-o-' . $sortIcon" class="size-3" />
                        @endif
                    </button>
                </th>
                <th class="hidden lg:table-cell text-right">
                    <button type="button" class="btn btn-ghost btn-xs gap-1" wire:click="sort('Completed_Miles')">
                        Completed
                        @if($sortBy === 'Completed_Miles')
                            <x-dynamic-component :component="'heroicon-o-' . $sortIcon" class="size-3" />
                        @endif
                    </button>
                </th>
                <th class="text-center">% Complete</th>
                <th class="hidden xl:table-cell text-center">Planners</th>
                <th class="w-10"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($regions as $region)
                @php
                    $totalMiles = $region['Total_Miles'] ?? 0;
                    $completedMiles = $region['Completed_Miles'] ?? 0;
                    $percentComplete = $totalMiles > 0 ? ($completedMiles / $totalMiles) * 100 : 0;
                @endphp
                <tr class="hover cursor-pointer" wire:click="openPanel('{{ $region['Region'] }}')">
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex size-8 shrink-0 items-center justify-center rounded bg-primary/10 text-primary">
                                <x-heroicon-o-map-pin class="size-4" />
                            </div>
                            <div>
                                <div class="font-medium">{{ $region['Region'] }}</div>
                                <div class="text-xs text-base-content/60">{{ $region['Total_Circuits'] ?? 0 }} circuits</div>
                            </div>
                        </div>
                    </td>
                    <td class="hidden md:table-cell text-center">
                        <span class="badge badge-primary badge-sm">{{ $region['Active_Count'] ?? 0 }}</span>
                    </td>
                    <td class="text-right font-medium">
                        {{ number_format($totalMiles, 0) }}
                        <span class="text-xs text-base-content/60">mi</span>
                    </td>
                    <td class="hidden lg:table-cell text-right text-base-content/70">
                        {{ number_format($completedMiles, 0) }}
                    </td>
                    <td class="text-center">
                        <div class="flex flex-col items-center gap-1">
                            <span class="font-semibold {{ $percentComplete >= 75 ? 'text-success' : ($percentComplete >= 50 ? 'text-warning' : '') }}">
                                {{ number_format($percentComplete, 0) }}%
                            </span>
                            <progress
                                class="progress w-16 h-1.5 {{ $percentComplete >= 75 ? 'progress-success' : ($percentComplete >= 50 ? 'progress-warning' : 'progress-primary') }}"
                                value="{{ $percentComplete }}"
                                max="100"
                            ></progress>
                        </div>
                    </td>
                    <td class="hidden xl:table-cell text-center">
                        <div class="flex items-center justify-center gap-1">
                            <x-heroicon-o-users class="size-4 text-base-content/50" />
                            <span>{{ $region['Active_Planners'] ?? 0 }}</span>
                        </div>
                    </td>
                    <td>
                        <x-heroicon-o-chevron-right class="size-4 text-base-content/40" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-8 text-base-content/60">
                        <x-heroicon-o-map class="size-12 mx-auto mb-2 opacity-30" />
                        <p>No regions found</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
