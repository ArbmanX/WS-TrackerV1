<?php
namespace App\Services\WorkStudio\Managers;

use App\Models\User;
use App\Models\UserWsCredential;

class ApiCredentialManager
{
    /**
     * Get credentials for API calls.
     * Returns user credentials if available, otherwise falls back to service account.
     *
     * @param  int|null  $userId  User ID to get credentials for
     * @return array{username: string, password: string, user_id: int|null, type: string}
     */
    public function getCredentials(?int $userId = null): array
    {
        // Try to get user-specific credentials first
        if ($userId) {
            $userCreds = $this->getUserCredentials($userId);
            if ($userCreds) {
                return $userCreds;
            }
        }

        // Fall back to service account
        return $this->getServiceAccountCredentials();
    }

    /**
     * Get user-specific WorkStudio credentials.
     *
     * @return array{username: string, password: string, user_id: int, type: string}|null
     */
    public function getUserCredentials(int $userId): ?array
    {
        $credential = UserWsCredential::where('user_id', $userId)
            ->where('is_valid', true)
            ->first();

        if (! $credential) {
            return null;
        }

        return [
            'username' => $credential->encrypted_username,
            'password' => $credential->encrypted_password,
            'user_id' => $userId,
            'type' => 'user',
        ];
    }

    /**
     * Get the service account credentials from config.
     *
     * @return array{username: string, password: string, user_id: null, type: string}
     */
    public function getServiceAccountCredentials(): array
    {
        return [
            'username' => config('workstudio.service_account.username'),
            'password' => config('workstudio.service_account.password'),
            'user_id' => null,
            'type' => 'service',
        ];
    }

    /**
     * Mark credentials as having a successful authentication.
     */
    public function markSuccess(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $credential = UserWsCredential::where('user_id', $userId)->first();

        if ($credential) {
            $credential->update([
                'is_valid' => true,
                'validated_at' => now(),
                'last_used_at' => now(),
            ]);
        }

        // Also update the user's credential tracking columns
        User::where('id', $userId)->update([
            'ws_credentials_last_used_at' => now(),
            'ws_credentials_fail_count' => 0,
        ]);
    }

    /**
     * Mark credentials as having a failed authentication.
     */
    public function markFailed(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        $credential = UserWsCredential::where('user_id', $userId)->first();

        if ($credential) {
            $credential->update([
                'is_valid' => false,
            ]);
        }

        // Also update the user's credential tracking columns
        User::where('id', $userId)->update([
            'ws_credentials_failed_at' => now(),
        ]);

        User::where('id', $userId)->increment('ws_credentials_fail_count');
    }

    /**
     * Store or update user credentials.
     */
    public function storeCredentials(int $userId, string $username, string $password): UserWsCredential
    {
        return UserWsCredential::updateOrCreate(
            ['user_id' => $userId],
            [
                'encrypted_username' => $username,
                'encrypted_password' => $password,
                'is_valid' => true,
                'validated_at' => null,
            ]
        );
    }

    /**
     * Check if a user has valid, active credentials.
     */
    public function hasValidCredentials(int $userId): bool
    {
        return UserWsCredential::where('user_id', $userId)
            ->where('is_valid', true)
            ->exists();
    }

    /**
     * Reactivate credentials for a user (after password reset).
     */
    public function reactivateCredentials(int $userId): bool
    {
        return UserWsCredential::where('user_id', $userId)
            ->update([
                'is_valid' => true,
                'validated_at' => null,
            ]) > 0;
    }

    /**
     * Get info about credentials without exposing password.
     *
     * @return array{type: string, username: string, user_id: int|null, is_valid: bool}
     */
    public function getCredentialsInfo(?int $userId = null): array
    {
        if ($userId) {
            $credential = UserWsCredential::where('user_id', $userId)->first();

            if ($credential && $credential->is_valid) {
                return [
                    'type' => 'user',
                    'username' => $credential->encrypted_username,
                    'user_id' => $userId,
                    'is_valid' => true,
                ];
            }
        }

        return [
            'type' => 'service',
            'username' => config('workstudio.service_account.username'),
            'user_id' => null,
            'is_valid' => true,
        ];
    }
}
