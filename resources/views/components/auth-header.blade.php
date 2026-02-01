@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-bold text-base-content">{{ $title }}</h1>
    <p class="text-sm text-base-content/70">{{ $description }}</p>
</div>
