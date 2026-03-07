<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <h2 class="card-title text-lg">
            <x-heroicon-o-identification class="size-5" />
            Select WS Credentials
            @if(count($selectedWsUserIds) > 0)
                <span class="badge badge-primary badge-sm">{{ count($selectedWsUserIds) }} selected</span>
            @endif
        </h2>

        <p class="text-sm text-base-content/60 mt-1">
            Select one or more WorkStudio credentials to associate with this user.
        </p>

        {{-- Search & Filter --}}
        <div class="flex flex-col sm:flex-row gap-3 mt-4">
            <input
                type="text"
                wire:model.live.debounce.300ms="credentialSearch"
                placeholder="Search by name, username, or email..."
                class="input input-bordered flex-1"
            />
            <select wire:model.live="domainFilter" class="select select-bordered w-full sm:w-48">
                <option value="">All Domains</option>
                @foreach($this->availableDomains as $domain)
                    <option value="{{ $domain }}">{{ $domain }}</option>
                @endforeach
            </select>
        </div>

        {{-- Credentials List --}}
        <div class="mt-4 max-h-96 overflow-y-auto divide-y divide-base-200 border border-base-200 rounded-lg">
            @forelse($this->filteredWsUsers as $wsUser)
                @php
                    $isAssigned = in_array($wsUser->id, $this->assignedWsUserIds);
                    $isSelected = in_array($wsUser->id, $selectedWsUserIds);
                    $assignedTo = $this->assignedWsUserMap[$wsUser->id] ?? null;
                @endphp
                <label
                    class="flex items-center gap-3 py-3 px-4 transition-colors
                        {{ $isAssigned && !$isSelected ? 'opacity-50 cursor-not-allowed' : 'hover:bg-base-200 cursor-pointer' }}
                        {{ $isSelected ? 'bg-primary/5' : '' }}"
                    @if($isAssigned && !$isSelected)
                        data-tip="Assigned to {{ $assignedTo }}"
                        class="tooltip"
                    @endif
                >
                    <input
                        type="checkbox"
                        wire:click="toggleWsUser({{ $wsUser->id }})"
                        @checked($isSelected)
                        @disabled($isAssigned && !$isSelected)
                        class="checkbox checkbox-primary checkbox-sm"
                    />

                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">{{ $wsUser->display_name ?? 'No Name' }}</p>
                        <p class="text-xs text-base-content/50 truncate">{{ $wsUser->username }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($wsUser->email)
                            <span class="text-xs text-base-content/40 truncate max-w-32 hidden sm:inline">{{ $wsUser->email }}</span>
                        @endif

                        @if($wsUser->is_enabled)
                            <span class="badge badge-success badge-xs">Active</span>
                        @else
                            <span class="badge badge-error badge-xs">Disabled</span>
                        @endif

                        @if($isAssigned && !$isSelected)
                            <div class="tooltip tooltip-left" data-tip="Assigned to {{ $assignedTo }}">
                                <x-heroicon-o-lock-closed class="size-4 text-base-content/30" />
                            </div>
                        @endif
                    </div>
                </label>
            @empty
                <div class="py-8 text-center text-base-content/50">
                    <x-heroicon-o-magnifying-glass class="size-8 mx-auto mb-2 opacity-40" />
                    <p class="text-sm">No credentials found</p>
                </div>
            @endforelse
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between mt-6">
            <button wire:click="skipCredentials" class="btn btn-ghost btn-sm">
                Skip &mdash; create without credentials
            </button>

            <button wire:click="nextStep" class="btn btn-primary">
                Next
                <x-heroicon-o-arrow-right class="size-4" />
            </button>
        </div>
    </div>
</div>
