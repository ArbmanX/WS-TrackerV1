<div>
    @if($userCreated)
        {{-- Success State --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create User</h1>
            <p class="text-base-content/60 text-sm mt-1">User created successfully</p>
        </div>

        <div class="max-w-2xl alert alert-success mb-6 shadow-sm">
            <x-heroicon-o-check-circle class="size-5" />
            <div>
                <p class="font-semibold">User created successfully</p>
                <p class="text-sm">{{ $createdUserName }} ({{ $createdUserEmail }}) has been created.</p>
            </div>
        </div>

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

        <button wire:click="createAnother" class="btn btn-primary">
            <x-heroicon-o-user-plus class="size-5" />
            Create Another User
        </button>
    @else
        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create User</h1>
            <p class="text-base-content/60 text-sm mt-1">Multi-step user creation wizard</p>
        </div>

        {{-- Progress --}}
        <div class="mb-6">
            <x-user-management.wizard-progress :currentStep="$currentStep" />
        </div>

        {{-- Two Panel Layout --}}
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-6">
            {{-- Left Panel: Step Content --}}
            <div class="xl:col-span-3">
                @include("livewire.user-management.steps.{$this->stepView}")
            </div>

            {{-- Right Panel: Summary --}}
            <div class="xl:col-span-2">
                @include('livewire.user-management.partials.summary-panel')
            </div>
        </div>
    @endif
</div>
