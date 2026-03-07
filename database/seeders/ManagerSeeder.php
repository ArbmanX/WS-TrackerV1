<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ManagerSeeder extends Seeder
{
    /**
     * Seed the default manager user for initial system access.
     */
    public function run(): void
    {
        $email = config('app.manager_email');
        $password = config('app.manager_password');

        if (! $email || ! $password) {
            $this->command->warn('MANAGER_EMAIL or MANAGER_PASSWORD not set. Skipping manager creation.');

            return;
        }

        $manager = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Manager',
                'password' => Hash::make($password),
            ]
        );

        UserSetting::firstOrCreate(
            ['user_id' => $manager->id],
            [
                'first_login' => false,
                'onboarding_completed_at' => now(),
                'theme' => 'system',
            ]
        );

        $manager->assignRole('manager');

        $this->command->info("Manager created/verified: {$email} and assigned role of manager");
    }
}
