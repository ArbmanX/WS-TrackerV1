<?php

use App\Events\AssessmentClosed;
use App\Listeners\ProcessAssessmentClose;
use App\Models\AssessmentMonitor;
use App\Models\GhostOwnershipPeriod;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;

test('ProcessAssessmentClose cleans up ghosts and deletes monitor', function () {
    $monitor = AssessmentMonitor::factory()->withSnapshots(3)->create([
        'job_guid' => '{AAAAAAAA-BBBB-CCCC-DDDD-EEEEEEEEEEEE}',
        'current_planner' => 'ASPLUNDH\\jsmith',
    ]);

    $ghostPeriod = GhostOwnershipPeriod::factory()->create([
        'job_guid' => $monitor->job_guid,
    ]);

    $mockQS = Mockery::mock(GetQueryService::class);
    $mockQS->shouldReceive('executeAndHandle')
        ->andReturn(collect([]));

    $ghostService = new GhostDetectionService($mockQS);

    $listener = new ProcessAssessmentClose($ghostService);
    $event = new AssessmentClosed($monitor, $monitor->job_guid);

    $listener->handle($event);

    expect(GhostOwnershipPeriod::where('job_guid', $monitor->job_guid)->exists())->toBeFalse()
        ->and(AssessmentMonitor::where('id', $monitor->id)->exists())->toBeFalse();
});

test('AssessmentClosed event carries monitor and jobGuid', function () {
    $monitor = AssessmentMonitor::factory()->create();
    $event = new AssessmentClosed($monitor, $monitor->job_guid);

    expect($event->monitor)->toBe($monitor)
        ->and($event->jobGuid)->toBe($monitor->job_guid);
});

test('ProcessAssessmentClose listener implements ShouldQueue', function () {
    $reflection = new ReflectionClass(ProcessAssessmentClose::class);

    expect($reflection->implementsInterface(\Illuminate\Contracts\Queue\ShouldQueue::class))->toBeTrue();
});
