<?php

declare(strict_types=1);

namespace App\Livewire\UserManagement;

use App\Models\Assessment;
use App\Models\AssessmentContributor;
use App\Models\Region;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserWsIdentity;
use App\Models\WsUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
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
class UserWizard extends Component
{
    public int $currentStep = 1;

    // Step 1 — WS Credentials
    public array $selectedWsUserIds = [];

    public ?int $primaryWsUserId = null;

    // Step 1 — Search/filter
    public string $credentialSearch = '';

    public string $domainFilter = '';

    // Step 2 — User Info
    public string $userName = '';

    public string $userEmail = '';

    // Step 3 — Role
    public string $selectedRole = '';

    // Step 4 — Regions & Assessments
    public array $selectedRegionIds = [];

    public array $selectedAssessmentIds = [];

    public array $detectedAssessmentIds = [];

    public string $assessmentSearch = '';

    // Success state
    public bool $userCreated = false;

    public string $temporaryPassword = '';

    public string $createdUserName = '';

    public string $createdUserEmail = '';

    private const STEP_VIEWS = [
        1 => 'select-credentials',
        2 => 'verify-information',
        3 => 'assign-roles',
        4 => 'assign-regions-assessments',
        5 => 'review-save',
    ];

    public function nextStep(): void
    {
        $this->validateCurrentStep();

        if ($this->currentStep === 1) {
            $this->prepopulateFromCredentials();
        }

        if ($this->currentStep === 3) {
            $this->detectAssessments();
        }

        $this->currentStep = min($this->currentStep + 1, 5);
    }

    public function previousStep(): void
    {
        $this->currentStep = max($this->currentStep - 1, 1);
    }

    public function goToStep(int $step): void
    {
        if ($step < $this->currentStep) {
            $this->currentStep = $step;
        }
    }

    public function toggleWsUser(int $wsUserId): void
    {
        if (in_array($wsUserId, $this->selectedWsUserIds)) {
            $this->selectedWsUserIds = array_values(array_diff($this->selectedWsUserIds, [$wsUserId]));
            if ($this->primaryWsUserId === $wsUserId) {
                $this->primaryWsUserId = $this->selectedWsUserIds[0] ?? null;
            }
        } else {
            $this->selectedWsUserIds[] = $wsUserId;
            if ($this->primaryWsUserId === null) {
                $this->primaryWsUserId = $wsUserId;
            }
        }
    }

    public function setPrimary(int $wsUserId): void
    {
        if (in_array($wsUserId, $this->selectedWsUserIds)) {
            $this->primaryWsUserId = $wsUserId;
        }
    }

    public function skipCredentials(): void
    {
        $this->selectedWsUserIds = [];
        $this->primaryWsUserId = null;
        $this->currentStep = 2;
    }

    public function toggleRegion(int $regionId): void
    {
        if (in_array($regionId, $this->selectedRegionIds)) {
            $this->selectedRegionIds = array_values(array_diff($this->selectedRegionIds, [$regionId]));
        } else {
            $this->selectedRegionIds[] = $regionId;
        }
    }

    public function selectAllRegions(): void
    {
        $this->selectedRegionIds = Region::active()->pluck('id')->all();
    }

    public function deselectAllRegions(): void
    {
        $this->selectedRegionIds = [];
    }

    public function toggleAssessment(int $assessmentId): void
    {
        if (in_array($assessmentId, $this->selectedAssessmentIds)) {
            $this->selectedAssessmentIds = array_values(array_diff($this->selectedAssessmentIds, [$assessmentId]));
        } else {
            $this->selectedAssessmentIds[] = $assessmentId;
        }
    }

    public function detectAssessments(): void
    {
        if (empty($this->selectedWsUserIds)) {
            $this->detectedAssessmentIds = [];
            $this->selectedAssessmentIds = [];

            return;
        }

        $wsUsernames = WsUser::whereIn('id', $this->selectedWsUserIds)->pluck('username')->all();

        $assessmentIds = AssessmentContributor::whereIn('ws_username', $wsUsernames)
            ->distinct()
            ->pluck('job_guid')
            ->toArray();

        $this->detectedAssessmentIds = Assessment::whereIn('job_guid', $assessmentIds)
            ->pluck('id')
            ->all();

        // Pre-check detected assessments (merge with any manual selections)
        $this->selectedAssessmentIds = array_values(array_unique(
            array_merge($this->selectedAssessmentIds, $this->detectedAssessmentIds)
        ));
    }

    public function saveUser(): void
    {
        $this->validateCurrentStep();

        try {
            DB::transaction(function () {
                $this->temporaryPassword = Str::password(16);

                $user = User::create([
                    'name' => $this->userName,
                    'email' => $this->userEmail,
                    'password' => $this->temporaryPassword,
                ]);

                $user->email_verified_at = now();
                $user->save();

                $user->assignRole($this->selectedRole);

                // Link WS identities
                foreach ($this->selectedWsUserIds as $wsUserId) {
                    UserWsIdentity::create([
                        'user_id' => $user->id,
                        'ws_user_id' => $wsUserId,
                        'is_primary' => $wsUserId === $this->primaryWsUserId,
                    ]);
                }

                // Link regions
                if (! empty($this->selectedRegionIds)) {
                    $user->regions()->attach($this->selectedRegionIds);
                }

                // Link assessments
                if (! empty($this->selectedAssessmentIds)) {
                    $user->assessments()->attach($this->selectedAssessmentIds);
                }

                UserSetting::create([
                    'user_id' => $user->id,
                    'first_login' => true,
                ]);

                $this->createdUserName = $this->userName;
                $this->createdUserEmail = $this->userEmail;
                $this->userCreated = true;
            });
        } catch (\Throwable $e) {
            $this->addError('save', 'Failed to create user. Please try again.');

            throw $e;
        }
    }

    public function createAnother(): void
    {
        $this->reset();
    }

    #[Computed]
    public function selectedWsUsers()
    {
        if (empty($this->selectedWsUserIds)) {
            return collect();
        }

        return WsUser::whereIn('id', $this->selectedWsUserIds)->get();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::orderBy('name')->get()->map(fn ($role) => [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ]);
    }

    #[Computed]
    public function availableRegions()
    {
        return Region::active()->orderBy('display_name')->get();
    }

    #[Computed]
    public function filteredWsUsers()
    {
        $query = WsUser::query();

        if ($this->credentialSearch !== '') {
            $search = $this->credentialSearch;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($this->domainFilter !== '') {
            $query->where('domain', $this->domainFilter);
        }

        return $query->orderBy('display_name')->limit(100)->get();
    }

    #[Computed]
    public function availableDomains()
    {
        return WsUser::select('domain')->distinct()->orderBy('domain')->pluck('domain');
    }

    #[Computed]
    public function assignedWsUserIds()
    {
        return UserWsIdentity::pluck('ws_user_id')->all();
    }

    #[Computed]
    public function assignedWsUserMap()
    {
        return UserWsIdentity::with('user:id,name')
            ->get()
            ->pluck('user.name', 'ws_user_id')
            ->all();
    }

    #[Computed]
    public function searchedAssessments()
    {
        if ($this->assessmentSearch === '') {
            return collect();
        }

        $search = $this->assessmentSearch;

        return Assessment::where(function ($q) use ($search) {
            $q->where('work_order', 'like', "%{$search}%")
                ->orWhereHas('circuit', fn ($cq) => $cq->where('line_name', 'like', "%{$search}%"));
        })
            ->whereNotIn('id', $this->selectedAssessmentIds)
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function selectedAssessments()
    {
        if (empty($this->selectedAssessmentIds)) {
            return collect();
        }

        return Assessment::with('circuit:id,line_name')->whereIn('id', $this->selectedAssessmentIds)->get();
    }

    #[Computed]
    public function selectedRegions()
    {
        if (empty($this->selectedRegionIds)) {
            return collect();
        }

        return Region::whereIn('id', $this->selectedRegionIds)->orderBy('display_name')->get();
    }

    #[Computed]
    public function selectedRoleDetails()
    {
        if ($this->selectedRole === '') {
            return null;
        }

        $role = Role::where('name', $this->selectedRole)->first();

        if (! $role) {
            return null;
        }

        return [
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name')->all(),
        ];
    }

    public function getStepViewProperty(): string
    {
        return self::STEP_VIEWS[$this->currentStep] ?? 'select-credentials';
    }

    public function render(): View
    {
        return view('livewire.user-management.user-wizard');
    }

    private function validateCurrentStep(): void
    {
        match ($this->currentStep) {
            2 => $this->validate([
                'userName' => ['required', 'string', 'max:255'],
                'userEmail' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            ]),
            3 => $this->validate([
                'selectedRole' => ['required', 'string', Rule::exists('roles', 'name')],
            ]),
            default => null,
        };
    }

    private function prepopulateFromCredentials(): void
    {
        if ($this->primaryWsUserId && $this->userName === '' && $this->userEmail === '') {
            $primary = WsUser::find($this->primaryWsUserId);
            if ($primary) {
                $this->userName = $primary->display_name ?? '';
                $this->userEmail = $primary->email ?? '';
            }
        }
    }
}
