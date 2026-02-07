<?php

namespace App\Livewire\Onboarding;

use App\Services\WorkStudio\Client\ApiCredentialManager;
use App\Services\WorkStudio\Client\HeartbeatService;
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

    public string $ws_password = '';

    public bool $isValidating = false;

    public ?array $userDetails = null;

    public ?string $errorMessage = null;

    protected array $rules = [
        'ws_username' => ['required', 'string', 'regex:/^[A-Za-z0-9_]+\\\\[A-Za-z0-9_]+$/'],
        'ws_password' => ['required', 'string'],
    ];

    protected array $messages = [
        'ws_username.regex' => 'Username must be in DOMAIN\\username format (e.g., ASPLUNDH\\jsmith)',
    ];

    /**
     * Validate the WorkStudio credentials and username against the API.
     */
    public function validateWorkStudio(
        UserDetailsServiceInterface $userDetailsService,
        ApiCredentialManager $credentialManager,
        HeartbeatService $heartbeat,
    ): void {
        $this->validate();

        $this->isValidating = true;
        $this->errorMessage = null;
        $this->userDetails = null;

        try {
            // Check if the API server is responsive before attempting credential validation
            if (! $heartbeat->isAlive()) {
                $this->errorMessage = 'WorkStudio server is not responding. Please try again later.';
                $this->isValidating = false;

                return;
            }

            // Test credentials against the API
            if (! $credentialManager->testCredentials($this->ws_username, $this->ws_password)) {
                $this->errorMessage = 'Invalid WorkStudio credentials. Please check your username and password.';
                $this->isValidating = false;

                return;
            }

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

            // Store encrypted credentials
            $credentialManager->storeCredentials($user->id, $this->ws_username, $this->ws_password);

            // Advance onboarding step (completion happens in confirmation step)
            $user->settings()->update([
                'onboarding_step' => 3,
            ]);

            $this->redirect(route('onboarding.confirmation'), navigate: true);
        } catch (UserNotFoundException $e) {
            $this->errorMessage = 'User not found in WorkStudio. Please check your username and try again.';
        } catch (WorkStudioApiException $e) {
            $this->errorMessage = 'Unable to connect to WorkStudio. Please try again later.';
        } finally {
            $this->isValidating = false;
        }
    }

    /**
     * Go back to the theme selection step.
     */
    public function goBack(): void
    {
        $this->redirect(route('onboarding.theme'), navigate: true);
    }

    public function render()
    {
        return view('livewire.onboarding.work-studio-setup');
    }
}
