@props([
    'breadcrumbs' => [],
    'title' => null,
])

{{--
    Header Component

    Top navigation bar with:
    - Mobile hamburger toggle (drawer trigger)
    - Breadcrumbs navigation
    - Search placeholder (disabled)
    - Notifications placeholder (disabled)
    - Theme toggle
    - User menu

    Usage:
    <x-layout.header
        :breadcrumbs="[
            ['label' => 'Dashboard', 'route' => 'dashboard'],
            ['label' => 'Overview'],
        ]"
        title="Overview"
    />
--}}

<header class="navbar sticky top-0 z-30 bg-base-100 shadow-sm">
    {{-- Left Section --}}
    <div class="navbar-start gap-2">
        {{-- Mobile Menu Toggle --}}
        <button
            type="button"
            class="btn btn-ghost btn-square lg:hidden"
            @click="$store.sidebar.toggle()"
            aria-label="Toggle navigation menu"
        >
            <x-ui.icon name="bars-3" size="lg" />
        </button>

        {{-- Desktop Collapse Toggle (when sidebar is collapsed) --}}
        <button
            type="button"
            class="btn btn-ghost btn-square hidden lg:flex"
            @click="$store.sidebar.toggleCollapse()"
            x-show="$store.sidebar.isCollapsed && !$store.sidebar.isHovering"
            aria-label="Expand sidebar"
        >
            <x-ui.icon name="bars-3" size="lg" />
        </button>

        {{-- Breadcrumbs --}}
        @if(count($breadcrumbs) > 0)
            <x-ui.breadcrumbs
                :items="$breadcrumbs"
                class="hidden sm:flex"
            />
        @endif
    </div>

    {{-- Center Section (Page Title on Mobile) --}}
    <div class="navbar-center sm:hidden">
        @if($title)
            <span class="text-lg font-semibold truncate max-w-[200px]">
                {{ $title }}
            </span>
        @endif
    </div>

    {{-- Right Section --}}
    <div class="navbar-end gap-1">
        {{-- Theme Toggle --}}
        <x-ui.theme-toggle />

        {{-- User Menu --}}
        <x-layout.user-menu />
    </div>
</header>
