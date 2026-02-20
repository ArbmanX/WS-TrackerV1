<?php

use App\Livewire\PlannerMetrics\Overview;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function createAccordionTestUser(): User
{
    $user = User::factory()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    return $user;
}

function mockAccordionService(array $unifiedReturn = []): void
{
    $mock = Mockery::mock(PlannerMetricsServiceInterface::class);
    $mock->shouldReceive('getUnifiedMetrics')->andReturn($unifiedReturn);
    $mock->shouldReceive('getDefaultOffset')->andReturn(0);
    $mock->shouldReceive('getPeriodLabel')->andReturn('Feb 8 – Feb 14, 2026');
    app()->bind(PlannerMetricsServiceInterface::class, fn () => $mock);
}

function accordionPlannerData(array $overrides = []): array
{
    return array_merge([
        'username' => 'tgibson',
        'display_name' => 'tgibson',
        'period_miles' => 4.0,
        'quota_target' => 6.5,
        'quota_percent' => 61.5,
        'streak_weeks' => 1,
        'gap_miles' => 2.5,
        'days_since_last_edit' => 2,
        'pending_over_threshold' => 1,
        'permission_breakdown' => ['Approved' => 43, 'Pending' => 10],
        'total_miles' => 14.2,
        'overall_percent' => 51.5,
        'active_assessment_count' => 2,
        'status' => 'warning',
        'daily_miles' => [
            ['day' => 'Sun', 'miles' => 0.0],
            ['day' => 'Mon', 'miles' => 1.0],
            ['day' => 'Tue', 'miles' => 0.5],
            ['day' => 'Wed', 'miles' => 1.5],
            ['day' => 'Thu', 'miles' => 1.0],
            ['day' => 'Fri', 'miles' => 0.0],
            ['day' => 'Sat', 'miles' => 0.0],
        ],
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

// ─── State tests ─────────────────────────────────────────────────────────────

test('toggleAccordion sets expandedPlanner property', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', 'tgibson');
});

test('toggleAccordion closes when same planner clicked', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', 'tgibson')
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', null);
});

test('toggleAccordion switches to different planner', function () {
    mockAccordionService([
        accordionPlannerData(),
        accordionPlannerData(['username' => 'amiller', 'display_name' => 'amiller', 'circuits' => []]),
    ]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', 'tgibson')
        ->call('toggleAccordion', 'amiller')
        ->assertSet('expandedPlanner', 'amiller');
});

test('switchSort resets expandedPlanner to null', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', 'tgibson')
        ->call('switchSort', 'attention')
        ->assertSet('expandedPlanner', null);
});

test('navigateOffset resets expandedPlanner to null', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSet('expandedPlanner', 'tgibson')
        ->call('navigateOffset', -1)
        ->assertSet('expandedPlanner', null);
});

// ─── Computed property tests ─────────────────────────────────────────────────

test('expandedCircuits returns circuits for selected planner', function () {
    mockAccordionService([accordionPlannerData()]);

    $component = Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson');

    expect($component->get('expandedCircuits'))->toHaveCount(2);
});

test('expandedCircuits returns empty array when no planner selected', function () {
    mockAccordionService([accordionPlannerData()]);

    $component = Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class);

    expect($component->get('expandedCircuits'))->toBe([]);
});

// ─── Render tests ────────────────────────────────────────────────────────────

test('accordion renders circuit line name when expanded', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSee('Circuit-1234')
        ->assertSee('Circuit-5678');
});

test('accordion renders circuit region', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSee('NORTH')
        ->assertSee('SOUTH');
});

test('accordion renders circuit miles', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSee('/ 15.5 mi');
});

test('accordion renders empty state when planner has zero circuits', function () {
    mockAccordionService([accordionPlannerData(['circuits' => []])]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'tgibson')
        ->assertSee('No active circuits');
});

test('accordion content is not rendered when no planner expanded', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->assertDontSee('Circuit-1234');
});

test('toggleAccordion rejects non-existent username', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->call('toggleAccordion', 'nonexistent_user')
        ->assertSet('expandedPlanner', null)
        ->assertDontSee('Circuit-1234');
});

test('circuit count button shows correct count', function () {
    mockAccordionService([accordionPlannerData()]);

    Livewire::actingAs(createAccordionTestUser())
        ->test(Overview::class)
        ->assertSeeHtml("toggleAccordion('tgibson')");
});
