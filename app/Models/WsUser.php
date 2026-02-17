<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
