<?php

declare(strict_types=1);

namespace App\Services\WorkStudio\Shared\Persistence;

use App\Models\RegionalSnapshot;
use App\Models\SystemWideSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SnapshotPersistenceService
{
    /**
     * API response key → DB column mapping for system-wide metrics.
     * System-wide SQL aliases are already snake_case.
     */
    private const SYSTEM_WIDE_MAP = [
        'contractor' => 'contractor',
        'total_assessments' => 'total_assessments',
        'active_count' => 'active_count',
        'qc_count' => 'qc_count',
        'rework_count' => 'rework_count',
        'closed_count' => 'closed_count',
        'total_miles' => 'total_miles',
        'completed_miles' => 'completed_miles',
        'active_planners' => 'active_planners',
    ];

    /**
     * API response key → DB column mapping for regional metrics.
     * Regional SQL aliases use PascalCase_With_Underscores.
     */
    private const REGIONAL_MAP = [
        'Region' => 'region',
        'Total_Circuits' => 'total_assessments',
        'Active_Count' => 'active_count',
        'QC_Count' => 'qc_count',
        'Rework_Count' => 'rework_count',
        'Closed_Count' => 'closed_count',
        'Total_Miles' => 'total_miles',
        'Completed_Miles' => 'completed_miles',
        'Active_Planners' => 'active_planners',
        'Total_Units' => 'total_units',
        'Approved_Count' => 'approved_count',
        'Pending_Count' => 'pending_count',
        'No_Contact_Count' => 'no_contact_count',
        'Refusal_Count' => 'refusal_count',
        'Deferred_Count' => 'deferred_count',
        'PPL_Approved_Count' => 'ppl_approved_count',
        'Rem_6_12_Count' => 'rem_6_12_count',
        'Rem_Over_12_Count' => 'rem_over_12_count',
        'Ash_Removal_Count' => 'ash_removal_count',
        'VPS_Count' => 'vps_count',
        'Brush_Acres' => 'brush_acres',
        'Herbicide_Acres' => 'herbicide_acres',
        'Bucket_Trim_Length' => 'bucket_trim_length',
        'Manual_Trim_Length' => 'manual_trim_length',
    ];

    /**
     * Integer columns that should be coerced from string to int.
     */
    private const INTEGER_COLUMNS = [
        'total_assessments', 'active_count', 'qc_count', 'rework_count', 'closed_count',
        'active_planners', 'total_units', 'approved_count', 'pending_count',
        'no_contact_count', 'refusal_count', 'deferred_count', 'ppl_approved_count',
        'rem_6_12_count', 'rem_over_12_count', 'ash_removal_count', 'vps_count',
    ];

    /**
     * Decimal columns that should be coerced from string to float.
     */
    private const DECIMAL_COLUMNS = [
        'total_miles', 'completed_miles', 'brush_acres', 'herbicide_acres',
        'bucket_trim_length', 'manual_trim_length',
    ];

    /**
     * Persist a system-wide metrics snapshot.
     */
    public function persistSystemWideMetrics(Collection $data, string $scopeYear, string $contextHash): void
    {
        if ($data->isEmpty()) {
            return;
        }

        try {
            $row = $data->first();
            $mapped = $this->mapRow($row, self::SYSTEM_WIDE_MAP);
            $mapped['scope_year'] = $scopeYear;
            $mapped['context_hash'] = $contextHash;
            $mapped['captured_at'] = now();

            SystemWideSnapshot::create($mapped);
        } catch (\Throwable $e) {
            Log::warning('SnapshotPersistence: failed to persist system-wide metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Persist regional metrics snapshots (one row per region).
     */
    public function persistRegionalMetrics(Collection $data, string $scopeYear, string $contextHash): void
    {
        if ($data->isEmpty()) {
            return;
        }

        try {
            $now = now();
            $rows = [];

            foreach ($data as $item) {
                $mapped = $this->mapRow($item, self::REGIONAL_MAP);
                $mapped['scope_year'] = $scopeYear;
                $mapped['context_hash'] = $contextHash;
                $mapped['captured_at'] = $now;
                $mapped['created_at'] = $now;
                $mapped['updated_at'] = $now;
                $rows[] = $mapped;
            }

            RegionalSnapshot::insert($rows);
        } catch (\Throwable $e) {
            Log::warning('SnapshotPersistence: failed to persist regional metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map an API response row to DB columns using the provided mapping.
     *
     * @param  array<string, mixed>|object  $row
     * @param  array<string, string>  $map  API key → DB column
     * @return array<string, mixed>
     */
    private function mapRow(mixed $row, array $map): array
    {
        $row = (array) $row;
        $mapped = [];

        foreach ($map as $apiKey => $dbColumn) {
            $value = $row[$apiKey] ?? null;
            $mapped[$dbColumn] = $this->coerce($dbColumn, $value);
        }

        return $mapped;
    }

    /**
     * Coerce a value to the correct type based on column name.
     */
    private function coerce(string $column, mixed $value): mixed
    {
        if ($column === 'contractor' || $column === 'region') {
            return $value;
        }

        if (in_array($column, self::INTEGER_COLUMNS, true)) {
            return (int) ($value ?? 0);
        }

        if (in_array($column, self::DECIMAL_COLUMNS, true)) {
            return (float) ($value ?? 0);
        }

        return $value;
    }
}
