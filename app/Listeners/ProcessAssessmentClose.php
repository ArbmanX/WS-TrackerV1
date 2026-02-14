<?php

namespace App\Listeners;

use App\Events\AssessmentClosed;
use App\Services\WorkStudio\DataCollection\CareerLedgerService;
use App\Services\WorkStudio\DataCollection\GhostDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAssessmentClose implements ShouldQueue
{
    public function __construct(
        private CareerLedgerService $careerLedger,
        private GhostDetectionService $ghostDetection,
    ) {}

    public function handle(AssessmentClosed $event): void
    {
        DB::transaction(function () use ($event) {
            $this->careerLedger->appendFromMonitor($event->monitor);

            $this->ghostDetection->cleanupOnClose($event->jobGuid);

            $event->monitor->delete();
        });

        Log::info('Assessment close processed', [
            'job_guid' => $event->jobGuid,
            'line_name' => $event->monitor->line_name,
        ]);
    }
}
