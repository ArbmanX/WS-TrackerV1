<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlannerDailyRecord extends Model
{
    private const FEET_PER_MILE = 5280.0;

    protected $fillable = [
        'job_guid',
        'frstr_user',
        'work_order',
        'extension',
        'assess_date',
        'span_length_ft',
        'span_miles',
        'station_count',
        'stations',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'assess_date' => 'date',
            'span_length_ft' => 'decimal:4',
            'span_miles' => 'decimal:9',
            'station_count' => 'integer',
            'stations' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Insert new daily records from API rows, aggregated per day.
     * First-claim-wins — existing day records are never overwritten.
     * Conflicts (same WO+ext+date, different planner) are logged.
     *
     * @param  Collection<int, array<string, mixed>>  $apiRows  Raw rows from WinningUnitQuery
     * @return array{inserted: int, conflicts: int}
     */
    public static function syncFromApi(Collection $apiRows): array
    {
        if ($apiRows->isEmpty()) {
            return ['inserted' => 0, 'conflicts' => 0];
        }

        // Map raw API rows, then group into per-day aggregates
        $mapped = $apiRows->map(fn (array $row) => static::mapApiRow($row));
        $dailyRecords = static::aggregateByDay($mapped);

        // Build composite keys for the incoming batch
        $incomingKeys = $dailyRecords->map(
            fn (array $r) => $r['work_order'].'|'.$r['extension'].'|'.$r['frstr_user'].'|'.$r['assess_date']
        );

        // Find which composite keys already exist in the database
        $existing = static::query()
            ->select('work_order', 'extension', 'frstr_user', 'assess_date')
            ->whereIn('work_order', $dailyRecords->pluck('work_order')->unique()->values())
            ->get()
            ->keyBy(fn (self $r) => $r->work_order.'|'.$r->extension.'|'.$r->frstr_user.'|'.$r->assess_date->format('Y-m-d'));

        $toInsert = [];
        $conflicts = 0;

        foreach ($dailyRecords as $index => $record) {
            $key = $incomingKeys[$index];

            if ($existing->has($key)) {
                $owner = $existing->get($key);

                if ($owner->frstr_user !== $record['frstr_user']) {
                    $conflicts++;
                    static::logConflict($record, $owner->frstr_user);
                }

                continue;
            }

            $toInsert[] = $record;
        }

        if (! empty($toInsert)) {
            foreach (array_chunk($toInsert, 500) as $batch) {
                static::insertOrIgnore($batch);
            }
        }

        return [
            'inserted' => count($toInsert),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Aggregate mapped unit rows into per-day records.
     * Stations are sorted by stat_name for map-ready coordinate ordering.
     *
     * @param  Collection<int, array<string, mixed>>  $mappedRows
     * @return Collection<int, array<string, mixed>>
     */
    private static function aggregateByDay(Collection $mappedRows): Collection
    {
        $now = now();

        return $mappedRows
            ->groupBy(fn (array $r) => $r['work_order'].'|'.$r['extension'].'|'.$r['frstr_user'].'|'.$r['assess_date'])
            ->map(function (Collection $group) use ($now) {
                $first = $group->first();

                // Build stations array sorted by stat_name for circuit-path ordering
                $stations = $group
                    ->sortBy('stat_name')
                    ->values()
                    ->map(fn (array $r) => [
                        'stat_name' => $r['stat_name'],
                        'unit' => $r['unit'],
                        'lat' => $r['lat'],
                        'long' => $r['long'],
                        'coord_source' => $r['coord_source'],
                        'span_length_ft' => $r['span_length_ft'],
                    ])
                    ->all();

                $totalFeet = $group->sum('span_length_ft');

                return [
                    'job_guid' => $first['job_guid'],
                    'frstr_user' => $first['frstr_user'],
                    'work_order' => $first['work_order'],
                    'extension' => $first['extension'],
                    'assess_date' => $first['assess_date'],
                    'span_length_ft' => round($totalFeet, 4),
                    'span_miles' => round($totalFeet / self::FEET_PER_MILE, 9),
                    'station_count' => count($stations),
                    'stations' => json_encode($stations),
                    'last_synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->values();
    }

    /**
     * Map a single raw API row to intermediate values (not yet aggregated).
     */
    public static function mapApiRow(array $row): array
    {
        $lat = isset($row['LAT']) ? (float) $row['LAT'] : null;
        $long = isset($row['LONG']) ? (float) $row['LONG'] : null;
        [$lat, $long] = static::sanitizeCoordinates($lat, $long);

        $spanFt = isset($row['SPAN_LENGTH_FT']) ? (float) $row['SPAN_LENGTH_FT'] : null;
        if ($spanFt !== null && $spanFt < 0) {
            $spanFt = null;
        }

        return [
            'job_guid' => static::sanitizeGuid($row['JOBGUID'] ?? null),
            'frstr_user' => static::sanitizeString($row['FRSTR_USER'] ?? null) ?? '',
            'work_order' => trim((string) ($row['WO'] ?? '')),
            'extension' => trim((string) ($row['EXT'] ?? '@')) ?: '@',
            'assess_date' => static::parseWsDate($row['ASSESS_DATE'] ?? null),
            'stat_name' => trim((string) ($row['STATNAME'] ?? '')),
            'unit' => static::sanitizeString($row['UNIT'] ?? null),
            'lat' => $lat,
            'long' => $long,
            'coord_source' => static::sanitizeString($row['COORD_SOURCE'] ?? null),
            'span_length_ft' => $spanFt ?? 0,
        ];
    }

    /**
     * Log a footage conflict for admin review.
     */
    private static function logConflict(array $incoming, string $existingUser): void
    {
        Log::channel('daily_record_conflicts')->warning('Footage conflict: day already claimed', [
            'existing_planner' => $existingUser,
            'incoming_planner' => $incoming['frstr_user'],
            'work_order' => $incoming['work_order'],
            'extension' => $incoming['extension'],
            'assess_date' => $incoming['assess_date'],
            'span_miles' => $incoming['span_miles'],
        ]);
    }

    private static function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function sanitizeGuid(?string $value): ?string
    {
        $clean = static::sanitizeString($value);

        return $clean !== null ? strtoupper($clean) : null;
    }

    /**
     * Validate and repair coordinates. Rejects (0,0), detects lat/long swaps,
     * and enforces a continental US bounding box.
     *
     * @return array{0: ?float, 1: ?float} [lat, long] — nulled out if invalid
     */
    private static function sanitizeCoordinates(?float $lat, ?float $long): array
    {
        if ($lat === null && $long === null) {
            return [null, null];
        }

        if ($lat === null || $long === null) {
            Log::channel('daily_record_conflicts')->notice('Partial coordinate nulled', [
                'lat' => $lat, 'long' => $long,
            ]);

            return [null, null];
        }

        if ($lat == 0.0 && $long == 0.0) {
            return [null, null];
        }

        if ($lat < 0 && $long > 0 && $long >= 24.0 && $long <= 50.0 && $lat >= -125.0 && $lat <= -66.0) {
            Log::channel('daily_record_conflicts')->notice('Swapped lat/long detected and corrected', [
                'original_lat' => $lat, 'original_long' => $long,
            ]);
            [$lat, $long] = [$long, $lat];
        }

        if ($lat < 24.0 || $lat > 50.0 || $long < -125.0 || $long > -66.0) {
            Log::channel('daily_record_conflicts')->notice('Coordinates outside continental US — nulled', [
                'lat' => $lat, 'long' => $long,
            ]);

            return [null, null];
        }

        return [$lat, $long];
    }

    private static function parseWsDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (preg_match('#/Date\(([^)]+)\)/#', $value, $matches)) {
            return Carbon::parse($matches[1])->toDateString();
        }

        return Carbon::parse($value)->toDateString();
    }
}
