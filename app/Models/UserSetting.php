<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends Model
{
    /** @use HasFactory<\Database\Factories\UserSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'layout_preference',
        'notifications_enabled',
        'sidebar_collapsed',
        'first_login',
        'onboarding_completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'sidebar_collapsed' => 'boolean',
            'first_login' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the settings.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
