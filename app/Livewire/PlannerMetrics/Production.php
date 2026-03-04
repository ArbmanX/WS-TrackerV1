<?php

namespace App\Livewire\PlannerMetrics;

use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Daily Production', 'breadcrumbs' => [['label' => 'Planners', 'route' => 'planner-metrics.overview'], ['label' => 'Production']]])]
class Production extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $planner = 'all';

    public function mount(): void
    {
        if (! $this->from || ! $this->to) {
            $this->to = now()->toDateString();
            $this->from = now()->subDays(13)->toDateString();
        }
    }

    #[Computed]
    public function dateRange(): array
    {
        $from = Carbon::parse($this->from);
        $to = Carbon::parse($this->to);

        if ($from->diffInDays($to) > 60) {
            $from = $to->copy()->subDays(60);
            $this->from = $from->toDateString();
        }

        return [$from, $to];
    }

    /**
     * Domain prefix for filtering planners (e.g. "ASPLUNDH\").
     *
     * Returns two forms:
     *  - raw: "ASPLUNDH\" for whereRaw comparisons
     *  - like: "ASPLUNDH\\\\" (doubled backslash) for PG ILIKE patterns
     */
    #[Computed]
    public function domainFilter(): array
    {
        $context = UserQueryContext::fromUser(Auth::user());
        $domain = strtoupper($context->domain);

        return [
            'raw' => $domain.'\\',
            'like' => $domain.'\\\\%',
        ];
    }

    #[Computed]
    public function dailyData(): array
    {
        [$from, $to] = $this->dateRange;
        $filter = $this->domainFilter;

        // Query planner_daily_records scoped to domain prefix and date range
        $rows = DB::table('planner_daily_records')
            ->select('assess_date', 'frstr_user', DB::raw('SUM(span_miles) as daily_miles'))
            ->where('frstr_user', 'ILIKE', $filter['like'])
            ->whereBetween('assess_date', [$from->toDateString(), $to->toDateString()])
            ->when($this->planner !== 'all', fn ($q) => $q->where('frstr_user', $this->planner))
            ->groupBy('assess_date', 'frstr_user')
            ->orderBy('assess_date')
            ->get();

        // Build all dates in range
        $dates = [];
        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Sort planners by total miles descending
        $plannerTotals = $rows->groupBy('frstr_user')
            ->map(fn ($group) => $group->sum('daily_miles'))
            ->sortDesc();

        // Build per-planner daily series
        $series = [];
        foreach ($plannerTotals->keys() as $frstrUser) {
            $dailyMap = $rows->where('frstr_user', $frstrUser)
                ->keyBy(fn ($r) => is_string($r->assess_date) ? $r->assess_date : Carbon::parse($r->assess_date)->toDateString());

            $values = [];
            foreach ($dates as $date) {
                $row = $dailyMap->get($date);
                $values[] = $row ? round((float) $row->daily_miles, 2) : 0;
            }

            $series[] = [
                'frstr_user' => $frstrUser,
                'display_name' => $this->formatDisplayName($frstrUser),
                'data' => $values,
            ];
        }

        return [
            'dates' => $dates,
            'series' => $series,
        ];
    }

    #[Computed]
    public function summaryTable(): array
    {
        $data = $this->dailyData;
        $dates = $data['dates'];
        $summary = [];

        foreach ($data['series'] as $s) {
            $values = $s['data'];
            $activeDays = count(array_filter($values, fn ($v) => $v > 0));
            $totalMiles = array_sum($values);
            $avgPerDay = $activeDays > 0 ? $totalMiles / $activeDays : 0;

            $peakMiles = ! empty($values) ? max($values) : 0;
            $peakIndex = $peakMiles > 0 ? array_search($peakMiles, $values) : 0;
            $peakDate = $dates[$peakIndex] ?? null;

            $summary[] = [
                'display_name' => $s['display_name'],
                'total_miles' => round($totalMiles, 1),
                'avg_per_day' => round($avgPerDay, 2),
                'peak_miles' => round($peakMiles, 1),
                'peak_date' => $peakDate,
                'active_days' => $activeDays,
            ];
        }

        return $summary;
    }

    #[Computed]
    public function availablePlanners(): array
    {
        [$from, $to] = $this->dateRange;

        return DB::table('planner_daily_records')
            ->select('frstr_user', DB::raw('SUM(span_miles) as total'))
            ->where('frstr_user', 'ILIKE', $this->domainFilter['like'])
            ->whereBetween('assess_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('frstr_user')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'value' => $r->frstr_user,
                'label' => $this->formatDisplayName($r->frstr_user),
            ])
            ->all();
    }

    public function updatedFrom(): void
    {
        $this->clearCache();
    }

    public function updatedTo(): void
    {
        $this->clearCache();
    }

    public function updatedPlanner(): void
    {
        $this->clearCache();
    }

    public function render()
    {
        return view('livewire.planner-metrics.production');
    }

    private function clearCache(): void
    {
        unset(
            $this->dailyData,
            $this->summaryTable,
            $this->availablePlanners,
            $this->dateRange,
        );
    }

    private function formatDisplayName(string $frstrUser): string
    {
        $parts = explode('\\', $frstrUser);
        $username = end($parts);

        // "lehigh dcf67" style
        if (str_contains($username, ' ')) {
            return ucwords($username);
        }

        // "name@domain.com" style
        if (str_contains($username, '@')) {
            $username = explode('@', $username)[0];
        }

        // "amiller" → "A. Miller"
        if (strlen($username) > 2 && ctype_alpha($username)) {
            $first = strtoupper($username[0]);
            $rest = ucfirst(substr($username, 1));

            return "{$first}. {$rest}";
        }

        return ucfirst($username);
    }
}
