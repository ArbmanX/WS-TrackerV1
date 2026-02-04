@props([
    'text' => '',
    'position' => 'top',
    'color' => null,
])

{{--
    Tooltip Component

    DaisyUI tooltip wrapper with positioning and color options.

    Usage:
    <x-ui.tooltip text="Helpful hint">
        <button>Hover me</button>
    </x-ui.tooltip>

    <x-ui.tooltip text="Error info" position="right" color="error">
        <span>!</span>
    </x-ui.tooltip>

    Positions: top (default), bottom, left, right
    Colors: primary, secondary, accent, info, success, warning, error
--}}

@php
    $positionClasses = [
        'top' => 'tooltip-top',
        'bottom' => 'tooltip-bottom',
        'left' => 'tooltip-left',
        'right' => 'tooltip-right',
    ];

    $colorClasses = [
        'primary' => 'tooltip-primary',
        'secondary' => 'tooltip-secondary',
        'accent' => 'tooltip-accent',
        'info' => 'tooltip-info',
        'success' => 'tooltip-success',
        'warning' => 'tooltip-warning',
        'error' => 'tooltip-error',
    ];

    $classes = collect([
        'tooltip',
        $positionClasses[$position] ?? 'tooltip-top',
        $color ? ($colorClasses[$color] ?? null) : null,
    ])->filter()->join(' ');
@endphp

<div
    class="{{ $classes }}"
    data-tip="{{ $text }}"
    {{ $attributes }}
>
    {{ $slot }}
</div>
