<?php

namespace App\Livewire\Dashboard;

use App\Models\Assessment;
use App\Models\GhostOwnershipPeriod;
use App\Services\PlannerMetrics\PlannerMetricsService;
use App\Services\WorkStudio\Shared\Cache\CachedQueryService;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Dashboard', 'breadcrumbs' => [['label' => 'Dashboard', 'icon' => 'chart-bar']]])]
class Hub extends Component
{
    #[Computed]
    public function systemMetrics(): array
    {
        $context = UserQueryContext::fromUser(Auth::user());

        return app(CachedQueryService::class)
            ->getSystemWideMetrics($context)
            ->first() ?? [];
    }

    #[Computed]
    public function regionalMetrics(): Collection
    {
        $context = UserQueryContext::fromUser(Auth::user());

        return app(CachedQueryService::class)->getRegionalMetrics($context);
    }

    #[Computed]
    public function plannerSnapshot(): array
    {
        $metrics = app(PlannerMetricsService::class)->getQuotaMetrics();

        $total = count($metrics);
        $behindQuota = collect($metrics)->where('status', 'error')->count();
        $warningCount = collect($metrics)->where('status', 'warning')->count();

        return [
            'total' => $total,
            'behind_quota' => $behindQuota,
            'warning' => $warningCount,
            'on_track' => $total - $behindQuota - $warningCount,
        ];
    }

    #[Computed]
    public function assessmentPipeline(): array
    {
        $sys = $this->systemMetrics;

        return [
            'active' => $sys['active_count'] ?? 0,
            'qc' => $sys['qc_count'] ?? 0,
            'rework' => $sys['rework_count'] ?? 0,
            'closed' => $sys['closed_count'] ?? 0,
            'total' => $sys['total_assessments'] ?? 0,
        ];
    }

    #[Computed]
    public function scopeYearProgress(): Collection
    {
        return Assessment::query()
            ->selectRaw("scope_year, COUNT(*) as total, SUM(CASE WHEN status = 'CLOSE' THEN 1 ELSE 0 END) as closed")
            ->whereNotNull('scope_year')
            ->groupBy('scope_year')
            ->orderBy('scope_year')
            ->get()
            ->map(fn ($row) => [
                'year' => $row->scope_year,
                'total' => (int) $row->total,
                'closed' => (int) $row->closed,
                'percent' => $row->total > 0 ? round(($row->closed / $row->total) * 100) : 0,
            ]);
    }

    #[Computed]
    public function recentActivity(): Collection
    {
        return Assessment::query()
            ->where('last_synced_at', '>=', now()->subDay())
            ->orderByDesc('last_synced_at')
            ->limit(8)
            ->get(['raw_title', 'status', 'region', 'last_synced_at', 'taken_by_username']);
    }

    #[Computed]
    public function alerts(): array
    {
        $ghostCount = GhostOwnershipPeriod::active()->count();
        $ghostPeriods = GhostOwnershipPeriod::active()
            ->latest('takeover_date')
            ->limit(3)
            ->get();

        $plannerMetrics = app(PlannerMetricsService::class)->getHealthMetrics();
        $staleDays = (int) config('planner_metrics.staleness_critical_days', 14);
        $stalePlanners = collect($plannerMetrics)
            ->filter(fn ($p) => ($p['days_since_last_edit'] ?? 0) >= $staleDays)
            ->values();

        $items = [];

        // P0: Ghost takeovers — potential data manipulation
        foreach ($ghostPeriods as $ghost) {
            $items[] = [
                'priority' => 0,
                'title' => "Ghost takeover: {$ghost->line_name}",
                'description' => "{$ghost->takeover_username} since {$ghost->takeover_date->format('M j')}",
                'color' => 'error',
                'icon' => 'shield-exclamation',
            ];
        }

        // P1: Stale planners — work may be stalling
        foreach ($stalePlanners->take(3) as $planner) {
            $days = $planner['days_since_last_edit'] ?? 0;
            $items[] = [
                'priority' => 1,
                'title' => "{$planner['display_name']} inactive ({$days}d)",
                'description' => "{$planner['active_assessment_count']} circuits with no recent edits",
                'color' => 'warning',
                'icon' => 'clock',
            ];
        }

        // P2: Planners with aging pending permissions
        $agingPlanners = collect($plannerMetrics)
            ->filter(fn ($p) => ($p['pending_over_threshold'] ?? 0) > 0)
            ->sortByDesc('pending_over_threshold')
            ->take(2);

        foreach ($agingPlanners as $planner) {
            $items[] = [
                'priority' => 2,
                'title' => "{$planner['display_name']}: {$planner['pending_over_threshold']} aging permits",
                'description' => 'Pending permissions past threshold',
                'color' => 'info',
                'icon' => 'document-check',
            ];
        }

        usort($items, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return [
            'items' => $items,
            'ghost_count' => $ghostCount,
            'ghost_periods' => $ghostPeriods,
            'stale_planner_count' => $stalePlanners->count(),
            'stale_planners' => $stalePlanners->take(3)->values()->all(),
        ];
    }

    public function render()
    {
        return view('livewire.dashboard.hub');
    }
}
