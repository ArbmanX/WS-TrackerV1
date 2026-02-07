<?php

namespace App\Livewire\Onboarding;

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class ChangePassword extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Set the new password for the user.
     */
    public function setPassword(): void
    {
        try {
            $this->validate([
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('password', 'password_confirmation');

            throw $e;
        }

        $user = Auth::user();

        $user->update([
            'password' => $this->password,
        ]);

        // Update settings to mark password as changed and advance step
        $user->settings()->updateOrCreate(
            ['user_id' => $user->id],
            ['first_login' => false, 'onboarding_step' => 1]
        );

        $this->redirect(route('onboarding.theme'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.change-password');
    }
}
