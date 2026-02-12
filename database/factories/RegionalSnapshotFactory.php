<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RegionalSnapshot>
 */
class RegionalSnapshotFactory extends Factory
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
            'region' => fake()->randomElement(['HARRISBURG', 'LANCASTER', 'LEHIGH', 'CENTRAL', 'SUSQUEHANNA', 'NORTHEAST']),
            'contractor' => fake()->randomElement(['Asplundh', 'CNUC', null]),
            'total_assessments' => fake()->numberBetween(10, 500),
            'active_count' => fake()->numberBetween(1, 50),
            'qc_count' => fake()->numberBetween(0, 20),
            'rework_count' => fake()->numberBetween(0, 10),
            'closed_count' => fake()->numberBetween(5, 400),
            'total_miles' => fake()->randomFloat(2, 10, 1000),
            'completed_miles' => fake()->randomFloat(2, 5, 800),
            'active_planners' => fake()->numberBetween(1, 10),
            'total_units' => fake()->numberBetween(50, 5000),
            'approved_count' => fake()->numberBetween(10, 3000),
            'pending_count' => fake()->numberBetween(0, 500),
            'no_contact_count' => fake()->numberBetween(0, 200),
            'refusal_count' => fake()->numberBetween(0, 100),
            'deferred_count' => fake()->numberBetween(0, 50),
            'ppl_approved_count' => fake()->numberBetween(0, 2000),
            'rem_6_12_count' => fake()->numberBetween(0, 100),
            'rem_over_12_count' => fake()->numberBetween(0, 50),
            'ash_removal_count' => fake()->numberBetween(0, 30),
            'vps_count' => fake()->numberBetween(0, 200),
            'brush_acres' => fake()->randomFloat(2, 0, 500),
            'herbicide_acres' => fake()->randomFloat(2, 0, 300),
            'bucket_trim_length' => fake()->randomFloat(2, 0, 10000),
            'manual_trim_length' => fake()->randomFloat(2, 0, 5000),
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
