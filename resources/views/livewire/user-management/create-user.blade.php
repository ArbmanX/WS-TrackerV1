<div>
    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold">Create User</h1>
        <p class="text-base-content/60 text-sm mt-1">Create a new user account with a temporary password</p>
    </div>

    @if($userCreated)
        {{-- Success State --}}
        <div class="max-w-2xl alert alert-success mb-6 shadow-sm">
            <x-heroicon-o-check-circle class="size-5" />
            <div>
                <p class="font-semibold">User created successfully</p>
                <p class="text-sm">{{ $createdUserName }} ({{ $createdUserEmail }}) has been created.</p>
            </div>
        </div>

        {{-- Temporary Password Card --}}
        <div class="card bg-warning/10 border border-warning shadow-sm mb-6 max-w-2xl">
            <div class="card-body">
                <h2 class="card-title text-lg text-warning">
                    <x-heroicon-o-exclamation-triangle class="size-5" />
                    Temporary Password
                </h2>
                <p class="text-sm text-base-content/70">
                    Share this password securely with the user. They will be required to change it on first login.
                </p>
                <div class="mt-3 rounded-lg bg-base-200 p-4">
                    <code class="text-lg font-mono select-all">{{ $temporaryPassword }}</code>
                </div>
            </div>
        </div>

        {{-- Create Another --}}
        <button
            wire:click="createAnother"
            class="btn btn-primary"
        >
            <x-heroicon-o-user-plus class="size-5" />
            Create Another User
        </button>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            {{-- Form State --}}
            <div class="card bg-base-100 shadow-sm xl:col-span-2">
                <div class="card-body">
                    <h2 class="card-title text-lg">
                        <x-heroicon-o-user-plus class="size-5" />
                        New User Details
                    </h2>

                    {{-- Info Alert --}}
                    <div class="alert alert-info mt-2">
                        <x-heroicon-o-information-circle class="size-5" />
                        <span class="text-sm">A temporary password will be generated automatically. The user will be required to change it on first login.</span>
                    </div>

                    <form wire:submit="createUser" class="space-y-4 mt-4">
                        {{-- Name & Email — Two Columns --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Name --}}
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend font-medium">Full Name</legend>
                                <input
                                    id="name-input"
                                    type="text"
                                    wire:model="name"
                                    placeholder="John Smith"
                                    class="input w-full @error('name') input-error @enderror"
                                />
                                @error('name')
                                    <p class="label text-error">{{ $message }}</p>
                                @enderror
                            </fieldset>

                            {{-- Email --}}
                            <fieldset class="fieldset">
                                <legend class="fieldset-legend font-medium">Email Address</legend>
                                <input
                                    id="email-input"
                                    type="email"
                                    wire:model="email"
                                    placeholder="john@example.com"
                                    class="input w-full @error('email') input-error @enderror"
                                />
                                @error('email')
                                    <p class="label text-error">{{ $message }}</p>
                                @enderror
                            </fieldset>
                        </div>

                        {{-- Role --}}
                        <fieldset class="fieldset">
                            <legend class="fieldset-legend font-medium">Role</legend>
                            <select
                                id="role-select"
                                wire:model="role"
                                class="select w-full @error('role') select-error @enderror"
                            >
                                <option value="">-- Select a role --</option>
                                @foreach($availableRoles as $availableRole)
                                    <option value="{{ $availableRole }}">{{ ucwords(str_replace('-', ' ', $availableRole)) }}</option>
                                @endforeach
                            </select>
                            @error('role')
                                <p class="label text-error">{{ $message }}</p>
                            @enderror
                        </fieldset>

                        {{-- Submit --}}
                        <div class="mt-6">
                            <button
                                type="submit"
                                class="btn btn-primary btn-wide"
                                wire:loading.attr="disabled"
                                wire:target="createUser"
                            >
                                <span wire:loading.remove wire:target="createUser" class="inline-flex items-center gap-2">
                                    <x-heroicon-o-user-plus class="size-5" />
                                    Create User
                                </span>
                                <span wire:loading wire:target="createUser" class="inline-flex items-center gap-2">
                                    <span class="loading loading-spinner loading-sm"></span>
                                    Creating...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Users List (Mockup) --}}
            <div class="card bg-base-100 shadow-sm">
                <div class="card-body">
                    <div class="flex items-center justify-between">
                        <h2 class="card-title text-lg">
                            <x-heroicon-o-users class="size-5" />
                            Users
                            <span class="badge badge-ghost badge-sm">12</span>
                        </h2>
                        <div class="tooltip tooltip-left" data-tip="Coming soon">
                            <input
                                type="text"
                                placeholder="Filter users..."
                                class="input input-sm w-44"
                                disabled
                            />
                        </div>
                    </div>

                    {{-- User List --}}
                    <div class="mt-4 divide-y divide-base-200">
                        @php
                            $mockUsers = [
                                ['name' => 'Alice Johnson', 'email' => 'alice.johnson@example.com', 'role' => 'Manager', 'initials' => 'AJ'],
                                ['name' => 'Bob Martinez', 'email' => 'bob.martinez@example.com', 'role' => 'Planner', 'initials' => 'BM'],
                                ['name' => 'Carol Nguyen', 'email' => 'carol.nguyen@example.com', 'role' => 'Planner', 'initials' => 'CN'],
                                ['name' => 'David Park', 'email' => 'david.park@example.com', 'role' => 'General Foreman', 'initials' => 'DP'],
                                ['name' => 'Elena Rossi', 'email' => 'elena.rossi@example.com', 'role' => 'User', 'initials' => 'ER'],
                            ];
                        @endphp

                        @foreach($mockUsers as $mockUser)
                            <div class="flex items-center gap-3 py-3 px-2 rounded-lg cursor-pointer hover:bg-base-200 transition-colors">
                                {{-- Avatar --}}
                                <div class="avatar avatar-placeholder">
                                    <div class="bg-neutral text-neutral-content rounded-full w-9">
                                        <span class="text-xs font-medium">{{ $mockUser['initials'] }}</span>
                                    </div>
                                </div>

                                {{-- Info --}}
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate">{{ $mockUser['name'] }}</p>
                                    <p class="text-xs text-base-content/50 truncate">{{ $mockUser['email'] }}</p>
                                </div>

                                {{-- Role Badge --}}
                                <span class="badge badge-outline badge-sm whitespace-nowrap">{{ $mockUser['role'] }}</span>

                                {{-- Edit Arrow --}}
                                <x-heroicon-o-chevron-right class="size-4 text-base-content/30 shrink-0" />
                            </div>
                        @endforeach
                    </div>

                    {{-- Show More --}}
                    <div class="mt-3 text-center">
                        <div class="tooltip" data-tip="Coming soon — Edit Users page">
                            <button class="btn btn-ghost btn-sm gap-1 opacity-50 cursor-not-allowed" disabled>
                                View all 12 users
                                <x-heroicon-o-arrow-right class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
