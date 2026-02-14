<?php

namespace Database\Factories;

use App\Models\GhostOwnershipPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GhostUnitEvidence>
 */
class GhostUnitEvidenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ownership_period_id' => GhostOwnershipPeriod::factory(),
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'line_name' => fake()->numerify('Circuit-####'),
            'region' => fake()->randomElement(['NORTH', 'SOUTH', 'EAST', 'WEST', 'CENTRAL']),
            'unitguid' => '{'.Str::uuid()->toString().'}',
            'unit_type' => fake()->randomElement(['SPM', 'SPB', 'MPM', 'BRUSH', 'HCB', 'REM612']),
            'statname' => (string) fake()->numberBetween(1, 50),
            'permstat_at_snapshot' => fake()->randomElement(['Granted', 'Denied', 'Pending', null]),
            'forester' => fake()->name(),
            'detected_date' => fake()->dateTimeBetween('-14 days', 'now'),
            'takeover_date' => fake()->dateTimeBetween('-30 days', '-15 days'),
            'takeover_username' => 'ONEPPL\\'.fake()->userName(),
        ];
    }
}
