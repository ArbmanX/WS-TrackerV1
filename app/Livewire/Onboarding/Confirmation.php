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

        $themeKey = $settings?->theme ?? config('themes.default', 'ppl-light');
        $themeName = config("themes.available.{$themeKey}.name", ucfirst($themeKey));

        $homePage = $settings?->home_page ?? 'dashboard';

        $this->summary = [
            'name' => $user->name,
            'email' => $user->email,
            'theme' => $themeName,
            'home_page' => $homePage,
            'ws_username' => $user->ws_username,
            'ws_full_name' => $user->ws_full_name,
            'ws_domain' => $user->ws_domain,
            'regions' => $user->ws_resource_groups ?? [],
            'teams' => $user->teams()->pluck('name')->toArray(),
        ];
    }

    /**
     * Confirm onboarding and redirect to chosen home page.
     */
    public function confirm(): void
    {
        $user = Auth::user();
        $settings = $user->settings;

        $settings->update([
            'onboarding_step' => 6,
            'onboarding_completed_at' => now(),
        ]);

        $homePage = $settings->home_page ?? 'dashboard';
        $this->redirect(route($homePage), navigate: true);
    }

    /**
     * Go back to the home page selection step.
     */
    public function goBack(): void
    {
        $this->redirect(route('onboarding.home-page'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.confirmation');
    }
}
