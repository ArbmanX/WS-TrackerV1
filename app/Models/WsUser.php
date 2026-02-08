<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WsUser extends Model
{
    /** @use HasFactory<\Database\Factories\WsUserFactory> */
    use HasFactory;

    protected $fillable = [
        'username',
        'domain',
        'display_name',
        'email',
        'is_enabled',
        'groups',
        'last_synced_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'groups' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function takenJobs(): HasMany
    {
        return $this->hasMany(SsJob::class, 'taken_by_id');
    }

    public function modifiedJobs(): HasMany
    {
        return $this->hasMany(SsJob::class, 'modified_by_id');
    }
}
