<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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

    public function identity(): HasOne
    {
        return $this->hasOne(UserWsIdentity::class);
    }

    public function user(): HasOneThrough
    {
        return $this->hasOneThrough(User::class, UserWsIdentity::class, 'ws_user_id', 'id', 'id', 'user_id');
    }
}
