<?php

declare(strict_types=1);

namespace App\Livewire\DataManagement;

use App\Services\WorkStudio\Services\CachedQueryService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout.app-shell', [
    'title' => 'Cache Controls',
    'breadcrumbs' => [
        ['label' => 'Home', 'route' => 'dashboard', 'icon' => 'home'],
        ['label' => 'Data Management'],
        ['label' => 'Cache Controls'],
    ],
])]
class CacheControls extends Component
{
    public string $flashMessage = '';

    public string $flashType = 'success';

    #[Computed]
    public function cacheStatus(): array
    {
        return app(CachedQueryService::class)->getCacheStatus();
    }

    #[Computed]
    public function cacheDriver(): string
    {
        return app(CachedQueryService::class)->getDriverName();
    }

    #[Computed]
    public function scopeYear(): string
    {
        return config('ws_assessment_query.scope_year', (string) date('Y'));
    }

    #[Computed]
    public function datasetsCached(): int
    {
        return collect($this->cacheStatus)->where('cached', true)->count();
    }

    #[Computed]
    public function totalHits(): int
    {
        return collect($this->cacheStatus)->sum('hit_count');
    }

    #[Computed]
    public function totalDatasets(): int
    {
        return count($this->cacheStatus);
    }

    public function refreshDataset(string $dataset): void
    {
        $service = app(CachedQueryService::class);
        $datasets = config('ws_cache.datasets');

        if (! isset($datasets[$dataset])) {
            $this->flash('Invalid dataset.', 'error');

            return;
        }

        $service->invalidateDataset($dataset);
        $method = $datasets[$dataset]['method'];

        try {
            $service->{$method}();
            $this->flash("Refreshed {$datasets[$dataset]['label']} successfully.");
        } catch (\Throwable $e) {
            $this->flash("Failed to refresh {$datasets[$dataset]['label']}: {$e->getMessage()}", 'error');
        }

        $this->clearComputedCache();
    }

    public function clearAll(): void
    {
        $count = app(CachedQueryService::class)->invalidateAll();
        $this->flash("Cleared {$count} cached dataset(s).");
        $this->clearComputedCache();
    }

    public function warmAll(): void
    {
        $results = app(CachedQueryService::class)->warmAll();
        $successes = collect($results)->where('success', true)->count();
        $failures = collect($results)->where('success', false)->count();

        if ($failures === 0) {
            $this->flash("Warmed all {$successes} datasets successfully.");
        } else {
            $this->flash("Warmed {$successes} datasets, {$failures} failed.", 'warning');
        }

        $this->clearComputedCache();
    }

    public function render()
    {
        return view('livewire.data-management.cache-controls');
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
    }

    private function clearComputedCache(): void
    {
        unset($this->cacheStatus, $this->datasetsCached, $this->totalHits, $this->totalDatasets);
    }
}
