@props([
    'items' => [],
])

{{--
    Breadcrumbs Component

    Navigation breadcrumbs with optional icons and wire:navigate support.

    Usage:
    <x-ui.breadcrumbs :items="[
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Overview', 'route' => 'dashboard.overview'],
        ['label' => 'Current Page'],  // No route = current page (non-clickable)
    ]" />

    With icons:
    <x-ui.breadcrumbs :items="[
        ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Settings'],
    ]" />

    With route params:
    <x-ui.breadcrumbs :items="[
        ['label' => 'Region', 'route' => 'regions.show', 'params' => ['region' => 1]],
    ]" />
--}}

<div {{ $attributes->merge(['class' => 'breadcrumbs text-sm']) }}>
    <ul>
        @foreach($items as $index => $item)
            <li>
                @php
                    $isLast = $index === count($items) - 1;
                    $hasRoute = isset($item['route']) && !$isLast;
                    $icon = $item['icon'] ?? null;
                @endphp

                @if($hasRoute)
                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" wire:navigate class="inline-flex items-center gap-1.5">
                        @if($icon)
                            <x-ui.icon :name="$icon" size="sm" />
                        @endif
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="inline-flex items-center gap-1.5">
                        @if($icon)
                            <x-ui.icon :name="$icon" size="sm" />
                        @endif
                        {{ $item['label'] }}
                    </span>
                @endif
            </li>
        @endforeach
    </ul>
</div>
