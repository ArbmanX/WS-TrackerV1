@props([
    'title' => null,
    'breadcrumbs' => [],
])

{{--
    App Shell Layout

    Main application layout with:
    - Responsive sidebar (drawer on mobile, collapsible on desktop)
    - Fixed header with breadcrumbs
    - Theme system with localStorage persistence
    - Alpine.js stores for state management

    Usage:
    <x-layout.app-shell
        title="Dashboard"
        :breadcrumbs="[
            ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
            ['label' => 'Overview'],
        ]"
    >
        <!-- Page content -->
    </x-layout.app-shell>
--}}

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    x-data
    x-init="$store.theme?.init()"
    :data-theme="$store.theme?.effective || document.documentElement.getAttribute('data-theme') || 'corporate'"
>
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />

        <title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- FOUC Prevention - Runs before Alpine loads --}}
        <script>
            (function() {
                const storageKey = 'ws-theme';
                const defaultTheme = '{{ config('themes.default', 'corporate') }}';
                const savedTheme = localStorage.getItem(storageKey) || defaultTheme;

                let effectiveTheme = savedTheme;
                if (savedTheme === 'system') {
                    const systemMapping = @json(config('themes.system_mapping', ['light' => 'corporate', 'dark' => 'dark']));
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    effectiveTheme = prefersDark ? (systemMapping.dark || 'dark') : (systemMapping.light || 'corporate');
                }

                document.documentElement.setAttribute('data-theme', effectiveTheme);
            })();
        </script>

        <style>
            /* Smooth page transitions */
            .page-content {
                animation: fadeIn 0.2s ease-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(4px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Scrollbar styling */
            .custom-scrollbar::-webkit-scrollbar {
                width: 6px;
                height: 6px;
            }
            .custom-scrollbar::-webkit-scrollbar-track {
                background: oklch(var(--b2));
                border-radius: 3px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb {
                background: oklch(var(--bc) / 0.2);
                border-radius: 3px;
            }
            .custom-scrollbar::-webkit-scrollbar-thumb:hover {
                background: oklch(var(--bc) / 0.3);
            }
        </style>
    </head>

    <body class="min-h-screen bg-base-100">
        {{-- Drawer Container --}}
        <div
            class="drawer lg:drawer-open"
            x-data
            :class="{
                'drawer-open': $store.sidebar.isOpen,
            }"
        >
            {{-- Drawer Toggle (controlled by Alpine, not checkbox) --}}
            <input
                id="app-drawer"
                type="checkbox"
                class="drawer-toggle"
                x-model="$store.sidebar.isOpen"
            />

            {{-- Main Content Area --}}
            <div class="drawer-content flex flex-col min-h-screen">
                {{-- Header --}}
                <x-layout.header
                    :breadcrumbs="$breadcrumbs"
                    :title="$title"
                />

                {{-- Page Content --}}
                <main class="flex-1 p-4 lg:p-6 page-content custom-scrollbar">
                    {{ $slot }}
                </main>

                {{-- Footer (optional slot) --}}
                @isset($footer)
                    <footer class="p-4 lg:p-6 border-t border-base-200">
                        {{ $footer }}
                    </footer>
                @endisset
            </div>

            {{-- Sidebar Drawer --}}
            <div class="drawer-side z-40">
                {{-- Overlay (mobile) --}}
                <label
                    for="app-drawer"
                    aria-label="close sidebar"
                    class="drawer-overlay"
                    @click="$store.sidebar.close()"
                ></label>

                {{-- Sidebar Content --}}
                <x-layout.sidebar :currentRoute="Route::currentRouteName()" />
            </div>
        </div>
    </body>
</html>
