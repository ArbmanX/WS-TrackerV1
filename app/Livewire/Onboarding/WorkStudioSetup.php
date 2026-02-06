<?php

namespace App\Livewire\Onboarding;

use App\Services\WorkStudio\Shared\Contracts\UserDetailsServiceInterface;
use App\Services\WorkStudio\Shared\Exceptions\UserNotFoundException;
use App\Services\WorkStudio\Shared\Exceptions\WorkStudioApiException;
use App\Services\WorkStudio\Shared\Services\ResourceGroupAccessService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class WorkStudioSetup extends Component
{
    public string $ws_username = '';

    public bool $isValidating = false;

    public ?array $userDetails = null;

    public ?string $errorMessage = null;

    protected array $rules = [
        'ws_username' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+\\\\[A-Za-z0-9_]+$/'],
    ];

    protected array $messages = [
        'ws_username.regex' => 'Username must be in DOMAIN\\username format (e.g., ASPLUNDH\\jsmith)',
    ];

    /**
     * Validate the WorkStudio username against the API.
     */
    public function validateWorkStudio(UserDetailsServiceInterface $userDetailsService): void
    {
        $this->validate();

        $this->isValidating = true;
        $this->errorMessage = null;
        $this->userDetails = null;

        try {
            $this->userDetails = $userDetailsService->getDetails($this->ws_username);

            // Resolve resource groups from WS groups
            $resolvedRegions = app(ResourceGroupAccessService::class)
                ->resolveRegionsFromGroups($this->userDetails['groups'] ?? []);

            // Store the user details
            $user = Auth::user();

            $user->update([
                'ws_username' => $this->userDetails['username'],
                'ws_full_name' => $this->userDetails['full_name'],
                'ws_domain' => $this->userDetails['domain'],
                'ws_groups' => $this->userDetails['groups'],
                'ws_resource_groups' => $resolvedRegions,
                'ws_validated_at' => now(),
            ]);

            // Mark onboarding as complete
            $user->settings()->update([
                'onboarding_completed_at' => now(),
            ]);

            $this->redirect(route('dashboard'), navigate: true);
        } catch (UserNotFoundException $e) {
            $this->errorMessage = 'User not found in WorkStudio. Please check your username and try again.';
        } catch (WorkStudioApiException $e) {
            $this->errorMessage = 'Unable to connect to WorkStudio. Please try again later.';
        } finally {
            $this->isValidating = false;
        }
    }

    public function render()
    {
        return view('livewire.onboarding.work-studio-setup');
    }
}
