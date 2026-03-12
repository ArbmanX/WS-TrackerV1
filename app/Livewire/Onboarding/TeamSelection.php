<?php

namespace App\Livewire\Onboarding;

use App\Models\Team;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class TeamSelection extends Component
{
    public array $teamNames = [];

    public function mount(): void
    {
        $user = Auth::user();
        $lastName = Str::afterLast($user->name, ' ');

        // Pre-populate with defaults if no teams exist yet
        $existingTeams = $user->teams()->pluck('name')->toArray();

        $this->teamNames = ! empty($existingTeams)
            ? $existingTeams
            : ["{$lastName}_A_Team", "{$lastName}_B_Team"];
    }

    /**
     * Add another team name slot.
     */
    public function addTeam(): void
    {
        $this->teamNames[] = '';
    }

    /**
     * Remove a team name slot.
     */
    public function removeTeam(int $index): void
    {
        if (count($this->teamNames) > 1) {
            unset($this->teamNames[$index]);
            $this->teamNames = array_values($this->teamNames);
        }
    }

    /**
     * Save teams and advance to next step.
     */
    public function continueToNext(): void
    {
        // Trim whitespace before validation
        $this->teamNames = array_map('trim', $this->teamNames);

        $this->validate([
            'teamNames' => ['required', 'array', 'min:1', 'max:10'],
            'teamNames.*' => ['required', 'string', 'min:3', 'max:100', 'distinct:ignore_case', 'regex:/^[a-zA-Z0-9_\- ]+$/'],
        ], [
            'teamNames.*.min' => 'Team name must be at least 3 characters.',
            'teamNames.*.distinct' => 'Team names must be unique.',
            'teamNames.*.regex' => 'Team names can only contain letters, numbers, spaces, hyphens, and underscores.',
        ]);

        $user = Auth::user();

        // Remove existing teams and recreate
        $user->teams()->delete();

        foreach ($this->teamNames as $name) {
            Team::create([
                'name' => $name,
                'owner_id' => $user->id,
            ]);
        }

        $user->settings()->update([
            'onboarding_step' => 4,
        ]);

        $this->redirect(route('onboarding.home-page'), navigate: true);
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
        return view('livewire.onboarding.team-selection');
    }
}
