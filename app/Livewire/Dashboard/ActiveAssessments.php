<?php

namespace App\Livewire\Dashboard;

use App\Services\WorkStudio\Shared\Cache\CachedQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
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
            $context = UserQueryContext::fromUser(Auth::user());

            return app(CachedQueryService::class)->getActiveAssessmentsOrderedByOldest($context, $this->limit);
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
