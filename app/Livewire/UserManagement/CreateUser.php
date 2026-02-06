<?php

declare(strict_types=1);

namespace App\Livewire\UserManagement;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

#[Layout('components.layout.app-shell', [
    'title' => 'Create User',
    'breadcrumbs' => [
        ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'User Management'],
        ['label' => 'Create User'],
    ],
])]
class CreateUser extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = '';

    public bool $userCreated = false;

    public string $temporaryPassword = '';

    public string $createdUserName = '';

    public string $createdUserEmail = '';

    public function createUser(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);

        $this->temporaryPassword = Str::password(16);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->temporaryPassword,
        ]);

        $user->email_verified_at = now();
        $user->save();

        $user->assignRole($this->role);

        UserSetting::create([
            'user_id' => $user->id,
            'first_login' => true,
        ]);

        $this->createdUserName = $this->name;
        $this->createdUserEmail = $this->email;
        $this->userCreated = true;

        $this->reset('name', 'email', 'role');
    }

    public function createAnother(): void
    {
        $this->reset();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.user-management.create-user', [
            'availableRoles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
