<?php

use App\Livewire\PlannerMetrics\Overview;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createDrawerTestUser(): User
{
    $user = User::factory()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    return $user;
}

function mockDrawerMetricsService(array $quotaReturn = [], array $healthReturn = []): void
{
    $mock = Mockery::mock(PlannerMetricsServiceInterface::class);
    $mock->shouldReceive('getQuotaMetrics')->andReturn($quotaReturn);
    $mock->shouldReceive('getHealthMetrics')->andReturn($healthReturn);
    $mock->shouldReceive('getDefaultOffset')->andReturn(0);
    $mock->shouldReceive('getPeriodLabel')->andReturn('Feb 8 – Feb 11, 2026');
    app()->bind(PlannerMetricsServiceInterface::class, fn () => $mock);
}

function mockDrawerCoachingGenerator(): void
{
    $mock = Mockery::mock(CoachingMessageGeneratorInterface::class);
    $mock->shouldReceive('generate')->andReturn(null);
    app()->bind(CoachingMessageGeneratorInterface::class, fn () => $mock);
}

function drawerQuotaData(array $overrides = []): array
{
    return array_merge([
        'username' => 'tgibson',
        'display_name' => 'tgibson',
        'period_miles' => 4.0,
        'quota_target' => 6.5,
        'percent_complete' => 61.5,
        'streak_weeks' => 1,
        'last_week_miles' => 5.0,
        'days_since_last_edit' => 2,
        'active_assessment_count' => 2,
        'status' => 'warning',
        'gap_miles' => 2.5,
        'circuits' => [
            [
                'job_guid' => '{11111111-1111-1111-1111-111111111111}',
                'line_name' => 'Circuit-1234',
                'region' => 'NORTH',
                'total_miles' => 15.5,
                'completed_miles' => 8.2,
                'percent_complete' => 52.9,
                'permission_breakdown' => ['Approved' => 25, 'Pending' => 10],
            ],
            [
                'job_guid' => '{22222222-2222-2222-2222-222222222222}',
                'line_name' => 'Circuit-5678',
                'region' => 'SOUTH',
                'total_miles' => 12.0,
                'completed_miles' => 6.0,
                'percent_complete' => 50.0,
                'permission_breakdown' => ['Approved' => 18, 'Refused' => 3],
            ],
        ],
    ], $overrides);
}

function drawerHealthData(array $overrides = []): array
{
    return array_merge([
        'username' => 'tgibson',
        'display_name' => 'tgibson',
        'days_since_last_edit' => 2,
        'pending_over_threshold' => 1,
        'permission_breakdown' => ['Approved' => 43, 'Pending' => 10],
        'total_miles' => 14.2,
        'percent_complete' => 51.5,
        'active_assessment_count' => 2,
        'status' => 'success',
        'circuits' => [
            [
                'job_guid' => '{11111111-1111-1111-1111-111111111111}',
                'line_name' => 'Circuit-1234',
                'region' => 'NORTH',
                'total_miles' => 15.5,
                'completed_miles' => 8.2,
                'percent_complete' => 52.9,
                'permission_breakdown' => ['Approved' => 25, 'Pending' => 10],
            ],
        ],
    ], $overrides);
}

// ─── State tests ─────────────────────────────────────────────────────────────

test('openDrawer sets drawerPlanner property', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSet('drawerPlanner', 'tgibson');
});

test('closeDrawer resets drawerPlanner to null', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->call('closeDrawer')
        ->assertSet('drawerPlanner', null);
});

test('switchView resets drawerPlanner to null', function () {
    mockDrawerMetricsService(
        quotaReturn: [drawerQuotaData()],
        healthReturn: [drawerHealthData()],
    );
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSet('drawerPlanner', 'tgibson')
        ->call('switchView', 'health')
        ->assertSet('drawerPlanner', null);
});

test('switchPeriod resets drawerPlanner to null', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSet('drawerPlanner', 'tgibson')
        ->call('switchPeriod', 'month')
        ->assertSet('drawerPlanner', null);
});

// ─── Computed property tests ─────────────────────────────────────────────────

test('drawerCircuits returns circuits for selected planner', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    $component = Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson');

    expect($component->get('drawerCircuits'))->toHaveCount(2);
});

test('drawerCircuits returns empty array when no planner selected', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    $component = Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class);

    expect($component->get('drawerCircuits'))->toBe([]);
});

test('drawerDisplayName returns correct name for selected planner', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    $component = Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson');

    expect($component->get('drawerDisplayName'))->toBe('tgibson');
});

// ─── Render tests ────────────────────────────────────────────────────────────

test('drawer renders circuit line name', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSee('Circuit-1234')
        ->assertSee('Circuit-5678');
});

test('drawer renders circuit region', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSee('NORTH')
        ->assertSee('SOUTH');
});

test('drawer renders miles', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSee('/ 15.5 mi');
});

test('drawer renders empty state when planner has zero circuits', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData(['circuits' => []])]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSee('No active circuits');
});

test('drawer does not render when drawerPlanner is null', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->assertDontSee("'s Circuits");
});

test('drawer panel is rendered when planner selected', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'tgibson')
        ->assertSeeHtml('data-drawer-panel');
});

test('drawer panel is not rendered when no planner selected', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->assertDontSeeHtml('data-drawer-panel');
});

test('openDrawer rejects non-existent username', function () {
    mockDrawerMetricsService(quotaReturn: [drawerQuotaData()]);
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('openDrawer', 'nonexistent_user')
        ->assertSet('drawerPlanner', null)
        ->assertDontSeeHtml('data-drawer-panel');
});

test('drawer works from health view', function () {
    mockDrawerMetricsService(
        quotaReturn: [drawerQuotaData()],
        healthReturn: [drawerHealthData()],
    );
    mockDrawerCoachingGenerator();

    Livewire::actingAs(createDrawerTestUser())
        ->test(Overview::class)
        ->call('switchView', 'health')
        ->call('openDrawer', 'tgibson')
        ->assertSee('Circuit-1234')
        ->assertSeeHtml("tgibson's Circuits");
});
