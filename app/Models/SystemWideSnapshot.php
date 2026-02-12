<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemWideSnapshot extends Model
{
    /** @use HasFactory<\Database\Factories\SystemWideSnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'scope_year',
        'context_hash',
        'contractor',
        'total_assessments',
        'active_count',
        'qc_count',
        'rework_count',
        'closed_count',
        'total_miles',
        'completed_miles',
        'active_planners',
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
}
