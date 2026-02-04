@props([
    'label' => '',
    'value' => '',
    'suffix' => '',
    'icon' => null,
    'trend' => null,
    'trendLabel' => '',
    'color' => 'primary',
    'size' => 'md',
])

@php
    $sizeClasses = [
        'sm' => 'p-3',
        'md' => 'p-4',
        'lg' => 'p-6',
    ];
    $valueSize = [
        'sm' => 'text-xl',
        'md' => 'text-2xl',
        'lg' => 'text-3xl',
    ];
    $iconSize = [
        'sm' => 'size-6',
        'md' => 'size-8',
        'lg' => 'size-10',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'stat bg-base-100 rounded-box shadow ' . ($sizeClasses[$size] ?? $sizeClasses['md'])]) }}>
    @if($icon)
        <div class="stat-figure text-{{ $color }}">
            <x-dynamic-component :component="'heroicon-o-' . $icon" class="{{ $iconSize[$size] ?? $iconSize['md'] }}" />
        </div>
    @endif

    <div class="stat-title text-base-content/70">{{ $label }}</div>

    <div class="stat-value {{ $valueSize[$size] ?? $valueSize['md'] }} text-{{ $color }}">
        {{ $value }}@if($suffix)<span class="text-base font-normal text-base-content/60 ml-1">{{ $suffix }}</span>@endif
    </div>

    @if($trend !== null)
        <div class="stat-desc flex items-center gap-1 {{ $trend > 0 ? 'text-success' : ($trend < 0 ? 'text-error' : 'text-base-content/60') }}">
            @if($trend > 0)
                <x-heroicon-o-arrow-trending-up class="size-4" />
            @elseif($trend < 0)
                <x-heroicon-o-arrow-trending-down class="size-4" />
            @endif
            <span>{{ $trend > 0 ? '+' : '' }}{{ $trend }}%</span>
            @if($trendLabel)
                <span class="text-base-content/50">{{ $trendLabel }}</span>
            @endif
        </div>
    @endif
</div>
