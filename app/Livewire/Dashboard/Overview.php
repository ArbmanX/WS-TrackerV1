<?php

namespace App\Livewire\Dashboard;

use App\Services\WorkStudio\WorkStudioApiService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Overview Dashboard'])]
class Overview extends Component
{
    #[Url]
    public string $viewMode = 'cards';

    public bool $panelOpen = false;

    public ?string $selectedRegion = null;

    #[Url]
    public string $sortBy = 'Region';

    #[Url]
    public string $sortDir = 'asc';

    #[Computed]
    public function systemMetrics(): Collection
    {
        return app(WorkStudioApiService::class)->getSystemWideMetrics();
    }

    #[Computed]
    public function regionalMetrics(): Collection
    {
        $metrics = app(WorkStudioApiService::class)->getRegionalMetrics();

        return $this->sortMetrics($metrics);
    }

    protected function sortMetrics(Collection $metrics): Collection
    {
        return $metrics->sortBy(
            $this->sortBy,
            SORT_REGULAR,
            $this->sortDir === 'desc'
        )->values();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        unset($this->regionalMetrics);
    }

    public function openPanel(string $region): void
    {
        $this->selectedRegion = $region;
        $this->panelOpen = true;
        $this->dispatch('open-panel');
    }

    public function closePanel(): void
    {
        $this->panelOpen = false;
        $this->selectedRegion = null;
        $this->dispatch('close-panel');
    }

    public function render()
    {
        return view('livewire.dashboard.overview');
    }
}
