<x-layouts::app.sidebar :title="$title ?? null">
    <div class="flex-1">
        {{ $slot }}
    </div>
</x-layouts::app.sidebar>
