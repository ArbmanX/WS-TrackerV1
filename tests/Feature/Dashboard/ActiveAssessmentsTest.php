<?php

use App\Livewire\Dashboard\ActiveAssessments;
use App\Services\WorkStudio\WorkStudioApiService;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    $user = \App\Models\User::factory()->create();
    \App\Models\UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);
    $this->actingAs($user);
});

function mockApiService(Collection $assessments): void
{
    $mock = Mockery::mock(WorkStudioApiService::class);
    $mock->shouldReceive('getActiveAssessmentsOrderedByOldest')
        ->andReturn($assessments);

    app()->instance(WorkStudioApiService::class, $mock);
}

test('renders empty state when no active assessments', function () {
    mockApiService(collect([]));

    Livewire::test(ActiveAssessments::class)
        ->assertSee('No Active Assessments')
        ->assertSee('There are no active assessments to display.');
});

test('renders assessment data correctly', function () {
    $assessments = collect([
        [
            'Current_Owner' => 'ASPLUNDH\\jsmith',
            'Line_Name' => 'Test Line Alpha',
            'Job_GUID' => '{TEST-GUID-001}',
            'Work_Order' => 'WO-12345',
            'Total_Miles' => '10.50',
            'Completed_Miles' => '7.80',
            'First_Edit_Date' => '01/15/2026 9:30 AM',
            'Last_Edit_Date' => '01/20/2026 3:45 PM',
            'Last_Sync' => '01/21/2026 8:00 AM',
        ],
    ]);

    mockApiService($assessments);

    Livewire::test(ActiveAssessments::class)
        ->assertSee('jsmith')
        ->assertSee('Test Line Alpha')
        ->assertSee('WO-12345')
        ->assertSee('74%')
        ->assertSee('2.7 mi left')
        ->assertDontSee('No Active Assessments');
});

test('displays count badge when assessments exist', function () {
    $assessments = collect([
        [
            'Current_Owner' => 'ASPLUNDH\\user1',
            'Line_Name' => 'Line A',
            'Job_GUID' => '{GUID-1}',
            'Work_Order' => 'WO-001',
            'Total_Miles' => '5.00',
            'Completed_Miles' => '2.50',
            'First_Edit_Date' => '01/10/2026 8:00 AM',
            'Last_Edit_Date' => '01/12/2026 4:00 PM',
            'Last_Sync' => '01/13/2026 9:00 AM',
        ],
        [
            'Current_Owner' => 'ASPLUNDH\\user2',
            'Line_Name' => 'Line B',
            'Job_GUID' => '{GUID-2}',
            'Work_Order' => 'WO-002',
            'Total_Miles' => '8.00',
            'Completed_Miles' => '8.00',
            'First_Edit_Date' => '01/05/2026 7:00 AM',
            'Last_Edit_Date' => '01/18/2026 2:30 PM',
            'Last_Sync' => '01/19/2026 10:00 AM',
        ],
    ]);

    mockApiService($assessments);

    Livewire::test(ActiveAssessments::class)
        ->assertSeeHtml('badge badge-warning badge-sm')
        ->assertSee('2');
});

test('refresh clears computed cache', function () {
    mockApiService(collect([]));

    Livewire::test(ActiveAssessments::class)
        ->assertSee('No Active Assessments')
        ->call('refresh')
        ->assertSee('No Active Assessments');
});
