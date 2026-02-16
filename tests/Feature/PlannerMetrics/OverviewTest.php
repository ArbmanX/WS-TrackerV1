<?php

use App\Livewire\PlannerMetrics\Overview;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createMetricsTestUser(): User
{
    $user = User::factory()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    return $user;
}

function mockMetricsService(array $quotaReturn = [], array $healthReturn = []): void
{
    $mock = Mockery::mock(PlannerMetricsServiceInterface::class);
    $mock->shouldReceive('getQuotaMetrics')->andReturn($quotaReturn);
    $mock->shouldReceive('getHealthMetrics')->andReturn($healthReturn);
    $mock->shouldReceive('getDefaultOffset')->andReturn(0);
    $mock->shouldReceive('getPeriodLabel')->andReturn('Feb 8 – Feb 11, 2026');
    app()->bind(PlannerMetricsServiceInterface::class, fn () => $mock);
}

function mockCoachingGenerator(array $messages = []): void
{
    $mock = Mockery::mock(CoachingMessageGeneratorInterface::class);
    $mock->shouldReceive('generate')->andReturnUsing(
        fn ($p) => $messages[$p['username']] ?? null
    );
    app()->bind(CoachingMessageGeneratorInterface::class, fn () => $mock);
}

function sampleQuotaData(array $overrides = []): array
{
    return array_merge([
        'username' => 'jsmith',
        'display_name' => 'J Smith',
        'period_miles' => 4.3,
        'quota_target' => 6.5,
        'percent_complete' => 66.2,
        'streak_weeks' => 2,
        'last_week_miles' => 5.8,
        'days_since_last_edit' => 4,
        'active_assessment_count' => 3,
        'status' => 'warning',
        'gap_miles' => 2.2,
    ], $overrides);
}

function sampleHealthData(array $overrides = []): array
{
    return array_merge([
        'username' => 'jsmith',
        'display_name' => 'J Smith',
        'days_since_last_edit' => 4,
        'pending_over_threshold' => 2,
        'permission_breakdown' => ['Approved' => 30, 'Pending' => 8],
        'total_miles' => 45.2,
        'percent_complete' => 68.0,
        'active_assessment_count' => 3,
        'status' => 'warning',
    ], $overrides);
}

test('it renders overview page for authenticated user', function () {
    mockMetricsService();
    mockCoachingGenerator();

    $this->actingAs(createMetricsTestUser())
        ->get('/planner-metrics')
        ->assertOk()
        ->assertSee('Planner Metrics');
});

test('it redirects unauthenticated user to login', function () {
    $this->get('/planner-metrics')
        ->assertRedirect('/login');
});

test('it displays planner cards in quota view by default', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData()]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('J Smith')
        ->assertSee('4.3')
        ->assertSee('6.5 mi');
});

test('it toggles to health view when clicking health toggle', function () {
    mockMetricsService(
        quotaReturn: [sampleQuotaData()],
        healthReturn: [sampleHealthData(['days_since_last_edit' => 12])]
    );
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->call('switchView', 'health')
        ->assertSet('cardView', 'health')
        ->assertSee('12 days ago');
});

test('it persists cardView in URL parameter', function () {
    mockMetricsService(healthReturn: [sampleHealthData()]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['cardView' => 'health'])
        ->assertSet('cardView', 'health');
});

test('it persists period in URL parameter', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData()]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['period' => 'month'])
        ->assertSet('period', 'month');
});

test('it shows empty state when no planner data exists', function () {
    mockMetricsService();
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('No Planner Data Available');
});

test('it displays coaching message for behind-quota planner', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData(['status' => 'warning', 'gap_miles' => 2.0])]);
    mockCoachingGenerator(['jsmith' => "You're 2.0 mi away — a strong day gets you there."]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('2.0 mi away');
});

test('it shows correct progress bar fill percentage', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData(['percent_complete' => 80.0])]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('80%');
});

test('it sorts cards alphabetically by default', function () {
    mockMetricsService(quotaReturn: [
        sampleQuotaData(['username' => 'charlie', 'display_name' => 'Charlie C']),
        sampleQuotaData(['username' => 'alice', 'display_name' => 'Alice A']),
    ]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSeeInOrder(['Alice A', 'Charlie C']);
});

test('it sorts cards by gap_miles when sortBy=attention in quota view', function () {
    mockMetricsService(quotaReturn: [
        sampleQuotaData(['username' => 'small', 'display_name' => 'Small Gap', 'gap_miles' => 1.0]),
        sampleQuotaData(['username' => 'big', 'display_name' => 'Big Gap', 'gap_miles' => 5.0]),
    ]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->call('switchSort', 'attention')
        ->assertSeeInOrder(['Big Gap', 'Small Gap']);
});

test('it sorts cards by days_since_last_edit when sortBy=attention in health view', function () {
    mockMetricsService(healthReturn: [
        sampleHealthData(['username' => 'recent', 'display_name' => 'Recent R', 'days_since_last_edit' => 1]),
        sampleHealthData(['username' => 'stale', 'display_name' => 'Stale S', 'days_since_last_edit' => 10]),
    ]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->call('switchView', 'health')
        ->call('switchSort', 'attention')
        ->assertSeeInOrder(['Stale S', 'Recent R']);
});

test('it shows days_since_last_edit on quota cards', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData(['days_since_last_edit' => 8])]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('Last edit 8d ago');
});

test('it hides period toggle when in health view', function () {
    mockMetricsService(healthReturn: [sampleHealthData()]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->call('switchView', 'health')
        ->assertDontSee("switchPeriod('week')");
});

test('it validates invalid URL params via mount and falls back to defaults', function () {
    mockMetricsService();
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['cardView' => 'invalid', 'period' => 'bogus', 'sortBy' => 'evil'])
        ->assertSet('cardView', 'quota')
        ->assertSet('period', 'week')
        ->assertSet('sortBy', 'alpha');
});

test('it persists sortBy in URL parameter', function () {
    mockMetricsService(quotaReturn: [sampleQuotaData()]);
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['sortBy' => 'attention'])
        ->assertSet('sortBy', 'attention');
});

test('it clamps positive offset to zero on mount', function () {
    mockMetricsService();
    mockCoachingGenerator();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['offset' => 5])
        ->assertSet('offset', 0);
});
