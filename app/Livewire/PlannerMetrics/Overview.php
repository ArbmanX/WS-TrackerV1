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

    /** @var int|null Null = auto-default (service decides). Explicit int = user-navigated. */
    #[Url]
    public ?int $offset = null;

    public ?string $drawerPlanner = null;

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
        if ($this->offset !== null && $this->offset > 0) {
            $this->offset = 0;
        }
    }

    #[Computed]
    public function resolvedOffset(): int
    {
        return $this->offset ?? app(PlannerMetricsServiceInterface::class)->getDefaultOffset($this->period);
    }

    #[Computed]
    public function planners(): array
    {
        $data = match ($this->cardView) {
            'health' => app(PlannerMetricsServiceInterface::class)->getHealthMetrics(),
            default => app(PlannerMetricsServiceInterface::class)->getQuotaMetrics($this->period, $this->resolvedOffset),
        };

        return $this->sortPlanners($data);
    }

    #[Computed]
    public function periodLabel(): string
    {
        return app(PlannerMetricsServiceInterface::class)->getPeriodLabel($this->period, $this->resolvedOffset);
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

    #[Computed]
    public function drawerCircuits(): array
    {
        if (! $this->drawerPlanner) {
            return [];
        }

        $planner = collect($this->planners)->firstWhere('username', $this->drawerPlanner);

        if (! $planner) {
            $this->drawerPlanner = null;

            return [];
        }

        return $planner['circuits'] ?? [];
    }

    #[Computed]
    public function drawerDisplayName(): string
    {
        if (! $this->drawerPlanner) {
            return '';
        }

        $planner = collect($this->planners)->firstWhere('username', $this->drawerPlanner);

        return $planner['display_name'] ?? $this->drawerPlanner;
    }

    public function openDrawer(string $username): void
    {
        if (! collect($this->planners)->contains('username', $username)) {
            return;
        }

        $this->drawerPlanner = $username;
    }

    public function closeDrawer(): void
    {
        $this->drawerPlanner = null;
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
        $this->offset = null;
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

    public function navigateOffset(int $direction): void
    {
        $this->offset = $this->resolvedOffset + $direction;
        $this->clearCache();
    }

    public function resetOffset(): void
    {
        $this->offset = null;
        $this->clearCache();
    }

    public function render(): View
    {
        return view('livewire.planner-metrics.overview');
    }

    private function clearCache(): void
    {
        unset($this->planners, $this->coachingMessages, $this->periodLabel, $this->resolvedOffset);
        unset($this->drawerCircuits, $this->drawerDisplayName);
        $this->drawerPlanner = null;
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
