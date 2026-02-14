<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentMonitor extends Model
{
    /** @use HasFactory<\Database\Factories\AssessmentMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'job_guid',
        'line_name',
        'region',
        'scope_year',
        'cycle_type',
        'current_status',
        'current_planner',
        'total_miles',
        'daily_snapshots',
        'latest_snapshot',
        'first_snapshot_date',
        'last_snapshot_date',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_snapshots' => 'array',
            'latest_snapshot' => 'array',
            'first_snapshot_date' => 'date',
            'last_snapshot_date' => 'date',
            'total_miles' => 'decimal:4',
        ];
    }

    /**
     * Append a daily snapshot and update denormalized fields.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function addSnapshot(string $date, array $snapshot): void
    {
        $snapshots = $this->daily_snapshots ?? [];
        $snapshots[$date] = $snapshot;

        $this->daily_snapshots = $snapshots;
        $this->latest_snapshot = $snapshot;
        $this->last_snapshot_date = $date;

        if ($this->first_snapshot_date === null) {
            $this->first_snapshot_date = $date;
        }
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('current_status', 'ACTIV');
    }

    /** @param Builder<self> $query */
    public function scopeInQc(Builder $query): void
    {
        $query->where('current_status', 'QC');
    }

    /** @param Builder<self> $query */
    public function scopeInRework(Builder $query): void
    {
        $query->where('current_status', 'REWRK');
    }

    /** @param Builder<self> $query */
    public function scopeForRegion(Builder $query, string $region): void
    {
        $query->where('region', $region);
    }
}
