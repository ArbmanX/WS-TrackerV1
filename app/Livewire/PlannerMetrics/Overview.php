<?php

namespace App\Livewire\PlannerMetrics;

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
    public string $sortBy = 'alpha';

    /** @var int|null Null = auto-default (service decides). Explicit int = user-navigated. */
    #[Url]
    public ?int $offset = null;

    public ?string $expandedPlanner = null;

    public function mount(): void
    {
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
        return $this->offset ?? app(PlannerMetricsServiceInterface::class)->getDefaultOffset('week');
    }

    #[Computed]
    public function planners(): array
    {
        $data = app(PlannerMetricsServiceInterface::class)->getUnifiedMetrics($this->resolvedOffset);

        return $this->sortPlanners($data);
    }

    #[Computed]
    public function periodLabel(): string
    {
        return app(PlannerMetricsServiceInterface::class)->getPeriodLabel('week', $this->resolvedOffset);
    }

    #[Computed]
    public function summaryStats(): array
    {
        $planners = $this->planners;
        $total = count($planners);
        $onTrack = collect($planners)->where('status', 'success')->count();

        return [
            'on_track' => $onTrack,
            'total_planners' => $total,
            'team_avg_percent' => $total ? round(collect($planners)->avg('quota_percent'), 1) : 0,
            'total_aging' => collect($planners)->sum('pending_over_threshold'),
            'total_miles' => round(collect($planners)->sum('period_miles'), 1),
        ];
    }

    #[Computed]
    public function expandedCircuits(): array
    {
        if (! $this->expandedPlanner) {
            return [];
        }

        $planner = collect($this->planners)->firstWhere('username', $this->expandedPlanner);

        if (! $planner) {
            $this->expandedPlanner = null;

            return [];
        }

        // TODO this should only return assessments where taken by field is equal to the planner username, but the service doesn't support that yet
        return $planner['circuits'] ?? [];
    }

    public function toggleAccordion(string $username): void
    {
        if (! collect($this->planners)->contains('username', $username)) {
            return;
        }

        $this->expandedPlanner = $this->expandedPlanner === $username ? null : $username;
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
        unset($this->planners, $this->periodLabel, $this->resolvedOffset, $this->summaryStats, $this->expandedCircuits);
        $this->expandedPlanner = null;
    }

    private function sortPlanners(array $data): array
    {
        return match ($this->sortBy) {
            'attention' => collect($data)->sortByDesc('gap_miles')->values()->all(),
            default => collect($data)->sortBy('display_name')->values()->all(),
        };
    }
}
