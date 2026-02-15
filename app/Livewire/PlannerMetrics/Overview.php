<?php

namespace App\Livewire\PlannerMetrics;

use App\Services\PlannerMetrics\Contracts\CoachingMessageGeneratorInterface;
use App\Services\PlannerMetrics\Contracts\PlannerMetricsServiceInterface;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Planner Metrics', 'breadcrumbs' => [['label' => 'Planner Metrics']]])]
class Overview extends Component
{
    #[Url]
    public string $cardView = 'quota';

    #[Url]
    public string $period = 'week';

    #[Url]
    public string $sortBy = 'alpha';

    public function mount(): void
    {
        if (! in_array($this->cardView, ['quota', 'health'])) {
            $this->cardView = config('planner_metrics.default_card_view', 'quota');
        }
        if (! in_array($this->period, config('planner_metrics.periods', []))) {
            $this->period = config('planner_metrics.default_period', 'week');
        }
        if (! in_array($this->sortBy, ['alpha', 'attention'])) {
            $this->sortBy = 'alpha';
        }
    }

    #[Computed]
    public function planners(): array
    {
        $data = match ($this->cardView) {
            'health' => app(PlannerMetricsServiceInterface::class)->getHealthMetrics(),
            default => app(PlannerMetricsServiceInterface::class)->getQuotaMetrics($this->period),
        };

        return $this->sortPlanners($data);
    }

    #[Computed]
    public function coachingMessages(): array
    {
        if ($this->cardView !== 'quota') {
            return [];
        }

        $generator = app(CoachingMessageGeneratorInterface::class);

        return collect($this->planners)
            ->mapWithKeys(fn ($p) => [$p['username'] => $generator->generate($p)])
            ->filter()
            ->all();
    }

    public function switchView(string $view): void
    {
        if (! in_array($view, ['quota', 'health'])) {
            return;
        }

        $this->cardView = $view;
        $this->clearCache();
    }

    public function switchPeriod(string $period): void
    {
        if (! in_array($period, config('planner_metrics.periods', []))) {
            return;
        }

        $this->period = $period;
        $this->clearCache();
    }

    public function switchSort(string $sort): void
    {
        if (! in_array($sort, ['alpha', 'attention'])) {
            return;
        }

        $this->sortBy = $sort;
        $this->clearCache();
    }

    public function render(): View
    {
        return view('livewire.planner-metrics.overview');
    }

    private function clearCache(): void
    {
        unset($this->planners, $this->coachingMessages);
    }

    private function sortPlanners(array $data): array
    {
        return match ($this->sortBy) {
            'attention' => match ($this->cardView) {
                'health' => collect($data)->sortByDesc('days_since_last_edit')->values()->all(),
                default => collect($data)->sortByDesc('gap_miles')->values()->all(),
            },
            default => collect($data)->sortBy('display_name')->values()->all(),
        };
    }
}
