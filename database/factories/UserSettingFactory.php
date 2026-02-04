<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserSetting>
 */
class UserSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'theme' => 'system',
            'layout_preference' => 'default',
            'notifications_enabled' => true,
            'sidebar_collapsed' => false,
            'first_login' => true,
            'onboarding_completed_at' => null,
        ];
    }

    /**
     * Indicate that the user is on first login.
     */
    public function firstLogin(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_login' => true,
            'onboarding_completed_at' => null,
        ]);
    }

    /**
     * Indicate that the user has completed onboarding.
     */
    public function onboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'first_login' => false,
            'onboarding_completed_at' => now(),
        ]);
    }
}
