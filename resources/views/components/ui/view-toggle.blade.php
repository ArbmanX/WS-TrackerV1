@props([
    'current' => 'cards',
    'size' => 'sm',
])

@php
    $sizeClass = match($size) {
        'xs' => 'btn-xs',
        'sm' => 'btn-sm',
        'lg' => 'btn-lg',
        default => '',
    };
@endphp

<div {{ $attributes->merge(['class' => 'join']) }} role="group" aria-label="View toggle">
    <button
        type="button"
        wire:click="$set('viewMode', 'cards')"
        @class(['join-item btn', $sizeClass, 'btn-active' => $current === 'cards'])
        aria-pressed="{{ $current === 'cards' ? 'true' : 'false' }}"
    >
        <x-heroicon-o-squares-2x2 class="size-4" />
        <span class="sr-only sm:not-sr-only sm:ml-1">Cards</span>
    </button>
    <button
        type="button"
        wire:click="$set('viewMode', 'table')"
        @class(['join-item btn', $sizeClass, 'btn-active' => $current === 'table'])
        aria-pressed="{{ $current === 'table' ? 'true' : 'false' }}"
    >
        <x-heroicon-o-table-cells class="size-4" />
        <span class="sr-only sm:not-sr-only sm:ml-1">Table</span>
    </button>
</div>
