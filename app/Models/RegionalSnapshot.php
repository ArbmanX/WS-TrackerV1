<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegionalSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\RegionalSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'scope_year',
        'context_hash',
        'region',
        'contractor',
        'total_assessments',
        'active_count',
        'qc_count',
        'rework_count',
        'closed_count',
        'total_miles',
        'completed_miles',
        'active_planners',
        'total_units',
        'approved_count',
        'pending_count',
        'no_contact_count',
        'refusal_count',
        'deferred_count',
        'ppl_approved_count',
        'rem_6_12_count',
        'rem_over_12_count',
        'ash_removal_count',
        'vps_count',
        'brush_acres',
        'herbicide_acres',
        'bucket_trim_length',
        'manual_trim_length',
        'captured_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_assessments' => 'integer',
            'active_count' => 'integer',
            'qc_count' => 'integer',
            'rework_count' => 'integer',
            'closed_count' => 'integer',
            'total_miles' => 'decimal:2',
            'completed_miles' => 'decimal:2',
            'active_planners' => 'integer',
            'total_units' => 'integer',
            'approved_count' => 'integer',
            'pending_count' => 'integer',
            'no_contact_count' => 'integer',
            'refusal_count' => 'integer',
            'deferred_count' => 'integer',
            'ppl_approved_count' => 'integer',
            'rem_6_12_count' => 'integer',
            'rem_over_12_count' => 'integer',
            'ash_removal_count' => 'integer',
            'vps_count' => 'integer',
            'brush_acres' => 'decimal:2',
            'herbicide_acres' => 'decimal:2',
            'bucket_trim_length' => 'decimal:2',
            'manual_trim_length' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeForYear(Builder $query, string $year): Builder
    {
        return $query->where('scope_year', $year);
    }

    /** @param Builder<self> $query */
    public function scopeForContext(Builder $query, string $contextHash): Builder
    {
        return $query->where('context_hash', $contextHash);
    }

    /** @param Builder<self> $query */
    public function scopeForRegion(Builder $query, string $region): Builder
    {
        return $query->where('region', $region);
    }
}
