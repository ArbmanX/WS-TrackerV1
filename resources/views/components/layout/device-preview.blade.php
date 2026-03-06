{{--
    Device Preview Drawer
    Debug-only component for previewing the app at different device viewport sizes.
    Drops down from the header area. Uses Alpine.js for toggle and resize behavior.
--}}

@if(config('app.debug'))
<div
    x-data="{
        open: false,
        active: null,
        previewWindow: null,
        devices: [
            { id: 'latitude-7030', name: 'Dell Latitude 7030', css: '1280x853',  w: 1280, h: 853,  dpr: 1.5, type: 'laptop' },
            { id: 'thinkpad-t16',  name: 'ThinkPad T16 Gen 1', css: '1920x1200', w: 1920, h: 1200, dpr: 1.0, type: 'laptop' },
            { id: 'surface-pro-7', name: 'Surface Pro 7',      css: '1368x912',  w: 1368, h: 912,  dpr: 2.0, type: 'tablet' },
            { id: 'surface-pro-9', name: 'Surface Pro 9',      css: '1440x960',  w: 1440, h: 960,  dpr: 2.0, type: 'tablet' },
            { id: 'android',       name: 'Android Phone',      css: '412x915',   w: 412,  h: 915,  dpr: 2.625, type: 'phone' },
            { id: 'iphone',        name: 'iPhone 14/15',       css: '390x844',   w: 390,  h: 844,  dpr: 3.0, type: 'phone' },
        ],
        apply(device) {
            this.active = device.id;
            if (this.previewWindow && !this.previewWindow.closed) {
                this.previewWindow.resizeTo(device.w, device.h);
            } else {
                this.previewWindow = window.open(
                    window.location.href,
                    'device-preview',
                    `width=${device.w},height=${device.h},menubar=no,toolbar=no,location=no,status=no,resizable=yes,scrollbars=yes`
                );
            }
        },
        reset() {
            this.active = null;
            if (this.previewWindow && !this.previewWindow.closed) {
                this.previewWindow.close();
            }
            this.previewWindow = null;
        },
    }"
    class="relative z-30"
>
    {{-- Toggle Button --}}
    <div class="flex justify-center">
        <button
            @click="open = !open"
            class="btn btn-xs btn-ghost gap-1 opacity-40 hover:opacity-100 transition-opacity font-code"
            :class="{ 'opacity-100 text-primary': open || active }"
        >
            <x-heroicon-o-device-tablet class="size-3.5" />
            <span x-text="active ? devices.find(d => d.id === active)?.name : 'Viewport'" class="text-[10px] tracking-wide uppercase"></span>
            <x-heroicon-m-chevron-down
                class="size-3 transition-transform"
                x-bind:class="{ 'rotate-180': open }"
            />
        </button>
    </div>

    {{-- Drawer Panel --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        @click.outside="open = false"
        class="absolute left-1/2 -translate-x-1/2 mt-1 bg-base-200 border border-base-300 rounded-box shadow-lg p-3 w-[640px] max-w-[95vw]"
    >
        <div class="grid grid-cols-3 gap-2">
            <template x-for="device in devices" :key="device.id">
                <button
                    @click="apply(device)"
                    class="flex items-start gap-2 p-2 rounded-box text-left transition-all border"
                    :class="active === device.id
                        ? 'border-primary bg-primary/10'
                        : 'border-transparent hover:bg-base-300/60'"
                >
                    <span class="mt-0.5 shrink-0 opacity-60">
                        <template x-if="device.type === 'phone'"><x-heroicon-o-device-phone-mobile class="size-4" /></template>
                        <template x-if="device.type === 'tablet'"><x-heroicon-o-device-tablet class="size-4" /></template>
                        <template x-if="device.type === 'laptop'"><x-heroicon-o-computer-desktop class="size-4" /></template>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-medium leading-tight truncate" x-text="device.name"></span>
                        <span class="block text-[10px] font-code opacity-50 leading-tight" x-text="'CSS ' + device.css"></span>
                        <span class="block text-[10px] font-code opacity-35 leading-tight" x-text="device.w + 'x' + device.h + ' @' + device.dpr + 'x'"></span>
                    </span>
                </button>
            </template>
        </div>

        {{-- Reset --}}
        <div class="mt-2 pt-2 border-t border-base-300 flex items-center justify-between">
            <span class="text-[10px] font-code opacity-40" x-show="active" x-text="'Simulating: ' + (active ? devices.find(d => d.id === active)?.css + ' CSS px' : '')"></span>
            <span x-show="!active" class="text-[10px] opacity-40">Select a device to constrain the viewport</span>
            <button
                @click="reset(); open = false"
                class="btn btn-xs btn-ghost gap-1 opacity-60 hover:opacity-100"
                :class="{ 'btn-primary': active }"
            >
                <x-heroicon-m-arrow-uturn-left class="size-3" />
                Reset
            </button>
        </div>
    </div>
</div>
@endif
