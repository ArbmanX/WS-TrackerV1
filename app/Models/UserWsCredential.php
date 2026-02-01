<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWsCredential extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'encrypted_username',
        'encrypted_password',
        'is_valid',
        'validated_at',
        'last_used_at',
    ];

    protected $hidden = [
        'encrypted_username',
        'encrypted_password',
    ];

    protected function casts(): array
    {
        return [
            'encrypted_username' => 'encrypted',
            'encrypted_password' => 'encrypted',
            'is_valid' => 'boolean',
            'validated_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The user these credentials belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark credentials as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Mark credentials as valid after successful validation.
     */
    public function markAsValid(): void
    {
        $this->update([
            'is_valid' => true,
            'validated_at' => now(),
        ]);
    }

    /**
     * Mark credentials as invalid.
     */
    public function markAsInvalid(): void
    {
        $this->update(['is_valid' => false]);
    }
}
