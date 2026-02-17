<?php

namespace Database\Factories;

use App\Models\Circuit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assessment>
 */
class AssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'parent_job_guid' => null,
            'circuit_id' => Circuit::factory(),
            'work_order' => fake()->numerify('WO-######'),
            'extension' => '@',
            'job_type' => fake()->randomElement(config('ws_assessment_query.job_types.assessments', ['Assessment Dx', 'Split_Assessment'])),
            'status' => fake()->randomElement(['SA', 'ACTIV', 'QC', 'REWRK', 'CLOSE']),
            'scope_year' => (string) now()->year,
            'is_split' => false,
            'taken' => false,
            'taken_by_username' => null,
            'modified_by_username' => null,
            'assigned_to' => null,
            'raw_title' => fake()->numerify('#####'),
            'version' => fake()->numberBetween(1, 20),
            'sync_version' => fake()->numberBetween(1, 50),
            'cycle_type' => null,
            'region' => null,
            'planned_emergent' => null,
            'voltage' => null,
            'cost_method' => null,
            'program_name' => null,
            'permissioning_required' => null,
            'percent_complete' => null,
            'length' => null,
            'length_completed' => null,
            'last_edited' => null,
            'last_edited_ole' => null,
            'discovered_at' => now(),
            'last_synced_at' => now(),
        ];
    }

    /**
     * A split child assessment (Split_Assessment type with parent reference).
     */
    public function split(string $parentJobGuid, string $extension = 'C_a'): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_job_guid' => $parentJobGuid,
            'job_type' => 'Split_Assessment',
            'extension' => $extension,
        ]);
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function withJobType(string $jobType): static
    {
        return $this->state(fn (array $attributes) => [
            'job_type' => $jobType,
        ]);
    }
}
