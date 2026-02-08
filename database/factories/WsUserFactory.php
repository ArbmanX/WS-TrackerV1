<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WsUser>
 */
class WsUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->userName();

        return [
            'username' => 'ASPLUNDH\\'.$username,
            'domain' => 'ASPLUNDH',
            'display_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'is_enabled' => true,
            'groups' => ['Field Crew', 'Vegetation Management'],
            'last_synced_at' => now(),
        ];
    }

    /**
     * User that has not been enriched with details yet.
     */
    public function unenriched(): static
    {
        return $this->state(fn (array $attributes) => [
            'display_name' => null,
            'email' => null,
            'is_enabled' => null,
            'groups' => null,
            'last_synced_at' => null,
        ]);
    }

    /**
     * Disabled WS user.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
