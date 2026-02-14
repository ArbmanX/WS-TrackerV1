<?php

namespace App\Events;

use App\Models\AssessmentMonitor;
use Illuminate\Foundation\Events\Dispatchable;

class AssessmentClosed
{
    use Dispatchable;

    public function __construct(
        public readonly AssessmentMonitor $monitor,
        public readonly string $jobGuid,
    ) {}
}
