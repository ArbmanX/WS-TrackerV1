<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannerJobAssignment extends Model
{
    /** @use HasFactory<\Database\Factories\PlannerJobAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'frstr_user',
        'normalized_username',
        'job_guid',
        'status',
        'export_path',
        'discovered_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeForNormalizedUser(Builder $query, string $username): void
    {
        $query->where('normalized_username', $username);
    }

    /** @param Builder<self> $query */
    public function scopeForUser(Builder $query, string $username): void
    {
        $query->where('frstr_user', $username);
    }

    /** @param Builder<self> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', 'discovered');
    }

    /** @param Builder<self> $query */
    public function scopeProcessed(Builder $query): void
    {
        $query->where('status', 'processed');
    }

    /** @param Builder<self> $query */
    public function scopeExported(Builder $query): void
    {
        $query->where('status', 'exported');
    }

    public function wsUser(): BelongsTo
    {
        return $this->belongsTo(WsUser::class, 'frstr_user', 'username');
    }

    /**
     * Canonical username normalization: strip domain prefix, spaces → underscores.
     *
     * Must match the filename convention in PlannerCareerLedgerService::stripDomain().
     * Used for the normalized_username column and for matching JSON filenames.
     */
    public static function normalizeUsername(string $frstrUser): string
    {
        $stripped = str_contains($frstrUser, '\\')
            ? substr($frstrUser, strrpos($frstrUser, '\\') + 1)
            : $frstrUser;

        return preg_replace('/\s+/', '_', trim($stripped));
    }
}
