<?php

namespace App\Livewire\Onboarding;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class ThemeSelection extends Component
{
    public string $selectedTheme = '';

    public function mount(): void
    {
        $this->selectedTheme = Auth::user()->settings?->theme
            ?? config('themes.default', 'corporate');
    }

    /**
     * When the theme selection changes, dispatch a browser event for live preview.
     */
    public function updatedSelectedTheme(string $value): void
    {
        $this->dispatch('set-theme', theme: $value);
    }

    /**
     * Save theme and advance to the next step.
     */
    public function continueToNext(): void
    {
        $user = Auth::user();

        $user->settings()->update([
            'theme' => $this->selectedTheme,
            'onboarding_step' => 2,
        ]);

        $this->redirect(route('onboarding.workstudio'), navigate: true);
    }

    /**
     * Go back to the password step.
     */
    public function goBack(): void
    {
        $this->redirect(route('onboarding.password'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.theme-selection');
    }
}
