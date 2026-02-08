<?php

namespace Database\Factories;

use App\Models\WsUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SsJob>
 */
class SsJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'circuit_id' => null,
            'parent_job_guid' => null,
            'taken_by_id' => null,
            'modified_by_id' => null,
            'work_order' => fake()->numerify('WO-######'),
            'extensions' => [fake()->numerify('EXT-###')],
            'job_type' => fake()->randomElement(['Assessment', 'Assessment Rework']),
            'status' => fake()->randomElement(['SA', 'ACTIV', 'QC', 'REWRK', 'CLOSE']),
            'scope_year' => (string) now()->year,
            'edit_date' => fake()->dateTimeBetween('-30 days'),
            'taken' => false,
            'version' => fake()->numerify('#.#'),
            'sync_version' => fake()->numerify('#.#'),
            'assigned_to' => null,
            'raw_title' => fake()->numerify('#####'),
            'last_synced_at' => now(),
        ];
    }

    /**
     * Job with a circuit association.
     */
    public function withCircuit(): static
    {
        return $this->state(fn (array $attributes) => [
            'circuit_id' => \App\Models\Circuit::factory(),
        ]);
    }

    /**
     * Job taken by a WS user.
     */
    public function withTakenBy(): static
    {
        return $this->state(fn (array $attributes) => [
            'taken' => true,
            'taken_by_id' => WsUser::factory(),
        ]);
    }

    /**
     * Job with a specific status.
     */
    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
