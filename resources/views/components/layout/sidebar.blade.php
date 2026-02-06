@props([
    'currentRoute' => null,
])

{{--
    Sidebar Navigation Component

    Responsive sidebar using DaisyUI drawer:
    - Mobile (<768px): Drawer overlay, hamburger toggle
    - Tablet (768-1024px): Collapsed to icons only
    - Desktop (>1024px): Expanded, collapsible to icons

    Uses Alpine.js $store.sidebar for state management.

    Usage:
    <x-layout.sidebar :currentRoute="Route::currentRouteName()" />
--}}

@php
    $navigation = [
        [
            'section' => 'Dashboard',
            'items' => [
                [
                    'label' => 'Overview',
                    'route' => 'dashboard',
                    'icon' => 'chart-bar',
                    'permission' => 'view-dashboard',
                ],
            ],
        ],
        [
            'section' => 'Data Management',
            'permission' => 'access-data-management',
            'items' => [
                [
                    'label' => 'Cache Controls',
                    'route' => 'data-management.cache',
                    'icon' => 'server-stack',
                    'permission' => 'access-data-management',
                ],
                [
                    'label' => 'Query Explorer',
                    'route' => 'data-management.query-explorer',
                    'icon' => 'code-bracket',
                    'permission' => 'execute-queries',
                ],
            ],
        ],
    ];

    // Helper to check if route is active
    $isActive = fn($route) => $currentRoute === $route;
@endphp

{{-- Sidebar Container --}}
<aside
    x-data
    class="h-full bg-base-200 transition-all duration-300"
    :class="$store.sidebar.widthClass"
    @mouseenter="$store.sidebar.hoverEnter()"
    @mouseleave="$store.sidebar.hoverLeave()"
>
    {{-- Logo Section --}}
    <div class="flex h-16 items-center gap-3 px-4">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="flex items-center gap-3"
        >
            {{-- Logo Icon --}}
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-content">
                <x-ui.icon name="bolt" size="lg" variant="solid" />
            </div>
            {{-- Logo Text (hidden when collapsed) --}}
            <span
                x-show="$store.sidebar.showLabels"
                x-transition:enter="transition-opacity duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="text-lg font-bold"
            >
                WS-Tracker
            </span>
        </a>
    </div>

    <div class="divider my-0 px-4"></div>

    {{-- Navigation Menu --}}
    <nav class="p-2">
        <ul class="menu menu-sm gap-1">
            @foreach($navigation as $section)
                @php
                    $sectionPermission = $section['permission'] ?? null;
                @endphp

                @if(!$sectionPermission || auth()->user()?->can($sectionPermission))
                    {{-- Section Title --}}
                    <li
                        x-show="$store.sidebar.showLabels"
                        class="menu-title mt-4 first:mt-0"
                    >
                        {{ $section['section'] }}
                    </li>

                    {{-- Section Items --}}
                    @foreach($section['items'] as $item)
                        @php
                            $itemPermission = $item['permission'] ?? null;
                        @endphp

                        @if(!$itemPermission || auth()->user()?->can($itemPermission))
                            @php
                                $active = $isActive($item['route'] ?? '');
                                $routeExists = Route::has($item['route'] ?? '');
                            @endphp

                            <li>
                                @if($routeExists)
                                    <a
                                        href="{{ route($item['route']) }}"
                                        wire:navigate
                                        @class([
                                            'flex items-center gap-3',
                                            'menu-active' => $active,
                                        ])
                                    >
                                        <x-ui.tooltip
                                            :text="$item['label']"
                                            position="right"
                                            x-show="!$store.sidebar.showLabels"
                                        >
                                            <x-ui.icon :name="$item['icon']" size="md" />
                                        </x-ui.tooltip>
                                        <x-ui.icon
                                            :name="$item['icon']"
                                            size="md"
                                            x-show="$store.sidebar.showLabels"
                                        />
                                        <span x-show="$store.sidebar.showLabels">
                                            {{ $item['label'] }}
                                        </span>
                                    </a>
                                @else
                                    {{-- Route doesn't exist yet - show disabled --}}
                                    <span class="flex items-center gap-3 opacity-50 cursor-not-allowed">
                                        <x-ui.icon :name="$item['icon']" size="md" />
                                        <span x-show="$store.sidebar.showLabels">
                                            {{ $item['label'] }}
                                        </span>
                                    </span>
                                @endif
                            </li>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </ul>
    </nav>

    {{-- Collapse Toggle (Desktop Only) --}}
    <div
        class="absolute bottom-4 left-0 right-0 px-2"
        x-show="$store.sidebar.breakpoint === 'desktop'"
    >
        <button
            type="button"
            @click="$store.sidebar.toggleCollapse()"
            class="btn btn-ghost btn-sm w-full justify-start gap-3"
        >
            <x-ui.icon
                name="chevron-double-left"
                size="md"
                x-show="!$store.sidebar.isCollapsed"
            />
            <x-ui.icon
                name="chevron-double-right"
                size="md"
                x-show="$store.sidebar.isCollapsed"
            />
            <span x-show="$store.sidebar.showLabels">
                Collapse
            </span>
        </button>
    </div>
</aside>
