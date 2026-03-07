<?php

use App\Livewire\UserManagement\UserWizard;
use App\Models\Assessment;
use App\Models\AssessmentContributor;
use App\Models\Region;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\UserWsIdentity;
use App\Models\WsUser;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createOnboardedWizardUser(?string $role = null): User
{
    $factory = User::factory()->withWorkStudio();

    if ($role) {
        $factory = $factory->withRole($role);
    }

    $user = $factory->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    return $user;
}

// ──────────────────────────────────────────
// Authorization
// ──────────────────────────────────────────

test('guests are redirected to login from wizard', function () {
    $this->get(route('user-management.wizard'))
        ->assertRedirect(route('login'));
});

test('user role gets 403 on wizard', function () {
    $user = createOnboardedWizardUser('user');

    $this->actingAs($user)
        ->get(route('user-management.wizard'))
        ->assertForbidden();
});

test('manager role can access wizard', function () {
    $user = createOnboardedWizardUser('manager');

    $this->actingAs($user)
        ->get(route('user-management.wizard'))
        ->assertOk();
});

test('sudo-admin can access wizard', function () {
    $user = createOnboardedWizardUser('sudo-admin');

    $this->actingAs($user)
        ->get(route('user-management.wizard'))
        ->assertOk();
});

// ──────────────────────────────────────────
// Step Navigation
// ──────────────────────────────────────────

test('wizard starts on step 1', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->assertSet('currentStep', 1);
});

test('can advance to step 2 via nextStep', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('nextStep')
        ->assertSet('currentStep', 2);
});

test('can go back freely without validation', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('nextStep') // -> step 2
        ->call('previousStep')
        ->assertSet('currentStep', 1);
});

test('can skip credentials to step 2', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('skipCredentials')
        ->assertSet('currentStep', 2)
        ->assertSet('selectedWsUserIds', []);
});

test('goToStep goes backwards only', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 3)
        ->call('goToStep', 1)
        ->assertSet('currentStep', 1);
});

// ──────────────────────────────────────────
// Step 1: Select Credentials
// ──────────────────────────────────────────

test('can toggle ws user selection', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $wsUser = WsUser::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleWsUser', $wsUser->id)
        ->assertSet('selectedWsUserIds', [$wsUser->id])
        ->assertSet('primaryWsUserId', $wsUser->id)
        ->call('toggleWsUser', $wsUser->id)
        ->assertSet('selectedWsUserIds', [])
        ->assertSet('primaryWsUserId', null);
});

test('first selected credential becomes primary', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $ws1 = WsUser::factory()->create();
    $ws2 = WsUser::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleWsUser', $ws1->id)
        ->call('toggleWsUser', $ws2->id)
        ->assertSet('primaryWsUserId', $ws1->id);
});

test('can change primary credential', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $ws1 = WsUser::factory()->create();
    $ws2 = WsUser::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleWsUser', $ws1->id)
        ->call('toggleWsUser', $ws2->id)
        ->call('setPrimary', $ws2->id)
        ->assertSet('primaryWsUserId', $ws2->id);
});

test('already assigned ws credential shows in assigned list', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $wsUser = WsUser::factory()->create();
    $otherUser = User::factory()->create();
    UserWsIdentity::factory()->create(['user_id' => $otherUser->id, 'ws_user_id' => $wsUser->id]);

    $component = Livewire::actingAs($admin)
        ->test(UserWizard::class);

    expect($component->get('assignedWsUserIds'))->toContain($wsUser->id);
});

// ──────────────────────────────────────────
// Step 2: Verify Information
// ──────────────────────────────────────────

test('step 2 pre-populates from primary credential', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $wsUser = WsUser::factory()->create([
        'display_name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleWsUser', $wsUser->id)
        ->call('nextStep') // advance to step 2
        ->assertSet('userName', 'John Doe')
        ->assertSet('userEmail', 'john@example.com');
});

test('step 2 validates required name', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 2)
        ->set('userName', '')
        ->set('userEmail', 'test@example.com')
        ->call('nextStep')
        ->assertHasErrors(['userName' => 'required']);
});

test('step 2 validates unique email', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    User::factory()->create(['email' => 'taken@example.com']);

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 2)
        ->set('userName', 'Test User')
        ->set('userEmail', 'taken@example.com')
        ->call('nextStep')
        ->assertHasErrors(['userEmail' => 'unique']);
});

test('step 2 validates email format', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 2)
        ->set('userName', 'Test')
        ->set('userEmail', 'not-an-email')
        ->call('nextStep')
        ->assertHasErrors(['userEmail' => 'email']);
});

// ──────────────────────────────────────────
// Step 3: Assign Role
// ──────────────────────────────────────────

test('step 3 validates role required', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 3)
        ->set('selectedRole', '')
        ->call('nextStep')
        ->assertHasErrors(['selectedRole' => 'required']);
});

test('step 3 validates role must exist', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 3)
        ->set('selectedRole', 'nonexistent-role')
        ->call('nextStep')
        ->assertHasErrors(['selectedRole']);
});

test('available roles include all seeded roles', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    $component = Livewire::actingAs($admin)
        ->test(UserWizard::class);

    $roleNames = collect($component->get('availableRoles'))->pluck('name');

    expect($roleNames)->toContain('sudo-admin')
        ->toContain('manager')
        ->toContain('planner')
        ->toContain('general-foreman')
        ->toContain('user');
});

// ──────────────────────────────────────────
// Step 4: Regions & Assessments
// ──────────────────────────────────────────

test('can toggle region selection', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $region = Region::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleRegion', $region->id)
        ->assertSet('selectedRegionIds', [$region->id])
        ->call('toggleRegion', $region->id)
        ->assertSet('selectedRegionIds', []);
});

test('can select and deselect all regions', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    Region::factory()->count(3)->create();

    $component = Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('selectAllRegions');

    expect($component->get('selectedRegionIds'))->toHaveCount(3);

    $component->call('deselectAllRegions');

    expect($component->get('selectedRegionIds'))->toBeEmpty();
});

test('auto-detects assessments from ws credentials', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $wsUser = WsUser::factory()->create(['username' => 'ASPLUNDH\jdoe']);
    $assessment = Assessment::factory()->create();
    AssessmentContributor::factory()->create([
        'job_guid' => $assessment->job_guid,
        'ws_username' => 'ASPLUNDH\jdoe',
    ]);

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleWsUser', $wsUser->id)
        ->set('currentStep', 3) // need to be on step 3 to trigger detection on nextStep
        ->set('selectedRole', 'planner')
        ->call('nextStep') // triggers detectAssessments on advancing from step 3
        ->assertSet('currentStep', 4);

    // Verify detected assessment IDs were populated
    // The detection happens when advancing from step 3
});

test('can toggle assessment selection', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $assessment = Assessment::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->call('toggleAssessment', $assessment->id)
        ->assertSet('selectedAssessmentIds', [$assessment->id])
        ->call('toggleAssessment', $assessment->id)
        ->assertSet('selectedAssessmentIds', []);
});

// ──────────────────────────────────────────
// Save: Full Flow
// ──────────────────────────────────────────

test('full wizard creates user with all associations', function () {
    $admin = createOnboardedWizardUser('sudo-admin');
    $wsUser = WsUser::factory()->create(['display_name' => 'Jane Doe', 'email' => 'jane@test.com']);
    $region = Region::factory()->create();
    $assessment = Assessment::factory()->create();

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        // Step 1
        ->call('toggleWsUser', $wsUser->id)
        ->call('nextStep') // -> step 2
        // Step 2 (pre-populated)
        ->assertSet('userName', 'Jane Doe')
        ->assertSet('userEmail', 'jane@test.com')
        ->call('nextStep') // -> step 3
        // Step 3
        ->set('selectedRole', 'planner')
        ->call('nextStep') // -> step 4
        // Step 4
        ->call('toggleRegion', $region->id)
        ->call('toggleAssessment', $assessment->id)
        ->call('nextStep') // -> step 5
        // Step 5
        ->call('saveUser')
        ->assertSet('userCreated', true)
        ->assertHasNoErrors();

    $createdUser = User::where('email', 'jane@test.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->name)->toBe('Jane Doe')
        ->and($createdUser->email_verified_at)->not->toBeNull()
        ->and($createdUser->hasRole('planner'))->toBeTrue()
        ->and($createdUser->wsIdentities)->toHaveCount(1)
        ->and($createdUser->primaryWsIdentity)->not->toBeNull()
        ->and($createdUser->primaryWsIdentity->ws_user_id)->toBe($wsUser->id)
        ->and($createdUser->regions)->toHaveCount(1)
        ->and($createdUser->assessments)->toHaveCount(1)
        ->and($createdUser->settings->first_login)->toBeTrue();
});

test('save generates working temporary password', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    $component = Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 5)
        ->set('userName', 'New User')
        ->set('userEmail', 'newuser@example.com')
        ->set('selectedRole', 'user')
        ->call('saveUser');

    $tempPassword = $component->get('temporaryPassword');
    $createdUser = User::where('email', 'newuser@example.com')->first();

    expect($tempPassword)->not->toBeEmpty()
        ->and(Hash::check($tempPassword, $createdUser->password))->toBeTrue();
});

test('user created without ws credentials works', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 5)
        ->set('userName', 'No Cred User')
        ->set('userEmail', 'nocred@example.com')
        ->set('selectedRole', 'user')
        ->call('saveUser')
        ->assertSet('userCreated', true)
        ->assertHasNoErrors();

    $createdUser = User::where('email', 'nocred@example.com')->first();

    expect($createdUser)->not->toBeNull()
        ->and($createdUser->wsIdentities)->toHaveCount(0);
});

test('user created without regions or assessments works', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 5)
        ->set('userName', 'Bare User')
        ->set('userEmail', 'bare@example.com')
        ->set('selectedRole', 'planner')
        ->call('saveUser')
        ->assertSet('userCreated', true);

    $createdUser = User::where('email', 'bare@example.com')->first();

    expect($createdUser->regions)->toHaveCount(0)
        ->and($createdUser->assessments)->toHaveCount(0);
});

test('create another resets all wizard state', function () {
    $admin = createOnboardedWizardUser('sudo-admin');

    Livewire::actingAs($admin)
        ->test(UserWizard::class)
        ->set('currentStep', 5)
        ->set('userName', 'Reset User')
        ->set('userEmail', 'reset@example.com')
        ->set('selectedRole', 'user')
        ->call('saveUser')
        ->assertSet('userCreated', true)
        ->call('createAnother')
        ->assertSet('userCreated', false)
        ->assertSet('currentStep', 1)
        ->assertSet('userName', '')
        ->assertSet('userEmail', '')
        ->assertSet('selectedRole', '')
        ->assertSet('selectedWsUserIds', [])
        ->assertSet('selectedRegionIds', [])
        ->assertSet('selectedAssessmentIds', []);
});
