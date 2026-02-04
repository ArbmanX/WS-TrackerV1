@props([
    'label' => '',
    'value' => '',
    'icon' => null,
    'color' => 'base-content',
])

<div class="flex items-center gap-1.5 text-sm">
    @if($icon)
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="size-4 text-{{ $color }}" />
    @endif
    <span class="font-semibold">{{ $value }}</span>
    <span class="text-base-content/60">{{ $label }}</span>
</div>
