<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitType>
 */
class UnitTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unit' => strtoupper(fake()->unique()->lexify('???')),
            'unitssname' => fake()->words(2, true),
            'unitsetid' => fake()->randomElement(['VEG', 'GENERAL', 'AUDIT']),
            'summarygrp' => 'Summary',
            'entityname' => fake()->word(),
            'work_unit' => true,
            'last_synced_at' => now(),
        ];
    }

    /**
     * Non-working unit (Summary-NonWork or empty summarygrp).
     */
    public function nonWorking(): static
    {
        return $this->state(fn (array $attributes) => [
            'summarygrp' => 'Summary-NonWork',
            'work_unit' => false,
        ]);
    }
}
