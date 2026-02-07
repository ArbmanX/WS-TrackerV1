<?php

namespace Database\Factories;

use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Circuit>
 */
class CircuitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'line_name' => fake()->unique()->bothify('??-###-???'),
            'region_id' => null,
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }

    /**
     * Associate the circuit with a region.
     */
    public function withRegion(): static
    {
        return $this->state(fn (array $attributes) => [
            'region_id' => Region::factory(),
        ]);
    }

    /**
     * Indicate that the circuit is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
