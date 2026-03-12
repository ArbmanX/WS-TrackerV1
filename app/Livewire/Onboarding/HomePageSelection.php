<?php

namespace App\Livewire\Onboarding;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class HomePageSelection extends Component
{
    public string $selectedPage = 'dashboard';

    /**
     * Available pages for home page selection.
     */
    public function getAvailablePagesProperty(): array
    {
        return [
            'dashboard' => [
                'name' => 'Dashboard',
                'description' => 'System overview with metrics and analytics',
                'icon' => 'chart-bar',
            ],
            'planner-metrics.overview' => [
                'name' => 'Planner Overview',
                'description' => 'Planner metrics and performance summary',
                'icon' => 'clipboard-document-list',
            ],
            'planner-metrics.production' => [
                'name' => 'Production',
                'description' => 'Production tracking and reports',
                'icon' => 'bolt',
            ],
            'assessments.index' => [
                'name' => 'Assessments',
                'description' => 'View and manage field assessments',
                'icon' => 'document-check',
            ],
        ];
    }

    public function mount(): void
    {
        $this->selectedPage = Auth::user()->settings?->home_page ?? 'dashboard';
    }

    /**
     * Save home page selection and advance.
     */
    public function continueToNext(): void
    {
        $user = Auth::user();

        $user->settings()->update([
            'home_page' => $this->selectedPage,
            'onboarding_step' => 5,
        ]);

        $this->redirect(route('onboarding.confirmation'), navigate: true);
    }

    /**
     * Go back to previous step.
     */
    public function goBack(): void
    {
        $user = Auth::user();

        // Go back to team selection if user has the right role, otherwise WS setup
        if ($user->hasAnyRole(['general-foreman', 'manager'])) {
            $this->redirect(route('onboarding.team-selection'), navigate: true);
        } else {
            $this->redirect(route('onboarding.workstudio'), navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.onboarding.home-page-selection');
    }
}
