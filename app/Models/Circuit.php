<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Circuit extends Model
{
    /** @use HasFactory<\Database\Factories\CircuitFactory> */
    use HasFactory;

    protected $fillable = [
        'line_name',
        'region_id',
        'is_active',
        'last_trim',
        'next_trim',
        'properties',
        'last_seen_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_trim' => 'date',
            'next_trim' => 'date',
            'properties' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @param Builder<Circuit> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
