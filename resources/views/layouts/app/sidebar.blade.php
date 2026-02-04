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
        <div class="min-h-screen flex flex-col">
            <!-- Minimal Header -->
            <header class="navbar bg-base-100 border-b border-base-300">
                <div class="flex-1">
                    <a href="{{ route('dashboard') }}" wire:navigate class="btn btn-ghost gap-2 text-xl font-semibold">
                        <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary">
                            <x-app-logo-icon class="size-5 fill-current text-primary-content" />
                        </span>
                        <span class="hidden sm:inline">WS-Tracker</span>
                    </a>
                </div>
                <div class="flex-none gap-2">
                    <!-- User Info -->
                    <div class="hidden sm:flex items-center gap-2 text-sm text-base-content/70">
                        <div class="avatar placeholder">
                            <div class="bg-neutral text-neutral-content rounded-full w-8">
                                <span class="text-xs">{{ auth()->user()->initials() }}</span>
                            </div>
                        </div>
                        <span>{{ auth()->user()->name }}</span>
                    </div>
                    <!-- Logout -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm" data-test="logout-button">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                            </svg>
                            <span class="hidden sm:inline">{{ __('Log Out') }}</span>
                        </button>
                    </form>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-6">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
