{{--
    Component Preview Sandbox

    Dev-only page for previewing Blade components without auth.
    Access: /dev/preview (local environment only)

    To preview a specific component, add sections below with sample data.
    Theme switcher is included for testing across all DaisyUI themes.
--}}
<!DOCTYPE html>
<html
    lang="en"
    data-theme="corporate"
>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Component Preview - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600|fira-code:400,600" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        (function() {
            const saved = localStorage.getItem('ws-theme') || 'corporate';
            let effective = saved;
            if (saved === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                effective = prefersDark ? 'dark' : 'corporate';
            }
            document.documentElement.setAttribute('data-theme', effective);
            // Sync select after DOM ready
            document.addEventListener('DOMContentLoaded', () => {
                const sel = document.querySelector('select');
                if (sel) sel.value = effective;
            });
        })();
    </script>
</head>
<body class="min-h-screen bg-base-100 text-base-content">
    {{-- Top Bar --}}
    <div class="navbar bg-base-200 border-b border-base-300 px-6">
        <div class="flex-1">
            <span class="font-bold text-sm">Component Preview</span>
            <span class="badge badge-warning badge-sm ml-2">DEV ONLY</span>
        </div>
        <div class="flex-none flex items-center gap-2">
            {{-- Quick theme selector (plain JS — no Alpine/Livewire on this page) --}}
            <select
                class="select select-sm w-40"
                onchange="document.documentElement.setAttribute('data-theme', this.value); localStorage.setItem('ws-theme', this.value);"
            >
                @foreach(config('themes.categories') as $category)
                    <optgroup label="{{ $category['label'] }}">
                        @foreach($category['themes'] as $theme)
                            <option value="{{ $theme }}">{{ config("themes.available.{$theme}.name", $theme) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Preview Content --}}
    <main class="p-6 lg:p-10 max-w-6xl mx-auto space-y-10">

        {{-- ============================================================ --}}
        {{-- PLANNER CARD COMPONENT                                        --}}
        {{-- ============================================================ --}}
        <section>
            <h2 class="text-lg font-bold mb-1">Planner Card</h2>
            <p class="text-base-content/60 text-sm mb-4">
                <code class="badge badge-ghost badge-sm">&lt;x-planner.card&gt;</code>
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Example 1: Warning / Progressing --}}
                <x-planner.card
                    name="J. Morales"
                    initials="JM"
                    status="warning"
                    statusLabel="Progressing"
                    region="North Region"
                    :periodMiles="6.1"
                    :quotaTarget="6.5"
                    :dailyMiles="[
                        ['day' => 'Sun', 'miles' => 1.0],
                        ['day' => 'Mon', 'miles' => 1.4],
                        ['day' => 'Tue', 'miles' => 0.8],
                        ['day' => 'Wed', 'miles' => 1.5],
                        ['day' => 'Thu', 'miles' => 1.1],
                        ['day' => 'Fri', 'miles' => 0.3],
                        ['day' => 'Sat', 'miles' => 0],
                    ]"
                />

                {{-- Example 2: Success / On Track --}}
                <x-planner.card
                    name="T. Gibson"
                    initials="TG"
                    status="success"
                    statusLabel="On Track"
                    region="South Region"
                    :periodMiles="7.2"
                    :quotaTarget="6.5"
                    :dailyMiles="[
                        ['day' => 'Sun', 'miles' => 0],
                        ['day' => 'Mon', 'miles' => 1.8],
                        ['day' => 'Tue', 'miles' => 1.6],
                        ['day' => 'Wed', 'miles' => 1.4],
                        ['day' => 'Thu', 'miles' => 1.2],
                        ['day' => 'Fri', 'miles' => 1.2],
                        ['day' => 'Sat', 'miles' => 0],
                    ]"
                />

                {{-- Example 3: Error / Behind --}}
                <x-planner.card
                    name="R. Patel"
                    initials="RP"
                    status="error"
                    statusLabel="Behind"
                    region="East Region"
                    :periodMiles="2.1"
                    :quotaTarget="6.5"
                    :dailyMiles="[
                        ['day' => 'Sun', 'miles' => 0],
                        ['day' => 'Mon', 'miles' => 0.5],
                        ['day' => 'Tue', 'miles' => 0.8],
                        ['day' => 'Wed', 'miles' => 0.8],
                        ['day' => 'Thu', 'miles' => 0],
                        ['day' => 'Fri', 'miles' => 0],
                        ['day' => 'Sat', 'miles' => 0],
                    ]"
                />

                {{-- Example 4: Success / Exceeded --}}
                <x-planner.card
                    name="A. Chen"
                    initials="AC"
                    status="success"
                    statusLabel="Exceeded"
                    region="West Region"
                    :periodMiles="8.9"
                    :quotaTarget="6.5"
                    :dailyMiles="[
                        ['day' => 'Sun', 'miles' => 1.2],
                        ['day' => 'Mon', 'miles' => 2.1],
                        ['day' => 'Tue', 'miles' => 1.8],
                        ['day' => 'Wed', 'miles' => 1.5],
                        ['day' => 'Thu', 'miles' => 1.3],
                        ['day' => 'Fri', 'miles' => 1.0],
                        ['day' => 'Sat', 'miles' => 0],
                    ]"
                />
            </div>
        </section>

        {{-- ============================================================ --}}
        {{-- HUB LAYOUT + CARD COMPONENTS                                  --}}
        {{-- ============================================================ --}}
        <section>
            <h2 class="text-lg font-bold mb-1">Hub Layout + Cards</h2>
            <p class="text-base-content/60 text-sm mb-4">
                <code class="badge badge-ghost badge-sm">&lt;x-hub.layout&gt;</code>
                <code class="badge badge-ghost badge-sm ml-1">&lt;x-hub.card&gt;</code>
            </p>

            <x-hub.layout
                title="Operations Hub"
                subtitle="Regional assessment tracking and planner performance"
            >
                <x-slot:stats>
                    <x-ui.stat-card label="Active Assessments" value="1,247" icon="bolt" color="primary" size="sm" />
                    <x-ui.stat-card label="Total Miles" value="842" suffix="mi" icon="map" size="sm" />
                    <x-ui.stat-card label="Progress" value="68" suffix="%" icon="chart-bar" color="success" size="sm" />
                    <x-ui.stat-card label="Active Planners" value="14" icon="users" size="sm" />
                </x-slot:stats>

                <x-hub.card
                    title="Dashboard"
                    summary="Regional overview with circuit assessment progress across all regions."
                    icon="chart-bar"
                    href="#"
                    color="primary"
                    :metric="4"
                    metricLabel="regions"
                />

                <x-hub.card
                    title="Planner Metrics"
                    summary="Weekly planner performance, quota tracking, and daily production breakdowns."
                    icon="users"
                    href="#"
                    color="secondary"
                    :metric="3"
                    metricLabel="warnings"
                    metricColor="warning"
                />

                <x-hub.card
                    title="Assessments"
                    summary="Browse and search all circuit assessments with filtering and detail views."
                    icon="clipboard-document-list"
                    href="#"
                    color="accent"
                    :metric="1247"
                    metricLabel="total"
                />

                <x-hub.card
                    title="Monitoring"
                    summary="Real-time system health, ghost detection alerts, and live activity feeds."
                    icon="signal"
                    href="#"
                    :metric="2"
                    metricLabel="alerts"
                    metricColor="error"
                />
            </x-hub.layout>
        </section>

        {{-- ============================================================ --}}
        {{-- HUB CARD VARIANTS                                             --}}
        {{-- ============================================================ --}}
        <section>
            <h2 class="text-lg font-bold mb-1">Hub Card States</h2>
            <p class="text-base-content/60 text-sm mb-4">Disabled + Skeleton + Empty state</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-hub.card
                    title="Admin Panel"
                    summary="User management, roles, and system configuration."
                    icon="shield-check"
                    :disabled="true"
                />

                <x-hub.card-skeleton />
            </div>

            <div class="mt-4">
                <x-hub.empty-state
                    icon="map"
                    title="No Regions Available"
                    description="There are no active regions to display. Check back after data has been synced."
                />
            </div>
        </section>

        {{-- Add more component sections here as needed --}}

    </main>
</body>
</html>
