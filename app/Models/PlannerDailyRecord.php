<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PlannerDailyRecord extends Model
{
    protected $fillable = [
        'job_guid',
        'frstr_user',
        'work_order',
        'extension',
        'assess_date',
        'stat_name',
        'sequence',
        'unit_guid',
        'unit',
        'lat',
        'long',
        'coord_source',
        'span_length',
        'span_miles',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'assess_date' => 'date',
            'lat' => 'decimal:12',
            'long' => 'decimal:12',
            'span_length' => 'decimal:6',
            'span_miles' => 'decimal:9',
            'sequence' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Insert new daily records from API rows. First-claim-wins — existing
     * records are never overwritten. Conflicts (duplicate composite key
     * with a different planner) are logged for admin review.
     *
     * @param  Collection<int, array<string, mixed>>  $apiRows  Raw rows from WinningUnitQuery
     * @return array{inserted: int, conflicts: int}
     */
    public static function syncFromApi(Collection $apiRows): array
    {
        if ($apiRows->isEmpty()) {
            return ['inserted' => 0, 'conflicts' => 0];
        }

        $mapped = $apiRows->map(fn (array $row) => static::mapApiRow($row));

        // Build composite keys for the incoming batch
        $incomingKeys = $mapped->map(
            fn (array $r) => $r['work_order'].'|'.$r['extension'].'|'.$r['stat_name'].'|'.$r['sequence']
        );

        // Find which composite keys already exist in the database
        $existing = static::query()
            ->select('work_order', 'extension', 'stat_name', 'sequence', 'frstr_user')
            ->whereIn('work_order', $mapped->pluck('work_order')->unique()->values())
            ->get()
            ->keyBy(fn (self $r) => $r->work_order.'|'.$r->extension.'|'.$r->stat_name.'|'.$r->sequence);

        $toInsert = [];
        $conflicts = 0;

        foreach ($mapped as $index => $record) {
            $key = $incomingKeys[$index];

            if ($existing->has($key)) {
                $owner = $existing->get($key);

                // Only log if a different planner is trying to claim this span
                if ($owner->frstr_user !== $record['frstr_user']) {
                    $conflicts++;
                    static::logConflict($record, $owner->frstr_user);
                }

                continue;
            }

            $toInsert[] = $record;
        }

        if (! empty($toInsert)) {
            // Chunk inserts to stay under PostgreSQL's ~65K bind parameter limit.
            // 17 columns → ~3,800 rows max per batch. Using 500 for safe margin.
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
     * Log a footage conflict for admin review.
     *
     * @param  array<string, mixed>  $incoming  The row attempting to claim the span
     * @param  string  $existingUser  The planner who already owns this span
     */
    private static function logConflict(array $incoming, string $existingUser): void
    {
        Log::channel('daily_record_conflicts')->warning('Footage conflict: span already claimed', [
            'existing_planner' => $existingUser,
            'incoming_planner' => $incoming['frstr_user'],
            'work_order' => $incoming['work_order'],
            'extension' => $incoming['extension'],
            'stat_name' => $incoming['stat_name'],
            'sequence' => $incoming['sequence'],
            'assess_date' => $incoming['assess_date'],
            'span_miles' => $incoming['span_miles'],
        ]);
    }

    /**
     * Map a single raw API row to database column values.
     *
     * @param  array<string, mixed>  $row  Raw API row with keys like JOBGUID, WO, EXT, etc.
     * @return array<string, mixed>  Mapped row ready for database insert
     */
    public static function mapApiRow(array $row): array
    {
        $now = now();

        $lat = isset($row['LAT']) ? (float) $row['LAT'] : null;
        $long = isset($row['LONG']) ? (float) $row['LONG'] : null;
        [$lat, $long] = static::sanitizeCoordinates($lat, $long);

        $spanMiles = isset($row['SPAN_MILES']) ? (float) $row['SPAN_MILES'] : null;
        if ($spanMiles !== null && $spanMiles < 0) {
            $spanMiles = null;
        }

        return [
            'job_guid' => static::sanitizeGuid($row['JOBGUID'] ?? null),
            'frstr_user' => static::sanitizeString($row['FRSTR_USER'] ?? null) ?? '',
            'work_order' => trim((string) ($row['WO'] ?? '')),
            'extension' => trim((string) ($row['EXT'] ?? '@')) ?: '@',
            'assess_date' => static::parseWsDate($row['ASSESS_DATE'] ?? null),
            'stat_name' => trim((string) ($row['STATNAME'] ?? '')),
            'sequence' => (int) ($row['SEQUENCE'] ?? 0),
            'unit_guid' => static::sanitizeGuid($row['UNITGUID'] ?? null),
            'unit' => static::sanitizeString($row['UNIT'] ?? null),
            'lat' => $lat,
            'long' => $long,
            'coord_source' => static::sanitizeString($row['COORD_SOURCE'] ?? null),
            'span_length' => $row['SPANLGTH'] ?? null,
            'span_miles' => $spanMiles,
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Trim whitespace and convert empty strings to null.
     */
    private static function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Sanitize a GUID: trim, empty→null, uppercase for consistency.
     */
    private static function sanitizeGuid(?string $value): ?string
    {
        $clean = static::sanitizeString($value);

        return $clean !== null ? strtoupper($clean) : null;
    }

    /**
     * Validate and repair coordinates. Rejects (0,0), detects lat/long swaps,
     * and enforces a continental US bounding box. Invalid coords are logged
     * and nulled so the frontend never receives garbage values.
     *
     * @return array{0: ?float, 1: ?float}  [lat, long] — nulled out if invalid
     */
    private static function sanitizeCoordinates(?float $lat, ?float $long): array
    {
        // Both missing — nothing to validate
        if ($lat === null && $long === null) {
            return [null, null];
        }

        // Partial coordinate — can't plot half a point
        if ($lat === null || $long === null) {
            Log::channel('daily_record_conflicts')->notice('Partial coordinate nulled', [
                'lat' => $lat, 'long' => $long,
            ]);

            return [null, null];
        }

        // API default for "no coordinates"
        if ($lat == 0.0 && $long == 0.0) {
            return [null, null];
        }

        // Detect swapped lat/long: if lat looks like a longitude (negative, west)
        // and long looks like a latitude (positive, 24-50 range), swap them
        if ($lat < 0 && $long > 0 && $long >= 24.0 && $long <= 50.0 && $lat >= -125.0 && $lat <= -66.0) {
            Log::channel('daily_record_conflicts')->notice('Swapped lat/long detected and corrected', [
                'original_lat' => $lat, 'original_long' => $long,
            ]);
            [$lat, $long] = [$long, $lat];
        }

        // Continental US bounding box
        if ($lat < 24.0 || $lat > 50.0 || $long < -125.0 || $long > -66.0) {
            Log::channel('daily_record_conflicts')->notice('Coordinates outside continental US — nulled', [
                'lat' => $lat, 'long' => $long,
            ]);

            return [null, null];
        }

        return [$lat, $long];
    }

    /**
     * Parse WorkStudio's "/Date(YYYY-MM-DD)/" format into a date string.
     */
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
