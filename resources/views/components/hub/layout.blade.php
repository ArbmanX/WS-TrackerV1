@props([
    'title' => '',
    'subtitle' => '',
])

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-base-content/60 mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>

        @if(isset($actions))
            <div class="flex items-center gap-3">
                {{ $actions }}
            </div>
        @endif
    </div>

    {{-- Optional Stat Cards Row --}}
    @if(isset($stats))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            {{ $stats }}
        </div>
    @endif

    {{-- Card Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{ $slot }}
    </div>
</div>
