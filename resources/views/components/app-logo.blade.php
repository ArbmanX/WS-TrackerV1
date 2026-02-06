@props([
    'sidebar' => false,
])

<a {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <span class="flex h-8 w-8 items-center justify-center rounded-md bg-primary">
        <x-app-logo-icon class="size-5 fill-current text-primary-content" />
    </span>
    <span class="font-semibold text-base-content">WS-Tracker</span>
</a>
