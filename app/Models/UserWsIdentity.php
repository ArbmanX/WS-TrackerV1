<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWsIdentity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ws_user_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wsUser(): BelongsTo
    {
        return $this->belongsTo(WsUser::class);
    }
}
