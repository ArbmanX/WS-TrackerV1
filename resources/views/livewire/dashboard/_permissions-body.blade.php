{{-- Permissions Body — shared by mobile accordion and slide-out panel.
     Data sourced from $permissionsSystemWide defined in overview.blade.php.
     Scoped to active assessments with completed_miles > 0. --}}
<div class="p-3" x-data="{
    _ready: false,
    permissions: @js($permissionsSystemWide),
    init() { requestAnimationFrame(() => this._ready = true); },
    resolveHex(cssVar) {
        const value = getComputedStyle(document.documentElement).getPropertyValue(cssVar).trim();
        const c = document.createElement('canvas');
        c.width = c.height = 1;
        const ctx = c.getContext('2d');
        ctx.fillStyle = value;
        ctx.fillRect(0, 0, 1, 1);
        const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;
        return '#' + [r, g, b].map(n => n.toString(16).padStart(2, '0')).join('');
    },
    toRgba(hex, a) {
        const r = parseInt(hex.slice(1,3), 16);
        const g = parseInt(hex.slice(3,5), 16);
        const b = parseInt(hex.slice(5,7), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a + ')';
    },
    permColors() {
        if (!this._ready) return {};
        const p = this.resolveHex('--color-primary');
        const s = this.resolveHex('--color-secondary');
        return {
            'Approved':     p,
            'PPL Approved': s,
            'Pending':      this.resolveHex('--color-warning'),
            'No Contact':   this.resolveHex('--color-base-300'),
            'Refused':      this.resolveHex('--color-error'),
            'Deferred':     this.toRgba(this.resolveHex('--color-base-content'), 0.7),
        };
    },
    total() { return this.permissions.reduce((a, d) => a + d.count, 0); },
}">
    {{-- Total count --}}
    <div class="flex items-baseline justify-between mb-2">
        <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-base-content/60">Total Permits</p>
        <span class="text-sm font-bold font-code tabular-nums text-base-content" x-text="total().toLocaleString()"></span>
    </div>

    {{-- Stacked bar --}}
    <div class="flex h-2.5 w-full rounded-sm overflow-hidden mb-3">
        <template x-for="(seg, i) in permissions" :key="i">
            <div
                class="h-full relative group cursor-default"
                :style="`width: ${(seg.count / total()) * 100}%; background-color: ${Object.values(permColors())[i]}`"
            >
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2 py-1 rounded bg-base-300 text-[10px] font-code whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                    <span x-text="seg.status + ': ' + seg.count.toLocaleString()"></span>
                </div>
            </div>
        </template>
    </div>

    {{-- Status rows --}}
    <div class="flex flex-col gap-1.5">
        <template x-for="(seg, i) in permissions" :key="'prow'+i">
            <div class="flex items-center justify-between">
                <span class="flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm shrink-0" :style="`background-color: ${Object.values(permColors())[i]}`"></span>
                    <span class="text-[11px] text-base-content/70" x-text="seg.status"></span>
                </span>
                <span class="text-[11px] font-code font-bold tabular-nums text-base-content" x-text="seg.count.toLocaleString()"></span>
            </div>
        </template>
    </div>
</div>
