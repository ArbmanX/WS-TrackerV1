<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GhostOwnershipPeriod extends Model
{
    /** @use HasFactory<\Database\Factories\GhostOwnershipPeriodFactory> */
    use HasFactory;

    protected $fillable = [
        'job_guid',
        'line_name',
        'region',
        'takeover_date',
        'takeover_username',
        'return_date',
        'baseline_unit_count',
        'baseline_snapshot',
        'is_parent_takeover',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'baseline_snapshot' => 'array',
            'is_parent_takeover' => 'boolean',
            'takeover_date' => 'date',
            'return_date' => 'date',
        ];
    }

    /** @return HasMany<GhostUnitEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(GhostUnitEvidence::class, 'ownership_period_id');
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /** @param Builder<self> $query */
    public function scopeResolved(Builder $query): void
    {
        $query->where('status', 'resolved');
    }

    /** @param Builder<self> $query */
    public function scopeParentTakeovers(Builder $query): void
    {
        $query->where('is_parent_takeover', true);
    }
}
