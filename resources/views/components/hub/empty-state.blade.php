@props([
    'icon' => 'inbox',
    'title' => 'Nothing here yet',
    'description' => '',
])

<div {{ $attributes->merge(['class' => 'card bg-base-100 shadow-sm']) }}>
    <div class="card-body items-center text-center py-16">
        <div class="flex size-16 items-center justify-center rounded-2xl bg-base-200 mb-4">
            <x-ui.icon :name="$icon" size="lg" class="text-base-content/30" />
        </div>
        <h3 class="text-lg font-semibold mb-1">{{ $title }}</h3>
        @if($description)
            <p class="text-base-content/60 max-w-sm">{{ $description }}</p>
        @endif
        @if($slot->isNotEmpty())
            <div class="mt-4">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
