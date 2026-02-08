<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SsJob extends Model
{
    /** @use HasFactory<\Database\Factories\SsJobFactory> */
    use HasFactory;

    protected $primaryKey = 'job_guid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'job_guid',
        'circuit_id',
        'parent_job_guid',
        'taken_by_id',
        'modified_by_id',
        'work_order',
        'extensions',
        'job_type',
        'status',
        'scope_year',
        'edit_date',
        'taken',
        'version',
        'sync_version',
        'assigned_to',
        'raw_title',
        'last_synced_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'extensions' => 'array',
            'edit_date' => 'datetime',
            'taken' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    public function parentJob(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_job_guid', 'job_guid');
    }

    public function childJobs(): HasMany
    {
        return $this->hasMany(self::class, 'parent_job_guid', 'job_guid');
    }

    public function takenBy(): BelongsTo
    {
        return $this->belongsTo(WsUser::class, 'taken_by_id');
    }

    public function modifiedBy(): BelongsTo
    {
        return $this->belongsTo(WsUser::class, 'modified_by_id');
    }
}
