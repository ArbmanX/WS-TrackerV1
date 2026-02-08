<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SudoAdminSeeder extends Seeder
{
    /**
     * Seed the sudo admin user for initial system access.
     *
     * This seeder creates or updates the system administrator account
     * using credentials from environment variables. The admin user
     * bypasses the normal onboarding flow.
     */
    public function run(): void
    {
        $email = config('app.sudo_admin_email');
        $password = config('app.sudo_admin_password');

        if (! $email || ! $password) {
            $this->command->warn('SUDO_ADMIN_EMAIL or SUDO_ADMIN_PASSWORD not set. Skipping sudo admin creation.');

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'System Administrator',
                'password' => Hash::make($password),
            ]
        );

        // Create settings with onboarding already complete
        UserSetting::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'first_login' => false,
                'onboarding_completed_at' => now(),
                'theme' => 'system',
            ]
        );

        $admin->assignRole('sudo-admin');

        $this->command->info("Sudo admin created/verified: {$email} and assigned role of sudo-admin");
    }
}
