<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlannerCareerEntry extends Model
{
    /** @use HasFactory<\Database\Factories\PlannerCareerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'planner_username',
        'planner_display_name',
        'job_guid',
        'line_name',
        'region',
        'scope_year',
        'cycle_type',
        'assessment_total_miles',
        'assessment_completed_miles',
        'assessment_pickup_date',
        'assessment_qc_date',
        'assessment_close_date',
        'went_to_rework',
        'rework_details',
        'daily_metrics',
        'summary_totals',
        'source',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_metrics' => 'array',
            'summary_totals' => 'array',
            'rework_details' => 'array',
            'went_to_rework' => 'boolean',
            'assessment_pickup_date' => 'date',
            'assessment_qc_date' => 'date',
            'assessment_close_date' => 'date',
            'assessment_total_miles' => 'decimal:4',
            'assessment_completed_miles' => 'decimal:4',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeForPlanner(Builder $query, string $username): void
    {
        $query->where('planner_username', $username);
    }

    /** @param Builder<self> $query */
    public function scopeForRegion(Builder $query, string $region): void
    {
        $query->where('region', $region);
    }

    /** @param Builder<self> $query */
    public function scopeForScopeYear(Builder $query, string $year): void
    {
        $query->where('scope_year', $year);
    }

    /** @param Builder<self> $query */
    public function scopeFromBootstrap(Builder $query): void
    {
        $query->where('source', 'bootstrap');
    }

    /** @param Builder<self> $query */
    public function scopeFromLiveMonitor(Builder $query): void
    {
        $query->where('source', 'live_monitor');
    }
}
