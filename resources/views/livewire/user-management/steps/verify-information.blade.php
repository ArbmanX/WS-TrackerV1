<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <h2 class="card-title text-lg">
            <x-heroicon-o-user class="size-5" />
            Verify Information
        </h2>

        <div class="alert alert-info mt-2">
            <x-heroicon-o-information-circle class="size-5" />
            <span class="text-sm">A temporary password will be generated automatically. The user will be required to change it on first login.</span>
        </div>

        {{-- Primary Credential Selector --}}
        @if(count($selectedWsUserIds) > 1)
            <div class="mt-4">
                <label class="label font-medium">Primary Credential</label>
                <div class="space-y-2">
                    @foreach($this->selectedWsUsers as $wsUser)
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-base-200 cursor-pointer hover:bg-base-200 transition-colors {{ $primaryWsUserId === $wsUser->id ? 'border-primary bg-primary/5' : '' }}">
                            <input
                                type="radio"
                                wire:click="setPrimary({{ $wsUser->id }})"
                                @checked($primaryWsUserId === $wsUser->id)
                                class="radio radio-primary radio-sm"
                            />
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium">{{ $wsUser->display_name ?? 'No Name' }}</p>
                                <p class="text-xs text-base-content/50">{{ $wsUser->username }}</p>
                            </div>
                            @if($primaryWsUserId === $wsUser->id)
                                <span class="badge badge-primary badge-sm">Primary</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>
        @elseif(count($selectedWsUserIds) === 1)
            <div class="mt-4">
                <div class="p-3 rounded-lg border border-base-200 bg-base-200/50">
                    @php $primary = $this->selectedWsUsers->first(); @endphp
                    <p class="text-sm font-medium">{{ $primary?->display_name ?? 'No Name' }}</p>
                    <p class="text-xs text-base-content/50">{{ $primary?->username }}</p>
                    <span class="badge badge-primary badge-sm mt-1">Primary</span>
                </div>
            </div>
        @endif

        {{-- Name & Email --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <fieldset class="fieldset">
                <legend class="fieldset-legend font-medium">Full Name</legend>
                <input
                    type="text"
                    wire:model="userName"
                    placeholder="John Smith"
                    class="input w-full @error('userName') input-error @enderror"
                />
                @error('userName')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset class="fieldset">
                <legend class="fieldset-legend font-medium">Email Address</legend>
                <input
                    type="email"
                    wire:model="userEmail"
                    placeholder="john@example.com"
                    class="input w-full @error('userEmail') input-error @enderror"
                />
                @error('userEmail')
                    <p class="label text-error">{{ $message }}</p>
                @enderror
            </fieldset>
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between mt-6">
            <button wire:click="previousStep" class="btn btn-ghost">
                <x-heroicon-o-arrow-left class="size-4" />
                Back
            </button>

            <button wire:click="nextStep" class="btn btn-primary">
                Next
                <x-heroicon-o-arrow-right class="size-4" />
            </button>
        </div>
    </div>
</div>
