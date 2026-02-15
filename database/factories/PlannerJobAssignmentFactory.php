<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlannerJobAssignment>
 */
class PlannerJobAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $username = fake()->userName();

        return [
            'frstr_user' => 'ASPLUNDH\\'.$username,
            'normalized_username' => $username,
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'status' => 'discovered',
            'discovered_at' => now(),
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
        ]);
    }

    public function exported(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'exported',
        ]);
    }

    public function withExportPath(string $path = '/tmp/career/test_export.json'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'exported',
            'export_path' => $path,
        ]);
    }
}
