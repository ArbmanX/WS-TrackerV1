<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <h2 class="card-title text-lg">
            <x-heroicon-o-shield-check class="size-5" />
            Assign Role
        </h2>

        <p class="text-sm text-base-content/60 mt-1">
            The user's role determines their app permissions. Select one role.
        </p>

        @error('selectedRole')
            <div class="alert alert-error mt-2">
                <x-heroicon-o-exclamation-circle class="size-5" />
                <span class="text-sm">{{ $message }}</span>
            </div>
        @enderror

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
            @foreach($this->availableRoles as $role)
                <label
                    wire:click="$set('selectedRole', '{{ $role['name'] }}')"
                    class="card card-compact border-2 cursor-pointer transition-all hover:shadow-md
                        {{ $selectedRole === $role['name'] ? 'border-primary bg-primary/5 shadow-md' : 'border-base-200' }}"
                >
                    <div class="card-body">
                        <div class="flex items-center gap-2">
                            <input
                                type="radio"
                                @checked($selectedRole === $role['name'])
                                class="radio radio-primary radio-sm"
                            />
                            <h3 class="font-semibold">{{ ucwords(str_replace('-', ' ', $role['name'])) }}</h3>
                        </div>

                        @if(!empty($role['permissions']))
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($role['permissions'] as $permission)
                                    <span class="badge badge-ghost badge-xs">{{ $permission }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </label>
            @endforeach
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
