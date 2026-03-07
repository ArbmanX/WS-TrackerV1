<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Assessment extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'job_guid',
        'parent_job_guid',
        'circuit_id',
        'work_order',
        'extension',
        'job_type',
        'status',
        'scope_year',
        'is_split',
        'taken',
        'taken_by_username',
        'modified_by_username',
        'assigned_to',
        'raw_title',
        'version',
        'sync_version',
        'cycle_type',
        'region',
        'contractor',
        'planned_emergent',
        'voltage',
        'cost_method',
        'program_name',
        'permissioning_required',
        'percent_complete',
        'length',
        'length_completed',
        'last_edited',
        'last_edited_ole',
        'discovered_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_split' => 'boolean',
            'taken' => 'boolean',
            'percent_complete' => 'integer',
            'voltage' => 'float',
            'permissioning_required' => 'boolean',
            'length' => 'float',
            'length_completed' => 'float',
            'last_edited_ole' => 'float',
            'last_edited' => 'datetime',
            'discovered_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function circuit(): BelongsTo
    {
        return $this->belongsTo(Circuit::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_job_guid', 'job_guid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_job_guid', 'job_guid');
    }

    public function metrics(): HasOne
    {
        return $this->hasOne(AssessmentMetric::class, 'job_guid', 'job_guid');
    }

    public function contributors(): HasMany
    {
        return $this->hasMany(AssessmentContributor::class, 'job_guid', 'job_guid');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_assessments')->withTimestamps();
    }
}
