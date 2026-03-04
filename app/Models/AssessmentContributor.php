<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentContributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_guid',
        'ws_username',
        'ws_user_id',
        'user_id',
        'unit_count',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'unit_count' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class, 'job_guid', 'job_guid');
    }

    public function wsUser(): BelongsTo
    {
        return $this->belongsTo(WsUser::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
