<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    :data-theme="$store.theme?.effective ?? 'corporate'"
    data-theme="corporate"
    x-data
    x-init="$store.theme?.init()"
>
    <head>
        @include('partials.head')
        <script>
            (function() {
                const stored = localStorage.getItem('ws-theme') || 'system';
                const systemMapping = @json(config('themes.system_mapping'));
                let theme;
                if (stored === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    theme = prefersDark ? (systemMapping.dark || 'dark') : (systemMapping.light || 'corporate');
                } else {
                    theme = stored;
                }
                document.documentElement.setAttribute('data-theme', theme);
            })();
        </script>
    </head>
    <body class="min-h-screen bg-base-200 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-md flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-base-content" />
                    </span>

                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                <div class="flex flex-col gap-6">
                    <div class="card bg-base-100 shadow-xl">
                        <div class="card-body">{{ $slot }}</div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
