@props([
    'showLabel' => false,
    'compact' => false,
])

{{--
    Theme Toggle Component

    Dropdown theme selector using Alpine.js store for state management.
    Syncs with localStorage for instant theme application (no FOUC).

    Usage:
    <x-ui.theme-toggle />                    <!-- Icon only -->
    <x-ui.theme-toggle showLabel />          <!-- With current theme name -->
    <x-ui.theme-toggle compact />            <!-- Smaller for tight spaces -->
--}}

<div
    x-data="{
        open: false,
        themes: {{ Js::from(config('themes.categories', [])) }},
        allThemes: {{ Js::from(config('themes.available', [])) }},
    }"
    class="dropdown dropdown-end"
    @click.outside="open = false"
>
    {{-- Toggle Button --}}
    <button
        type="button"
        @click="open = !open"
        class="btn btn-ghost {{ $compact ? 'btn-sm btn-square' : 'btn-circle' }} swap swap-rotate"
        :class="{ 'swap-active': $store.theme.isDark }"
        aria-label="Toggle theme menu"
    >
        {{-- Sun icon (light mode) --}}
        <x-ui.icon name="sun" class="swap-off" :size="$compact ? 'sm' : 'md'" />

        {{-- Moon icon (dark mode) --}}
        <x-ui.icon name="moon" class="swap-on" :size="$compact ? 'sm' : 'md'" />
    </button>

    @if($showLabel)
        <span class="ml-2 text-sm" x-text="$store.theme.currentName"></span>
    @endif

    {{-- Dropdown Menu --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="dropdown-content z-50 mt-2 w-56 rounded-box bg-base-200 p-2 shadow-lg"
        @click.stop
    >
        {{-- System Option --}}
        <button
            type="button"
            @click="$store.theme.set('system'); open = false"
            class="btn btn-ghost btn-sm w-full justify-start gap-2"
            :class="{ 'btn-active': $store.theme.current === 'system' }"
        >
            <x-ui.icon name="computer-desktop" size="sm" />
            <span>System</span>
            <span
                x-show="$store.theme.current === 'system'"
                class="ml-auto text-success"
            >
                <x-ui.icon name="check" size="sm" />
            </span>
        </button>

        <div class="divider my-1 text-xs">Themes</div>

        {{-- Theme Categories --}}
        <template x-for="(category, key) in themes" :key="key">
            <div class="mb-2">
                <div class="px-2 py-1 text-xs font-semibold text-base-content/60" x-text="category.label"></div>
                <template x-for="themeName in category.themes" :key="themeName">
                    <button
                        type="button"
                        @click="$store.theme.set(themeName); open = false"
                        class="btn btn-ghost btn-sm w-full justify-start gap-2"
                        :class="{ 'btn-active': $store.theme.current === themeName }"
                    >
                        {{-- Color preview dots --}}
                        <span
                            class="flex gap-0.5"
                            :data-theme="themeName"
                        >
                            <span class="size-2 rounded-full bg-primary"></span>
                            <span class="size-2 rounded-full bg-secondary"></span>
                            <span class="size-2 rounded-full bg-accent"></span>
                        </span>
                        <span x-text="allThemes[themeName]?.name || themeName"></span>
                        <span
                            x-show="$store.theme.current === themeName"
                            class="ml-auto text-success"
                        >
                            <x-ui.icon name="check" size="sm" />
                        </span>
                    </button>
                </template>
            </div>
        </template>
    </div>
</div>
