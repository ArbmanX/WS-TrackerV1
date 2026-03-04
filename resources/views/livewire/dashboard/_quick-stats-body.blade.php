{{-- Work Breakdown Body — shared by mobile accordion and slide-out panel.
     All data sourced from $summaryStats and $workTypeBreakdown defined in
     overview.blade.php. Scoped to active assessments with completed_miles > 0. --}}
@php
    // Top 8 work types by quantity — vertical stacked bar
    // Source: $workTypeBreakdown (active assessments, in-progress)
    $sortedWorkTypes = collect($workTypeBreakdown)->sortByDesc('total_qty')->take(8)->values()->all();

    // Ratio bars — proportion of first value vs total for each stat pair
    $trimTotal = $summaryStats['bucket_trim_miles'] + $summaryStats['manual_trim_miles'];
    $trimPct = $trimTotal > 0 ? ($summaryStats['bucket_trim_miles'] / $trimTotal) * 100 : 0;
    $brushTotal = $summaryStats['herbicide_acres'] + $summaryStats['hcb_acres'];
    $brushPct = $brushTotal > 0 ? ($summaryStats['herbicide_acres'] / $brushTotal) * 100 : 0;
    $remTotal = $summaryStats['rem_6_12_count'] + $summaryStats['rem_other_count'] + $summaryStats['vps_count'];
    $remPct = $remTotal > 0 ? (($summaryStats['rem_6_12_count'] + $summaryStats['rem_other_count']) / $remTotal) * 100 : 0;
@endphp
<div class="flex gap-3 p-3" x-data="{
    _ready: false,
    sorted: @js($sortedWorkTypes),
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
    wtColors() {
        if (!this._ready) return [];
        return [
            this.resolveHex('--color-primary'),
            this.resolveHex('--color-secondary'),
            this.resolveHex('--color-accent'),
            this.resolveHex('--color-warning'),
            this.resolveHex('--color-info'),
            this.resolveHex('--color-success'),
            this.resolveHex('--color-error'),
            this.toRgba(this.resolveHex('--color-base-content'), 0.65),
        ];
    },
    total() { return this.sorted.reduce((a, d) => a + d.total_qty, 0); },
}">
    {{-- Left: Vertical Work Types Bar
         Renders: $sortedWorkTypes — top 8 by total_qty, vertical segments.
         Source: active assessments, in-progress. Units vary by type. --}}
    <div class="w-5 shrink-0 flex flex-col rounded-sm overflow-hidden">
        <template x-for="(seg, i) in sorted" :key="i">
            <div
                class="w-full relative group cursor-default"
                :style="`height: ${(seg.total_qty / total()) * 100}%; background-color: ${wtColors()[i]}`"
            >
                <div class="absolute left-full ml-2 top-1/2 -translate-y-1/2 px-2 py-1 rounded bg-base-300 text-[10px] font-code whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10">
                    <span x-text="seg.label + ': ' + seg.total_qty.toLocaleString()"></span>
                </div>
            </div>
        </template>
    </div>

    {{-- Right: Stats --}}
    <div class="flex-1 min-w-0 flex flex-col justify-between gap-3">
        {{-- Trimming — Renders: bucket_trim_miles (MPB) vs manual_trim_miles (MPM)
             Units: miles. Ratio bar shows bucket proportion. --}}
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-base-content/60 mb-1">Trimming</p>
            <div class="flex justify-between items-baseline mb-1">
                <div>
                    <span class="text-[10px] text-base-content/55">bucket</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['bucket_trim_miles'], 0) }}</span>
                </div>
                <div>
                    <span class="text-[10px] text-base-content/55">manual</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['manual_trim_miles'], 0) }}</span>
                </div>
            </div>
            <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                <div class="h-full bg-primary rounded-full" style="width: {{ number_format($trimPct, 1) }}%"></div>
            </div>
        </div>

        {{-- Brush Acres — Renders: herbicide_acres (HERBA+HERBNA) vs hcb_acres (HCB)
             Units: acres. Ratio bar shows herbicide proportion. --}}
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-base-content/60 mb-1">Brush Acres</p>
            <div class="flex justify-between items-baseline mb-1">
                <div>
                    <span class="text-[10px] text-base-content/55">herbicide</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['herbicide_acres'], 0) }}</span>
                </div>
                <div>
                    <span class="text-[10px] text-base-content/55">hcb</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['hcb_acres'], 0) }}</span>
                </div>
            </div>
            <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                <div class="h-full bg-secondary rounded-full" style="width: {{ number_format($brushPct, 1) }}%"></div>
            </div>
        </div>

        {{-- Removals & VPS — Renders: rem count (REM612+REM1218+etc) vs vps_count (VPS)
             Units: each (count). Ratio bar shows removals proportion. --}}
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-base-content/60 mb-1">Removals & VPS</p>
            <div class="flex justify-between items-baseline mb-1">
                <div>
                    <span class="text-[10px] text-base-content/55">rem</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['rem_6_12_count'] + $summaryStats['rem_other_count']) }}</span>
                </div>
                <div>
                    <span class="text-[10px] text-base-content/55">vps</span>
                    <span class="text-sm font-bold font-code tabular-nums text-base-content ml-0.5">{{ number_format($summaryStats['vps_count']) }}</span>
                </div>
            </div>
            <div class="h-1.5 bg-base-200 rounded-full overflow-hidden">
                <div class="h-full bg-accent rounded-full" style="width: {{ number_format($remPct, 1) }}%"></div>
            </div>
        </div>
    </div>
</div>
