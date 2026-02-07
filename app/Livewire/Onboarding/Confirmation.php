<?php

namespace App\Livewire\Onboarding;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class Confirmation extends Component
{
    public array $summary = [];

    public function mount(): void
    {
        $user = Auth::user();
        $settings = $user->settings;

        $themeKey = $settings?->theme ?? config('themes.default', 'corporate');
        $themeName = config("themes.available.{$themeKey}.name", ucfirst($themeKey));

        $this->summary = [
            'name' => $user->name,
            'email' => $user->email,
            'theme' => $themeName,
            'ws_username' => $user->ws_username,
            'ws_full_name' => $user->ws_full_name,
            'ws_domain' => $user->ws_domain,
            'regions' => $user->ws_resource_groups ?? [],
        ];
    }

    /**
     * Confirm onboarding and redirect to dashboard.
     */
    public function confirm(): void
    {
        $user = Auth::user();

        $user->settings()->update([
            'onboarding_step' => 4,
            'onboarding_completed_at' => now(),
        ]);

        $this->redirect(route('dashboard'), navigate: true);
    }

    /**
     * Go back to the WorkStudio credentials step.
     */
    public function goBack(): void
    {
        $this->redirect(route('onboarding.workstudio'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.confirmation');
    }
}
