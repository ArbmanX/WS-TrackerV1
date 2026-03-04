<?php

namespace App\Console\Commands\Traits;

use App\Models\AssessmentContributor;
use App\Models\AssessmentMetric;
use App\Models\UnitType;
use App\Models\WsUser;
use App\Services\WorkStudio\Assessments\Queries\AdditionalMetricsQueries;
use App\Services\WorkStudio\Client\GetQueryService;
use App\Services\WorkStudio\Shared\Helpers\WSHelpers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait GetsAdditionalAssessmentMetrics
{
    public function getAdditionalMetrics(GetQueryService $queries, array $jobGuids): Collection
    {
        $sql = AdditionalMetricsQueries::buildBatched($jobGuids);

        $results = $queries->executeAndHandle($sql);

        $dateFields = ['taken_date', 'sent_to_qc_date', 'sent_to_rework_date', 'closed_date', 'first_unit_date', 'last_unit_date', 'oldest_pending_date'];

        return $results->map(function (array $row) use ($dateFields) {
            foreach ($dateFields as $field) {
                $row[$field] = WSHelpers::parseWsDate($row[$field] ?? null);
            }

            return $row;
        });
    }

    public function persistMetrics(Collection $metricsRows): void
    {
        $unitTypeMap = UnitType::pluck('entityname', 'unit')->all();

        $existingJobGuids = \App\Models\Assessment::whereIn(
            'job_guid',
            $metricsRows->pluck('JOBGUID')->unique()
        )->pluck('job_guid')->flip()->all();

        $bar = $this->output->createProgressBar($metricsRows->count());
        $this->info('Persisting assessment metrics...');
        $bar->start();

        DB::transaction(function () use ($metricsRows, $unitTypeMap, $bar, $existingJobGuids) {
            foreach ($metricsRows as $row) {
                if (! isset($existingJobGuids[$row['JOBGUID']])) {
                    $bar->advance();

                    continue;
                }
                $workTypeBreakdown = $this->enrichWorkTypeBreakdown($row['work_type_breakdown'] ?? null, $unitTypeMap);

                $mapped = [
                    'job_guid' => $row['JOBGUID'],
                    'work_order' => $row['WO'],
                    'extension' => $row['EXT'],
                    'total_units' => $row['total_units'] ?? 0,
                    'approved' => $row['approved'] ?? 0,
                    'pending' => $row['pending'] ?? 0,
                    'refused' => $row['refused'] ?? 0,
                    'no_contact' => $row['no_contact'] ?? 0,
                    'deferred' => $row['deferred'] ?? 0,
                    'ppl_approved' => $row['ppl_approved'] ?? 0,
                    'units_requiring_notes' => $row['units_requiring_notes'] ?? 0,
                    'units_with_notes' => $row['units_with_notes'] ?? 0,
                    'units_without_notes' => $row['units_without_notes'] ?? 0,
                    'notes_compliance_percent' => $row['notes_compliance_percent'] ?? null,
                    'pending_over_threshold' => $row['pending_over_threshold'] ?? 0,
                    'stations_with_work' => $row['stations_with_work'] ?? 0,
                    'stations_no_work' => $row['stations_no_work'] ?? 0,
                    'stations_not_planned' => $row['stations_not_planned'] ?? 0,
                    'split_count' => $row['split_count'] ?? null,
                    'split_updated' => isset($row['split_updated']) ? (bool) $row['split_updated'] : null,
                    'taken_date' => $row['taken_date'],
                    'sent_to_qc_date' => $row['sent_to_qc_date'],
                    'sent_to_rework_date' => $row['sent_to_rework_date'],
                    'closed_date' => $row['closed_date'],
                    'first_unit_date' => $row['first_unit_date'],
                    'last_unit_date' => $row['last_unit_date'],
                    'oldest_pending_date' => $row['oldest_pending_date'],
                    'oldest_pending_statname' => $row['oldest_pending_statname'] ?? null,
                    'oldest_pending_unit' => $row['oldest_pending_unit'] ?? null,
                    'oldest_pending_sequence' => $row['oldest_pending_sequence'] ?? null,
                    'work_type_breakdown' => $workTypeBreakdown,
                ];

                AssessmentMetric::firstOrNew(['job_guid' => $row['JOBGUID']])->fill($mapped)->save();

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    public function persistContributors(Collection $metricsRows): void
    {
        $wsUserMap = WsUser::pluck('id', 'username')->all();

        $existingJobGuids = \App\Models\Assessment::whereIn(
            'job_guid',
            $metricsRows->pluck('JOBGUID')->unique()
        )->pluck('job_guid')->flip()->all();

        $this->info('Persisting assessment contributors...');
        $count = 0;

        DB::transaction(function () use ($metricsRows, $wsUserMap, &$count, $existingJobGuids) {
            foreach ($metricsRows as $row) {
                if (! isset($existingJobGuids[$row['JOBGUID']])) {
                    continue;
                }

                $forestersJson = $row['foresters'] ?? null;

                if ($forestersJson === null || $forestersJson === '') {
                    continue;
                }

                $foresters = is_string($forestersJson) ? json_decode($forestersJson, true) : $forestersJson;

                if (! is_array($foresters) || empty($foresters)) {
                    continue;
                }

                $jobGuid = $row['JOBGUID'];

                foreach ($foresters as $entry) {
                    $rawUsername = $entry['forester'] ?? null;
                    $unitCount = (int) ($entry['unit_count'] ?? 0);

                    if ($rawUsername === null) {
                        continue;
                    }

                    $wsUserId = $wsUserMap[$rawUsername] ?? null;

                    AssessmentContributor::updateOrCreate(
                        ['job_guid' => $jobGuid, 'ws_username' => $rawUsername],
                        ['ws_user_id' => $wsUserId, 'unit_count' => $unitCount]
                    );

                    $count++;
                }
            }
        });

        $this->info("Persisted {$count} contributor records.");
    }

    private function enrichWorkTypeBreakdown(mixed $raw, array $unitTypeMap): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map(function (array $item) use ($unitTypeMap) {
            $unitCode = $item['unit'] ?? $item['Unit'] ?? '';
            $quantity = (int) ($item['UnitQty'] ?? $item['unitqty'] ?? 0);
            $displayName = $unitTypeMap[$unitCode] ?? $unitCode;

            return [
                'unit' => $unitCode,
                'display_name' => $displayName,
                'quantity' => $quantity,
            ];
        }, $decoded));
    }
}
