@props([
    'selected' => 'system',
])

{{--
    Theme Picker Component

    Visual theme selection grid with preview thumbnails.
    Designed for settings pages or modals where users can see
    theme previews before selecting.

    Usage:
    <x-ui.theme-picker :selected="$selectedTheme" />

    Note: This component uses wire:model.live for Livewire integration.
    The parent component should have a $selectedTheme property.
--}}

@php
    $themes = config('themes.available', []);
    $featuredThemes = ['corporate', 'light', 'dark'];
    $expandedThemes = ['cupcake', 'emerald', 'retro', 'garden', 'winter', 'autumn', 'silk', 'synthwave', 'cyberpunk', 'dracula', 'night', 'forest', 'coffee'];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-4']) }}>
    {{-- System Option --}}
    <label class="flex items-center gap-3 p-3 rounded-lg border-2 cursor-pointer transition-all
        {{ $selected === 'system' ? 'border-primary bg-primary/10' : 'border-base-300 hover:border-base-content/20' }}">
        <input
            type="radio"
            name="theme"
            value="system"
            class="radio radio-primary"
            {{ $selected === 'system' ? 'checked' : '' }}
            wire:model.live="selectedTheme"
        />
        <div>
            <span class="font-medium">Follow System</span>
            <p class="text-sm text-base-content/60">Automatically match your device settings</p>
        </div>
    </label>

    {{-- Featured Themes --}}
    <div class="grid grid-cols-3 gap-2">
        @foreach($featuredThemes as $theme)
            @if(isset($themes[$theme]))
                <label class="flex flex-col items-center gap-2 p-3 rounded-lg border-2 cursor-pointer transition-all
                    {{ $selected === $theme ? 'border-primary bg-primary/10' : 'border-base-300 hover:border-base-content/20' }}">
                    {{-- Theme Preview --}}
                    <div class="w-full h-12 rounded-md overflow-hidden" data-theme="{{ $theme }}">
                        <div class="h-full bg-base-100 flex items-center justify-center gap-1 p-1">
                            <div class="w-2 h-2 rounded-full bg-primary"></div>
                            <div class="w-2 h-2 rounded-full bg-secondary"></div>
                            <div class="w-2 h-2 rounded-full bg-accent"></div>
                        </div>
                    </div>
                    <input
                        type="radio"
                        name="theme"
                        value="{{ $theme }}"
                        class="radio radio-primary radio-sm"
                        {{ $selected === $theme ? 'checked' : '' }}
                        wire:model.live="selectedTheme"
                    />
                    <span class="text-xs">{{ $themes[$theme]['name'] }}</span>
                </label>
            @endif
        @endforeach
    </div>

    {{-- More Themes (Collapsible) --}}
    <div class="collapse collapse-arrow bg-base-200 rounded-lg">
        <input type="checkbox" />
        <div class="collapse-title text-sm font-medium">More themes</div>
        <div class="collapse-content">
            <div class="grid grid-cols-4 gap-2 pt-2">
                @foreach($expandedThemes as $theme)
                    @if(isset($themes[$theme]))
                        <label class="flex flex-col items-center gap-1 p-2 rounded cursor-pointer transition-all
                            {{ $selected === $theme ? 'bg-primary/20 ring-2 ring-primary' : 'hover:bg-base-300' }}">
                            {{-- Mini Preview --}}
                            <div class="w-full h-8 rounded overflow-hidden" data-theme="{{ $theme }}">
                                <div class="h-full bg-base-100 flex items-center justify-center gap-0.5">
                                    <div class="w-1.5 h-1.5 rounded-full bg-primary"></div>
                                    <div class="w-1.5 h-1.5 rounded-full bg-secondary"></div>
                                </div>
                            </div>
                            <input
                                type="radio"
                                name="theme"
                                value="{{ $theme }}"
                                class="hidden"
                                {{ $selected === $theme ? 'checked' : '' }}
                                wire:model.live="selectedTheme"
                            />
                            <span class="text-xs truncate w-full text-center">{{ $themes[$theme]['name'] }}</span>
                        </label>
                    @endif
                @endforeach
            </div>
        </div>
    </div>
</div>
