@props([
    'key',
    'label',
    'accent' => 'accent',
])

{{-- Slide-out panel tab + body. Must be inside a parent with x-data="{ panel: null }".
     The parent container must NOT have overflow-hidden so absolute panels can escape.
     Panel body positions itself relative to the nearest positioned ancestor (the
     fixed parent), so all panels open at the same vertical center.
     Props:
       key    — unique string matching the panel state (e.g. 'perm', 'work')
       label  — text displayed on the vertical tab
       accent — DaisyUI color for the left border (default: accent)
     Slot: the panel body content --}}
<div>
    <button
        class="flex items-center justify-center w-9 bg-base-100 border-l-[3px] border-l-{{ $accent }} rounded-l-lg shadow-lg cursor-pointer hover:bg-base-200 transition-colors"
        @click="panel = panel === '{{ $key }}' ? null : '{{ $key }}'"
    >
        <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-base-content/75 [writing-mode:vertical-lr] rotate-180 select-none py-5 px-1.5">{{ $label }}</span>
    </button>
    <div
        class="absolute right-9 top-1/2 -translate-y-1/2 bg-base-100 shadow-lg rounded-l-lg w-72 overflow-hidden transition-all duration-300 ease-in-out origin-right"
        :class="panel === '{{ $key }}' ? 'max-w-72 opacity-100' : 'max-w-0 opacity-0'"
    >
        <div class="w-72">
            {{ $slot }}
        </div>
    </div>
</div>
