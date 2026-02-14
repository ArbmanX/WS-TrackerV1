<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GhostUnitEvidence extends Model
{
    /** @use HasFactory<\Database\Factories\GhostUnitEvidenceFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'ghost_unit_evidence';

    protected $fillable = [
        'ownership_period_id',
        'job_guid',
        'line_name',
        'region',
        'unitguid',
        'unit_type',
        'statname',
        'permstat_at_snapshot',
        'forester',
        'detected_date',
        'takeover_date',
        'takeover_username',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'detected_date' => 'date',
            'takeover_date' => 'date',
        ];
    }

    /** @return BelongsTo<GhostOwnershipPeriod, $this> */
    public function ownershipPeriod(): BelongsTo
    {
        return $this->belongsTo(GhostOwnershipPeriod::class, 'ownership_period_id');
    }

    /** @param Builder<self> $query */
    public function scopeForAssessment(Builder $query, string $jobGuid): void
    {
        $query->where('job_guid', $jobGuid);
    }

    /** @param Builder<self> $query */
    public function scopeDetectedBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('detected_date', [$from, $to]);
    }
}
