<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        @include('partials.head')
        <script>
            // Theme initialization
            (function() {
                const theme = localStorage.getItem('theme') || 'system';
                if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>
    </head>
    <body class="min-h-screen bg-base-200">
        <!-- Navbar -->
        <div class="navbar bg-base-100 border-b border-base-300">
            <div class="navbar-start">
                <label for="mobile-drawer" class="btn btn-ghost btn-square lg:hidden">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="inline-block w-5 h-5 stroke-current">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </label>
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 px-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary">
                        <x-app-logo-icon class="size-5 fill-current text-primary-content" />
                    </span>
                    <span class="font-semibold text-base-content hidden sm:inline">Laravel Starter Kit</span>
                </a>
            </div>

            <div class="navbar-center hidden lg:flex">
                <ul class="menu menu-horizontal px-1">
                    <li>
                        <a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                            {{ __('Dashboard') }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="navbar-end">
                <div class="flex items-center gap-1">
                    <div class="tooltip tooltip-bottom" data-tip="{{ __('Search') }}">
                        <button class="btn btn-ghost btn-circle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </button>
                    </div>
                    <div class="tooltip tooltip-bottom hidden lg:block" data-tip="{{ __('Repository') }}">
                        <a href="https://github.com/laravel/livewire-starter-kit" target="_blank" class="btn btn-ghost btn-circle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z" />
                            </svg>
                        </a>
                    </div>
                    <div class="tooltip tooltip-bottom hidden lg:block" data-tip="{{ __('Documentation') }}">
                        <a href="https://laravel.com/docs/starter-kits#livewire" target="_blank" class="btn btn-ghost btn-circle">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                            </svg>
                        </a>
                    </div>
                    <x-desktop-user-menu />
                </div>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div class="drawer lg:hidden">
            <input id="mobile-drawer" type="checkbox" class="drawer-toggle" />
            <div class="drawer-side z-50">
                <label for="mobile-drawer" aria-label="close sidebar" class="drawer-overlay"></label>
                <aside class="bg-base-100 w-64 min-h-full">
                    <div class="p-4 border-b border-base-300">
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary">
                                <x-app-logo-icon class="size-5 fill-current text-primary-content" />
                            </span>
                            <span class="font-semibold">Laravel Starter Kit</span>
                        </a>
                    </div>
                    <nav class="p-4">
                        <div class="mb-2 px-2 text-xs font-semibold text-base-content/50 uppercase tracking-wider">
                            {{ __('Platform') }}
                        </div>
                        <ul class="menu gap-1 p-0">
                            <li>
                                <a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                                    </svg>
                                    {{ __('Dashboard') }}
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <div class="p-4 border-t border-base-300">
                        <ul class="menu gap-1 p-0">
                            <li>
                                <a href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                                    {{ __('Repository') }}
                                </a>
                            </li>
                            <li>
                                <a href="https://laravel.com/docs/starter-kits#livewire" target="_blank">
                                    {{ __('Documentation') }}
                                </a>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </div>

        {{ $slot }}
    </body>
</html>
