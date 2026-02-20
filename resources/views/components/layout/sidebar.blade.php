@props([
    'currentRoute' => null,
])

{{--
    Sidebar Navigation Component

    Role-keyed hub navigation. Each role gets a curated set of hub links
    defined in config/navigation.php. Settings is pinned to the bottom.

    Responsive behavior (via Alpine $store.sidebar):
    - Mobile (<768px): Drawer overlay, hamburger toggle
    - Tablet (768-1024px): Collapsed to icons only, expand on hover
    - Desktop (>1024px): Expanded, collapsible to icons

    Usage:
    <x-layout.sidebar :currentRoute="Route::currentRouteName()" />
--}}

@php
    $rolePriority = config('navigation.role_priority', []);
    $fallback = config('navigation.fallback_role', 'user');
    $userRole = $fallback;

    if (auth()->check()) {
        $userRoles = auth()->user()->getRoleNames();
        $userRole = collect($rolePriority)->first(fn ($role) => $userRoles->contains($role)) ?? $fallback;
    }

    $hubs = config("navigation.hubs.{$userRole}", []);
    $settings = config('navigation.settings');
@endphp

{{-- Sidebar Container --}}
<aside
    x-data
    class="nav-rail h-full bg-base-200 transition-all duration-300 flex flex-col"
    :class="$store.sidebar.widthClass"
    @mouseenter="$store.sidebar.hoverEnter()"
    @mouseleave="$store.sidebar.hoverLeave()"
>
    {{-- Logo --}}
    <div class="flex h-16 items-center gap-3 px-4 shrink-0">
        <a
            href="{{ route('dashboard') }}"
            wire:navigate
            class="flex items-center gap-3"
        >
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-primary text-primary-content">
                <x-ui.icon name="bolt" size="lg" variant="solid" />
            </div>
            <span
                x-show="$store.sidebar.showLabels"
                x-transition:enter="transition-opacity duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="text-lg font-bold tracking-tight"
            >
                WS-Tracker
            </span>
        </a>
    </div>

    <div class="divider my-0 mx-3"></div>

    {{-- Hub Links (scrollable) --}}
    <nav class="flex-1 overflow-y-auto px-3 py-3 custom-scrollbar" aria-label="Main navigation">
        <ul class="flex flex-col gap-1" role="list">
            @foreach($hubs as $hub)
                @php
                    $permission = $hub['permission'] ?? null;
                    $canAccess = !$permission || auth()->user()?->can($permission);
                    $routeExists = Route::has($hub['route']);
                    $active = $currentRoute === $hub['route'];
                    $disabled = !$routeExists;
                @endphp

                @if($canAccess)
                    <li>
                        @if($disabled)
                            {{-- Route not built yet — disabled hub --}}
                            <span
                                class="nav-hub-item nav-hub-disabled"
                                aria-disabled="true"
                            >
                                <x-ui.tooltip
                                    :text="$hub['label'] . ' — coming soon'"
                                    position="right"
                                    x-show="!$store.sidebar.showLabels"
                                >
                                    <x-ui.icon :name="$hub['icon']" size="md" />
                                </x-ui.tooltip>
                                <x-ui.icon
                                    :name="$hub['icon']"
                                    size="md"
                                    x-show="$store.sidebar.showLabels"
                                />
                                <span x-show="$store.sidebar.showLabels" class="truncate">
                                    {{ $hub['label'] }}
                                </span>
                            </span>
                        @else
                            {{-- Active hub link --}}
                            <a
                                href="{{ route($hub['route']) }}"
                                wire:navigate
                                @class([
                                    'nav-hub-item',
                                    'nav-hub-active' => $active,
                                ])
                                @if($active) aria-current="page" @endif
                            >
                                <x-ui.tooltip
                                    :text="$hub['label']"
                                    position="right"
                                    x-show="!$store.sidebar.showLabels"
                                >
                                    <x-ui.icon :name="$hub['icon']" size="md" />
                                </x-ui.tooltip>
                                <x-ui.icon
                                    :name="$hub['icon']"
                                    size="md"
                                    x-show="$store.sidebar.showLabels"
                                />
                                <span x-show="$store.sidebar.showLabels" class="truncate">
                                    {{ $hub['label'] }}
                                </span>
                            </a>
                        @endif
                    </li>
                @endif
            @endforeach
        </ul>
    </nav>

    {{-- Settings (pinned bottom) --}}
    @if($settings)
        <div class="shrink-0 border-t px-3 py-2" style="border-color: color-mix(in oklch, var(--color-base-content) 10%, transparent);">
            @php
                $settingsRouteExists = Route::has($settings['route']);
                $settingsActive = $currentRoute === $settings['route'];
            @endphp

            @if($settingsRouteExists)
                <a
                    href="{{ route($settings['route']) }}"
                    wire:navigate
                    @class([
                        'nav-hub-item',
                        'nav-hub-active' => $settingsActive,
                    ])
                >
                    <x-ui.tooltip
                        :text="$settings['label']"
                        position="right"
                        x-show="!$store.sidebar.showLabels"
                    >
                        <x-ui.icon :name="$settings['icon']" size="md" />
                    </x-ui.tooltip>
                    <x-ui.icon
                        :name="$settings['icon']"
                        size="md"
                        x-show="$store.sidebar.showLabels"
                    />
                    <span x-show="$store.sidebar.showLabels" class="truncate">
                        {{ $settings['label'] }}
                    </span>
                </a>
            @else
                <span class="nav-hub-item nav-hub-disabled" aria-disabled="true">
                    <x-ui.tooltip
                        :text="$settings['label'] . ' — coming soon'"
                        position="right"
                        x-show="!$store.sidebar.showLabels"
                    >
                        <x-ui.icon :name="$settings['icon']" size="md" />
                    </x-ui.tooltip>
                    <x-ui.icon
                        :name="$settings['icon']"
                        size="md"
                        x-show="$store.sidebar.showLabels"
                    />
                    <span x-show="$store.sidebar.showLabels" class="truncate">
                        {{ $settings['label'] }}
                    </span>
                </span>
            @endif
        </div>
    @endif

    {{-- Collapse Toggle (Desktop Only) --}}
    <div
        class="shrink-0 px-3 pb-3"
        x-show="$store.sidebar.breakpoint === 'desktop'"
    >
        <button
            type="button"
            @click="$store.sidebar.toggleCollapse()"
            class="nav-hub-item w-full justify-center opacity-60 hover:opacity-100"
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
            <span x-show="$store.sidebar.showLabels" class="truncate">
                Collapse
            </span>
        </button>
    </div>
</aside>
