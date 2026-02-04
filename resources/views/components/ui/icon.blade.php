@props([
    'name' => '',
    'size' => 'md',
    'variant' => 'outline',
])

{{--
    Icon Component

    Wrapper for Blade Heroicons with consistent sizing.

    Usage:
    <x-ui.icon name="home" />
    <x-ui.icon name="bolt" size="lg" variant="solid" />

    Sizes: xs (12px), sm (16px), md (20px), lg (24px), xl (32px)
    Variants: outline (default), solid, mini
--}}

@php
    $sizes = [
        'xs' => 'size-3',
        'sm' => 'size-4',
        'md' => 'size-5',
        'lg' => 'size-6',
        'xl' => 'size-8',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['md'];

    // Build the icon component name for Blade's dynamic component
    // Heroicons use: heroicon-o-{name} (outline), heroicon-s-{name} (solid), heroicon-m-{name} (mini)
    $prefix = match($variant) {
        'solid' => 'heroicon-s',
        'mini' => 'heroicon-m',
        default => 'heroicon-o',
    };
@endphp

<x-dynamic-component
    :component="$prefix . '-' . $name"
    {{ $attributes->class([$sizeClass]) }}
/>
