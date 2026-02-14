<?php

namespace App\Services\WorkStudio\DataCollection;

use App\Events\AssessmentClosed;
use App\Models\AssessmentMonitor;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\Queries\LiveMonitorQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LiveMonitorService
{
    private LiveMonitorQueries $queries;

    public function __construct(
        private GetQueryService $queryService,
    ) {
        $this->queries = new LiveMonitorQueries($this->buildServiceContext());
    }

    /**
     * Main cron entry point: snapshot all active assessments and detect closures.
     *
     * @return array{snapshots: int, new: int, closed: int}
     */
    public function runDailySnapshot(): array
    {
        $context = $this->buildServiceContext();
        $assessments = $this->queryService->getDailyActivitiesForAllAssessments($context);
        $allAssessments = $assessments->first();

        if (! is_array($allAssessments)) {
            return ['snapshots' => 0, 'new' => 0, 'closed' => 0];
        }

        $activeAssessments = collect($allAssessments)
            ->filter(fn (array $a) => in_array($a['Status'] ?? '', ['ACTIV', 'QC', 'REWRK']));

        $stats = ['snapshots' => 0, 'new' => 0, 'closed' => 0];

        foreach ($activeAssessments as $assessment) {
            $jobGuid = $assessment['Job_GUID'] ?? null;
            if (! $jobGuid) {
                continue;
            }

            $isNew = ! AssessmentMonitor::where('job_guid', $jobGuid)->exists();
            $this->snapshotAssessment($jobGuid, $assessment);

            $stats['snapshots']++;
            if ($isNew) {
                $stats['new']++;
            }
        }

        $closed = $this->detectClosedAssessments($activeAssessments->pluck('Job_GUID')->filter()->values());
        $stats['closed'] = $closed->count();

        return $stats;
    }

    /**
     * Create or update a daily snapshot for a single assessment.
     *
     * Issues one combined query via LiveMonitorQueries::getDailySnapshot()
     * and upserts an AssessmentMonitor row with the structured snapshot.
     *
     * @param  array<string, mixed>  $assessmentData  Row from daily activities query
     */
    public function snapshotAssessment(string $jobGuid, array $assessmentData): void
    {
        $today = Carbon::today()->toDateString();
        $thresholdDays = config('ws_data_collection.thresholds.aging_unit_days', 14);

        $row = $this->queryService->executeAndHandle(
            $this->queries->getDailySnapshot($jobGuid, $thresholdDays)
        )->first();

        $row = is_array($row) ? $row : [];

        $workTypes = json_decode($row['work_type_breakdown'] ?? '[]', true) ?: [];

        $lastEditDate = $this->parseDdoDate($row['last_edit_date'] ?? null);
        $daysSinceEdit = $lastEditDate
            ? Carbon::parse($lastEditDate)->diffInDays(Carbon::today())
            : null;

        $totalUnits = (int) ($row['total_units'] ?? 0);

        $snapshot = [
            'permission_breakdown' => [
                'approved' => (int) ($row['approved'] ?? 0),
                'pending' => (int) ($row['pending'] ?? 0),
                'refused' => (int) ($row['refused'] ?? 0),
                'no_contact' => (int) ($row['no_contact'] ?? 0),
                'deferred' => (int) ($row['deferred'] ?? 0),
                'ppl_approved' => (int) ($row['ppl_approved'] ?? 0),
            ],
            'unit_counts' => [
                'work_units' => (int) ($row['work_units'] ?? 0),
                'nw_units' => (int) ($row['nw_units'] ?? 0),
                'total_units' => $totalUnits,
            ],
            'work_type_breakdown' => $workTypes,
            'footage' => [
                'completed_miles' => (float) round($assessmentData['Completed_Miles'] ?? 0, 2),
                'percent_complete' => (float) ($assessmentData['Percent_Complete'] ?? 0),
            ],
            'notes_compliance' => [
                'units_with_notes' => (int) ($row['units_with_notes'] ?? 0),
                'units_without_notes' => (int) ($row['units_without_notes'] ?? 0),
                'compliance_percent' => (float) ($row['compliance_percent'] ?? 0),
            ],
            'planner_activity' => [
                'last_edit_date' => $lastEditDate,
                'days_since_last_edit' => $daysSinceEdit,
            ],
            'aging_units' => [
                'pending_over_threshold' => (int) ($row['pending_over_threshold'] ?? 0),
                'threshold_days' => $thresholdDays,
            ],
            'suspicious' => false,
        ];

        $monitor = AssessmentMonitor::firstOrNew(['job_guid' => $jobGuid]);

        if ($monitor->exists && config('ws_data_collection.sanity_checks.flag_zero_count', true)) {
            $previousTotal = $monitor->latest_snapshot['unit_counts']['total_units'] ?? null;
            if ($previousTotal !== null && $previousTotal > 0 && $totalUnits === 0) {
                $snapshot['suspicious'] = true;
            }
        }

        if (! $monitor->exists) {
            $monitor->fill([
                'line_name' => $assessmentData['Line_Name'] ?? null,
                'region' => $assessmentData['Region'] ?? null,
                'scope_year' => $assessmentData['Scope_Year'] ?? null,
                'cycle_type' => $assessmentData['Cycle_Type'] ?? null,
                'current_status' => $assessmentData['Status'] ?? null,
                'current_planner' => $assessmentData['Current_Owner'] ?? null,
                'total_miles' => $assessmentData['Total_Miles'] ?? null,
            ]);
        } else {
            $monitor->current_status = $assessmentData['Status'] ?? $monitor->current_status;
            $monitor->current_planner = $assessmentData['Current_Owner'] ?? $monitor->current_planner;
        }

        $monitor->addSnapshot($today, $snapshot);
        $monitor->save();
    }

    /**
     * Detect assessments that have closed by comparing active JOBGUIDs
     * against existing monitor rows.
     *
     * Dispatches AssessmentClosed event for each newly-closed assessment.
     *
     * @param  Collection<int, string>  $activeJobGuids  Currently active JOBGUIDs from API
     * @return Collection<int, AssessmentMonitor> Monitors that were detected as closed
     */
    public function detectClosedAssessments(Collection $activeJobGuids): Collection
    {
        $monitoredGuids = AssessmentMonitor::pluck('job_guid');

        $closedMonitors = AssessmentMonitor::whereNotIn('job_guid', $activeJobGuids->toArray())->get();

        foreach ($closedMonitors as $monitor) {
            AssessmentClosed::dispatch($monitor, $monitor->job_guid);
        }

        return $closedMonitors;
    }

    private function milesToFeet(float $miles): float
    {
        return round($miles * 5280, 2);
    }

    private function parseDdoDate(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            if (str_contains($value, '/Date(')) {
                $raw = str_replace(['/Date(', ')/'], '', $value);

                return Carbon::parse($raw)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildServiceContext(): UserQueryContext
    {
        return new UserQueryContext(
            resourceGroups: config('workstudio_resource_groups.all', []),
            contractors: config('ws_assessment_query.contractors', ['Asplundh']),
            domain: strtoupper(config('ws_assessment_query.contractors.0', 'ASPLUNDH')),
            username: 'service',
            userId: 0,
        );
    }
}
