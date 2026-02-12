<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SystemWideSnapshot>
 */
class SystemWideSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scope_year' => config('ws_assessment_query.scope_year', date('Y')),
            'context_hash' => substr(md5(fake()->word()), 0, 8),
            'contractor' => fake()->randomElement(['Asplundh', 'CNUC', null]),
            'total_assessments' => fake()->numberBetween(100, 2000),
            'active_count' => fake()->numberBetween(10, 200),
            'qc_count' => fake()->numberBetween(0, 50),
            'rework_count' => fake()->numberBetween(0, 20),
            'closed_count' => fake()->numberBetween(50, 1500),
            'total_miles' => fake()->randomFloat(2, 100, 5000),
            'completed_miles' => fake()->randomFloat(2, 50, 3000),
            'active_planners' => fake()->numberBetween(1, 30),
            'captured_at' => now(),
        ];
    }

    /**
     * Set a specific captured_at timestamp.
     */
    public function capturedAt(\DateTimeInterface|string $timestamp): static
    {
        return $this->state(fn (array $attributes) => [
            'captured_at' => $timestamp,
        ]);
    }
}
