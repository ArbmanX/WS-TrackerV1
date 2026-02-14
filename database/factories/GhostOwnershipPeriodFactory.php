<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GhostOwnershipPeriod>
 */
class GhostOwnershipPeriodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $unitCount = fake()->numberBetween(20, 100);

        return [
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'line_name' => fake()->numerify('Circuit-####'),
            'region' => fake()->randomElement(['NORTH', 'SOUTH', 'EAST', 'WEST', 'CENTRAL']),
            'takeover_date' => fake()->dateTimeBetween('-30 days', '-5 days'),
            'takeover_username' => 'ONEPPL\\'.fake()->userName(),
            'return_date' => null,
            'baseline_unit_count' => $unitCount,
            'baseline_snapshot' => $this->generateBaselineSnapshot($unitCount),
            'is_parent_takeover' => false,
            'status' => 'active',
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'return_date' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'return_date' => fake()->dateTimeBetween('-4 days', 'now'),
        ]);
    }

    public function parentTakeover(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_parent_takeover' => true,
        ]);
    }

    /**
     * @return list<array<string, string>>
     */
    private function generateBaselineSnapshot(int $count): array
    {
        $units = [];
        $unitTypes = ['SPM', 'SPB', 'MPM', 'BRUSH', 'HCB', 'REM612', 'HERBNA'];
        $permStatuses = ['Granted', 'Denied', 'Pending', 'Refused', 'Not Needed'];

        for ($i = 0; $i < min($count, 10); $i++) {
            $units[] = [
                'unitguid' => '{'.Str::uuid()->toString().'}',
                'unit_type' => fake()->randomElement($unitTypes),
                'statname' => (string) fake()->numberBetween(1, 50),
                'permstat' => fake()->randomElement($permStatuses),
                'forester' => fake()->name(),
            ];
        }

        return $units;
    }
}
