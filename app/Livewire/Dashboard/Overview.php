<?php

namespace App\Livewire\Dashboard;

use App\Models\Assessment;
use App\Models\AssessmentMetric;
use App\Models\SystemWideSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layout.app-shell', ['title' => 'Overview Dashboard', 'breadcrumbs' => [['label' => 'Dashboard', 'icon' => 'home']]])]
class Overview extends Component
{
    private const METERS_PER_MILE = 1609.344;

    private const CONTRACT_END = '2026-06-30';

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
        $latest = SystemWideSnapshot::query()
            ->orderByDesc('captured_at')
            ->first();

        if (! $latest) {
            return collect([]);
        }

        return collect([[
            'total_miles' => (float) $latest->total_miles,
            'completed_miles' => (float) $latest->completed_miles,
            'active_planners' => (int) $latest->active_planners,
        ]]);
    }

    #[Computed]
    public function milesPipelineByRegion(): array
    {
        $rows = Assessment::query()
            ->select('region', 'status', DB::raw('SUM(length / '.self::METERS_PER_MILE.') as miles'))
            ->whereNotNull('region')
            ->groupBy('region', 'status')
            ->get();

        $statusMap = [
            'ACTIV' => 'in_progress',
            'QC' => 'pending_qc',
            'REWRK' => 'rework',
            'CLOSE' => 'closed',
        ];

        $grouped = $rows->groupBy('region')->map(function ($regionRows, $region) use ($statusMap) {
            $result = ['region' => $region, 'not_started' => 0, 'in_progress' => 0, 'pending_qc' => 0, 'rework' => 0, 'closed' => 0];
            foreach ($regionRows as $row) {
                $key = $statusMap[$row->status] ?? 'not_started';
                $result[$key] = round((float) $row->miles, 1);
            }

            return $result;
        });

        return $grouped->sortKeys()->values()->all();
    }

    #[Computed]
    public function permissionsSystemWide(): array
    {
        $sums = AssessmentMetric::query()
            ->join('assessments', 'assessments.job_guid', '=', 'assessment_metrics.job_guid')
            ->where('assessments.status', 'ACTIV')
            ->selectRaw('
                SUM(approved) as approved,
                SUM(ppl_approved) as ppl_approved,
                SUM(pending) as pending,
                SUM(no_contact) as no_contact,
                SUM(refused) as refused,
                SUM(deferred) as deferred
            ')
            ->first();

        return [
            ['status' => 'Approved', 'count' => (int) $sums->approved],
            ['status' => 'PPL Approved', 'count' => (int) $sums->ppl_approved],
            ['status' => 'Pending', 'count' => (int) $sums->pending],
            ['status' => 'No Contact', 'count' => (int) $sums->no_contact],
            ['status' => 'Refused', 'count' => (int) $sums->refused],
            ['status' => 'Deferred', 'count' => (int) $sums->deferred],
        ];
    }

    #[Computed]
    public function workTypeBreakdown(): array
    {
        $breakdowns = AssessmentMetric::query()
            ->join('assessments', 'assessments.job_guid', '=', 'assessment_metrics.job_guid')
            ->where('assessments.status', 'ACTIV')
            ->whereRaw("assessment_metrics.work_type_breakdown != '[]'")
            ->pluck('assessment_metrics.work_type_breakdown');

        $totals = [];
        $labels = [
            'HCB' => 'Hazard Cut Back', 'HERBNA' => 'Herbicide N/A', 'HERBA' => 'Herbicide Applied',
            'MPB' => 'Mech. Power Brush', 'MPM' => 'Mech. Power Mow', 'SPB' => 'Side Prune Brush',
            'SPM' => 'Side Prune Mech.', 'BRUSHTRIM' => 'Brush Trim', 'FFP-CPM' => 'FFP Cost/Mile',
            'REM612' => 'Removal 6-12"', 'REM1218' => 'Removal 12-18"', 'REM1824' => 'Removal 18-24"',
            'REM2430' => 'Removal 24-30"', 'REM36' => 'Removal 36"+', 'VPS' => 'Veg. Problem Spot',
            'NW' => 'No Work', 'ASH612' => 'Ash 6-12"', 'ASH1218' => 'Ash 12-18"',
            'SENSI' => 'Sensitive Area', 'NOT' => 'Notice',
        ];

        foreach ($breakdowns as $breakdown) {
            $items = is_string($breakdown) ? json_decode($breakdown, true) : $breakdown;
            foreach ($items as $item) {
                $unit = $item['unit'];
                $totals[$unit] = ($totals[$unit] ?? 0) + ($item['quantity'] ?? 0);
            }
        }

        $result = [];
        foreach ($totals as $unit => $qty) {
            $result[] = [
                'work_type' => $unit,
                'label' => $labels[$unit] ?? $unit,
                'total_qty' => round($qty, 2),
            ];
        }

        return collect($result)->sortByDesc('total_qty')->values()->all();
    }

    #[Computed]
    public function summaryStats(): array
    {
        try {
            $latest = DB::table('system_wide_snapshots')
                ->orderByDesc('captured_at')
                ->first();
        } catch (\Illuminate\Database\QueryException) {
            $latest = null;
        }

        $totalMiles = $latest ? (float) $latest->total_miles : 0;
        $completedMiles = $latest ? (float) $latest->completed_miles : 0;
        $remainingMiles = $totalMiles - $completedMiles;

        $workByType = collect($this->workTypeBreakdown)->keyBy('work_type');

        $remOtherTypes = ['REM1218', 'REM1824', 'REM2430', 'REM36'];

        return [
            'total_miles' => round($totalMiles, 1),
            'completed_miles' => round($completedMiles, 1),
            'remaining_miles' => round($remainingMiles, 1),
            'days_remaining' => (int) now()->diffInDays(Carbon::parse(self::CONTRACT_END), false),
            'herbicide_acres' => round(($workByType->get('HERBA')['total_qty'] ?? 0) + ($workByType->get('HERBNA')['total_qty'] ?? 0), 1),
            'hcb_acres' => round($workByType->get('HCB')['total_qty'] ?? 0, 1),
            'vps_count' => (int) ($workByType->get('VPS')['total_qty'] ?? 0),
            'rem_6_12_count' => (int) ($workByType->get('REM612')['total_qty'] ?? 0),
            'rem_other_count' => (int) collect($remOtherTypes)->sum(fn ($t) => $workByType->get($t)['total_qty'] ?? 0),
            'bucket_trim_miles' => round($workByType->get('MPB')['total_qty'] ?? 0, 1),
            'manual_trim_miles' => round($workByType->get('MPM')['total_qty'] ?? 0, 1),
        ];
    }

    #[Computed]
    public function burndownSnapshots(): array
    {
        try {
            return DB::table('system_wide_snapshots')
                ->selectRaw('CAST(captured_at AS DATE) as date, MAX(CAST(total_miles AS NUMERIC) - CAST(completed_miles AS NUMERIC)) as remaining')
                ->groupByRaw('CAST(captured_at AS DATE)')
                ->orderByRaw('CAST(captured_at AS DATE)')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'remaining' => round((float) $row->remaining, 1),
                ])
                ->all();
        } catch (\Illuminate\Database\QueryException) {
            return [];
        }
    }

    #[Computed]
    public function contractEnd(): string
    {
        return self::CONTRACT_END;
    }

    #[Computed]
    public function ctaAssessmentsByRegion(): array
    {
        $rows = Assessment::query()
            ->select('region', 'status', DB::raw('COUNT(*) as count'))
            ->whereIn('status', ['ACTIV', 'QC', 'REWRK'])
            ->whereNotNull('region')
            ->groupBy('region', 'status')
            ->get();

        $regions = $rows->groupBy('region')->map(function ($statuses) {
            $counts = $statuses->keyBy('status');

            return [
                'active' => (int) ($counts->get('ACTIV')->count ?? 0),
                'in_qc' => (int) ($counts->get('QC')->count ?? 0),
                'rework' => (int) ($counts->get('REWRK')->count ?? 0),
            ];
        });

        $all = [
            'active' => $regions->sum('active'),
            'in_qc' => $regions->sum('in_qc'),
            'rework' => $regions->sum('rework'),
        ];

        return array_merge(['all' => $all], $regions->sortKeys()->all());
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
