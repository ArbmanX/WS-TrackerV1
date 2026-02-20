@props([
    'title' => '',
    'summary' => '',
    'icon' => 'squares-2x2',
    'href' => '#',
    'color' => 'primary',
    'metric' => null,
    'metricLabel' => '',
    'metricColor' => null,
    'disabled' => false,
])

@php
    $tag = $disabled ? 'div' : 'a';
    $linkAttrs = $disabled ? '' : 'href="' . $href . '" wire:navigate';

    $badgeColor = $metricColor ?? 'ghost';
    $formattedMetric = is_numeric($metric) && $metric >= 1000 ? number_format($metric) : $metric;
    $badgeText = $metricLabel ? "{$formattedMetric} {$metricLabel}" : $formattedMetric;
    $badgeHtml = '<span class="badge badge-sm badge-soft badge-' . e($badgeColor) . ' font-code tabular-nums">' . e($badgeText) . '</span>';
@endphp

<{{ $tag }}
    {{ $linkAttrs }}
    {{ $attributes->merge([
        'class' => 'hub-card group card card-border bg-base-100 shadow-sm'
            . ($disabled ? ' hub-card-disabled' : ''),
    ]) }}
    @if($disabled) aria-disabled="true" @endif
>
    <div class="card-body p-5 gap-3">
        {{-- Top row: icon + metric badge --}}
        <div class="flex items-start justify-between">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-{{ $color }}/10 text-{{ $color }} transition-colors duration-200 group-hover:bg-{{ $color }}/20">
                <x-ui.icon :name="$icon" size="md" />
            </div>

            @if($metric !== null)
                <div class="hub-card-badge">
                    {!! $badgeHtml !!}
                </div>
            @endif
        </div>

        {{-- Title + arrow --}}
        <div class="flex items-center gap-2">
            <h3 class="card-title text-base tracking-tight">{{ $title }}</h3>
            @unless($disabled)
                <x-heroicon-m-arrow-right class="size-4 text-base-content/0 transition-all duration-200 -translate-x-1 group-hover:text-base-content/40 group-hover:translate-x-0" />
            @endunless
        </div>

        {{-- Summary --}}
        @if($summary)
            <p class="text-sm text-base-content/60 leading-relaxed">{{ $summary }}</p>
        @endif

        {{-- Optional slot for extra content --}}
        @if($slot->isNotEmpty())
            <div class="pt-1">
                {{ $slot }}
            </div>
        @endif
    </div>
</{{ $tag }}>
