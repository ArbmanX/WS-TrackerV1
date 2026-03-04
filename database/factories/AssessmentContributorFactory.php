<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentContributor>
 */
class AssessmentContributorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_guid' => fn () => Assessment::factory()->create()->job_guid,
            'ws_username' => 'ASPLUNDH\\'.fake()->userName(),
            'ws_user_id' => null,
            'user_id' => null,
            'unit_count' => fake()->numberBetween(1, 30),
            'role' => null,
        ];
    }

    public function forester(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'Forester',
        ]);
    }

    public function qcReviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'QC Reviewer',
        ]);
    }

    public function withLocalUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => \App\Models\User::factory(),
        ]);
    }
}
