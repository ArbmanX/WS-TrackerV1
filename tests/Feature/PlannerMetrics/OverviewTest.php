<?php

use App\Livewire\PlannerMetrics\Overview;
use App\Models\User;
use App\Models\UserSetting;
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

function mockUnifiedService(array $unifiedReturn = []): void
{
    $mock = Mockery::mock(PlannerMetricsServiceInterface::class);
    $mock->shouldReceive('getUnifiedMetrics')->andReturn($unifiedReturn);
    $mock->shouldReceive('getDefaultOffset')->andReturn(0);
    $mock->shouldReceive('getPeriodLabel')->andReturn('Feb 8 – Feb 14, 2026');
    app()->bind(PlannerMetricsServiceInterface::class, fn () => $mock);
}

function sampleUnifiedData(array $overrides = []): array
{
    return array_merge([
        'username' => 'jsmith',
        'display_name' => 'J Smith',
        'period_miles' => 4.3,
        'quota_target' => 6.5,
        'quota_percent' => 66.2,
        'streak_weeks' => 2,
        'gap_miles' => 2.2,
        'days_since_last_edit' => 4,
        'pending_over_threshold' => 2,
        'permission_breakdown' => ['Approved' => 30, 'Pending' => 8],
        'total_miles' => 45.2,
        'overall_percent' => 68.0,
        'active_assessment_count' => 3,
        'status' => 'warning',
        'circuits' => [],
    ], $overrides);
}

test('it renders overview page for authenticated user', function () {
    mockUnifiedService();

    $this->actingAs(createMetricsTestUser())
        ->get('/planner-metrics')
        ->assertOk()
        ->assertSee('Planner Metrics');
});

test('it redirects unauthenticated user to login', function () {
    $this->get('/planner-metrics')
        ->assertRedirect('/login');
});

test('it displays planner data in unified view', function () {
    mockUnifiedService([sampleUnifiedData()]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('J Smith')
        ->assertSee('4.3/6.5')
        ->assertSee('Progressing');
});

test('it shows status badge matching planner status', function () {
    mockUnifiedService([
        sampleUnifiedData(['status' => 'success']),
        sampleUnifiedData(['username' => 'bob', 'display_name' => 'Bob B', 'status' => 'error']),
    ]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('On Track')
        ->assertSee('Behind');
});

test('it shows empty state when no planner data exists', function () {
    mockUnifiedService();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('No Planner Data Available');
});

test('it shows correct progress bar fill percentage', function () {
    mockUnifiedService([sampleUnifiedData(['quota_percent' => 80.0])]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('80%');
});

test('it sorts cards alphabetically by default', function () {
    mockUnifiedService([
        sampleUnifiedData(['username' => 'charlie', 'display_name' => 'Charlie C']),
        sampleUnifiedData(['username' => 'alice', 'display_name' => 'Alice A']),
    ]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSeeInOrder(['Alice A', 'Charlie C']);
});

test('it sorts cards by gap_miles when sortBy=attention', function () {
    mockUnifiedService([
        sampleUnifiedData(['username' => 'small', 'display_name' => 'Small Gap', 'gap_miles' => 1.0]),
        sampleUnifiedData(['username' => 'big', 'display_name' => 'Big Gap', 'gap_miles' => 5.0]),
    ]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->call('switchSort', 'attention')
        ->assertSeeInOrder(['Big Gap', 'Small Gap']);
});

test('it shows planner initials on card', function () {
    mockUnifiedService([sampleUnifiedData(['display_name' => 'tgibson'])]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('TG');
});

test('it validates invalid sortBy param and falls back to default', function () {
    mockUnifiedService();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['sortBy' => 'evil'])
        ->assertSet('sortBy', 'alpha');
});

test('it persists sortBy in URL parameter', function () {
    mockUnifiedService([sampleUnifiedData()]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['sortBy' => 'attention'])
        ->assertSet('sortBy', 'attention');
});

test('it clamps positive offset to zero on mount', function () {
    mockUnifiedService();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class, ['offset' => 5])
        ->assertSet('offset', 0);
});

test('it does not have cardView or period URL parameters', function () {
    mockUnifiedService();

    $component = Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class);

    expect($component->instance())->not->toHaveProperty('cardView')
        ->and($component->instance())->not->toHaveProperty('period');
});

test('it does not render view toggle buttons', function () {
    mockUnifiedService([sampleUnifiedData()]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertDontSee("switchView('quota')")
        ->assertDontSee("switchView('health')");
});

test('it does not render period selector buttons', function () {
    mockUnifiedService([sampleUnifiedData()]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertDontSee("switchPeriod('week')")
        ->assertDontSee("switchPeriod('month')");
});

test('it displays stat cards when planners exist', function () {
    mockUnifiedService([
        sampleUnifiedData(['status' => 'success', 'quota_percent' => 110.0, 'period_miles' => 7.2, 'pending_over_threshold' => 0]),
        sampleUnifiedData(['username' => 'bob', 'status' => 'warning', 'quota_percent' => 50.0, 'period_miles' => 3.1, 'pending_over_threshold' => 5]),
    ]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('On Track')
        ->assertSee('1/2')
        ->assertSee('Team Avg')
        ->assertSee('Aging Units')
        ->assertSee('Team Miles');
});

test('it renders planner card with quota progress', function () {
    mockUnifiedService([sampleUnifiedData(['period_miles' => 5.2, 'quota_target' => 6.5, 'status' => 'warning'])]);

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSee('5.2/6.5')
        ->assertSee('Progressing');
});

test('it constrains layout to max-w-5xl', function () {
    mockUnifiedService();

    Livewire::actingAs(createMetricsTestUser())
        ->test(Overview::class)
        ->assertSeeHtml('max-w-5xl mx-auto');
});
