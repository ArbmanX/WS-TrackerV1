<?php

namespace App\Livewire\Dashboard;

use App\Services\WorkStudio\Services\CachedQueryService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ActiveAssessments extends Component
{
    public int $limit = 5;

    #[Computed]
    public function assessments(): Collection
    {
        try {
            return app(CachedQueryService::class)->getActiveAssessmentsOrderedByOldest($this->limit);
        } catch (Exception $e) {
            Log::warning('ActiveAssessments: failed to fetch data', [
                'error' => $e->getMessage(),
            ]);

            return collect([]);
        }
    }

    public function refresh(): void
    {
        unset($this->assessments);
    }

    public function render()
    {
        return view('livewire.dashboard.active-assessments');
    }
}
