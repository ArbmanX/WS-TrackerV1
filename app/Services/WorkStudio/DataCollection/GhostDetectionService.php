<?php

namespace App\Services\WorkStudio\DataCollection;

use App\Models\GhostOwnershipPeriod;
use App\Models\GhostUnitEvidence;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\DataCollection\Queries\GhostDetectionQueries;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;
use Carbon\Carbon;

class GhostDetectionService
{
    private GhostDetectionQueries $queries;

    public function __construct(
        private GetQueryService $queryService,
    ) {
        $this->queries = new GhostDetectionQueries($this->buildServiceContext());
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

    /**
     * Scan JOBHISTORY for recent ONEPPL takeovers and create baselines.
     *
     * @return int Number of new ownership periods created
     */
    public function checkForOwnershipChanges(): int
    {
        $domain = config('ws_data_collection.ghost_detection.oneppl_domain', 'ONEPPL');

        $since = GhostOwnershipPeriod::latest('created_at')
            ->value('created_at')
            ?->toDateString()
            ?? Carbon::today()->subDays(7)->toDateString();

        $sql = $this->queries->getRecentOwnershipChanges($domain, $since);
        $changes = $this->queryService->executeAndHandle($sql);

        $created = 0;

        foreach ($changes as $change) {
            $jobGuid = $change['JOBGUID'] ?? null;
            $username = $change['ASSIGNEDTO'] ?? null;

            if (! $jobGuid || ! $username) {
                continue;
            }

            $alreadyTracked = GhostOwnershipPeriod::where('job_guid', $jobGuid)
                ->where('takeover_username', $username)
                ->active()
                ->exists();

            if ($alreadyTracked) {
                continue;
            }

            $isParent = ($change['EXT'] ?? '') === '@';
            $this->createBaseline($jobGuid, $username, $isParent, $change);
            $created++;
        }

        return $created;
    }

    /**
     * Capture a UNITGUID baseline snapshot at the moment of takeover.
     *
     * @param  array<string, mixed>  $assessmentMeta  Optional metadata from the ownership change row
     */
    public function createBaseline(string $jobGuid, string $username, bool $isParent, array $assessmentMeta = []): GhostOwnershipPeriod
    {
        $sql = $this->queries->getUnitGuidsForAssessment($jobGuid);
        $units = $this->queryService->executeAndHandle($sql);

        $snapshot = $units->map(fn (array $row) => [
            'unitguid' => $row['UNITGUID'],
            'unit_type' => $row['unit_type'],
            'statname' => $row['STATNAME'],
            'permstat' => $row['PERMSTAT'],
            'forester' => $row['FORESTER'],
        ])->values()->toArray();

        return GhostOwnershipPeriod::create([
            'job_guid' => $jobGuid,
            'line_name' => $assessmentMeta['LINENAME'] ?? null,
            'region' => $assessmentMeta['REGION'] ?? null,
            'takeover_date' => Carbon::today(),
            'takeover_username' => $username,
            'baseline_unit_count' => count($snapshot),
            'baseline_snapshot' => $snapshot,
            'is_parent_takeover' => $isParent,
            'status' => 'active',
        ]);
    }

    /**
     * Compare current UNITGUIDs against the baseline to find deleted (ghost) units.
     *
     * Uses set-difference: baseline - current - already_detected = new ghosts.
     *
     * @return int Number of newly detected ghost units
     */
    public function runComparison(GhostOwnershipPeriod $period): int
    {
        $sql = $this->queries->getUnitGuidsForAssessment($period->job_guid);
        $currentGuids = $this->queryService->executeAndHandle($sql)
            ->pluck('UNITGUID')
            ->toArray();

        $baselineByGuid = collect($period->baseline_snapshot)
            ->keyBy('unitguid');

        $alreadyDetected = GhostUnitEvidence::where('ownership_period_id', $period->id)
            ->pluck('unitguid')
            ->toArray();

        $excluded = array_merge($currentGuids, $alreadyDetected);

        $missingUnits = $baselineByGuid->diffKeys(
            collect($excluded)->mapWithKeys(fn (string $guid) => [$guid => true])
        );

        $created = 0;

        foreach ($missingUnits as $guid => $unit) {
            GhostUnitEvidence::create([
                'ownership_period_id' => $period->id,
                'job_guid' => $period->job_guid,
                'line_name' => $period->line_name,
                'region' => $period->region,
                'unitguid' => $guid,
                'unit_type' => $unit['unit_type'] ?? null,
                'statname' => $unit['statname'] ?? null,
                'permstat_at_snapshot' => $unit['permstat'] ?? null,
                'forester' => $unit['forester'] ?? null,
                'detected_date' => Carbon::today(),
                'takeover_date' => $period->takeover_date,
                'takeover_username' => $period->takeover_username,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Finalize an ownership period when the assessment returns to original owner.
     *
     * Runs a final comparison, then marks the period as resolved.
     */
    public function resolveOwnershipReturn(GhostOwnershipPeriod $period): void
    {
        $this->runComparison($period);

        $period->update([
            'return_date' => Carbon::today(),
            'status' => 'resolved',
        ]);
    }

    /**
     * Clean up ghost tracking when an assessment closes.
     *
     * Deletes ownership periods for the job_guid. The FK ON DELETE SET NULL
     * on ghost_unit_evidence preserves evidence rows (ownership_period_id
     * becomes null but the evidence record remains).
     */
    public function cleanupOnClose(string $jobGuid): void
    {
        GhostOwnershipPeriod::where('job_guid', $jobGuid)->delete();
    }
}
