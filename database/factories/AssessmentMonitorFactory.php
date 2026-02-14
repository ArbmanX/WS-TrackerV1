<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentMonitor>
 */
class AssessmentMonitorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'line_name' => fake()->numerify('Circuit-####'),
            'region' => fake()->randomElement(['NORTH', 'SOUTH', 'EAST', 'WEST', 'CENTRAL']),
            'scope_year' => (string) now()->year,
            'cycle_type' => fake()->randomElement(['Trim', 'Herbicide', 'Hazard Tree', null]),
            'current_status' => 'ACTIV',
            'current_planner' => fake()->name(),
            'total_miles' => fake()->randomFloat(4, 5, 25),
            'daily_snapshots' => [],
            'latest_snapshot' => null,
            'first_snapshot_date' => null,
            'last_snapshot_date' => null,
        ];
    }

    public function withSnapshots(int $days = 5): static
    {
        return $this->state(function (array $attributes) use ($days) {
            $snapshots = [];
            $date = now()->subDays($days);

            for ($i = 0; $i < $days; $i++) {
                $key = $date->format('Y-m-d');
                $snapshots[$key] = $this->generateSnapshot();
                $date = $date->addDay();
            }

            $dates = array_keys($snapshots);

            return [
                'daily_snapshots' => $snapshots,
                'latest_snapshot' => end($snapshots),
                'first_snapshot_date' => reset($dates),
                'last_snapshot_date' => end($dates),
            ];
        });
    }

    public function inQc(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'QC',
        ]);
    }

    public function inRework(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_status' => 'REWRK',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSnapshot(): array
    {
        $workUnits = fake()->numberBetween(30, 80);
        $nwUnits = fake()->numberBetween(5, 20);
        $totalUnits = $workUnits + $nwUnits;
        $unitsWithNotes = fake()->numberBetween(20, $totalUnits);

        return [
            'permission_breakdown' => [
                'Granted' => fake()->numberBetween(20, 50),
                'Denied' => fake()->numberBetween(0, 5),
                'Pending' => fake()->numberBetween(5, 20),
                'Refused' => fake()->numberBetween(0, 3),
                'Not Needed' => fake()->numberBetween(3, 15),
            ],
            'unit_counts' => [
                'work_units' => $workUnits,
                'nw_units' => $nwUnits,
                'total_units' => $totalUnits,
            ],
            'work_type_breakdown' => [
                'SPM' => fake()->numberBetween(5, 20),
                'SPB' => fake()->numberBetween(3, 15),
                'REM612' => fake()->numberBetween(2, 10),
                'BRUSH' => fake()->numberBetween(1, 8),
            ],
            'footage' => [
                'completed_feet' => fake()->randomFloat(1, 5000, 50000),
                'completed_miles' => fake()->randomFloat(2, 1, 10),
                'percent_complete' => fake()->randomFloat(1, 10, 95),
            ],
            'notes_compliance' => [
                'units_with_notes' => $unitsWithNotes,
                'units_without_notes' => $totalUnits - $unitsWithNotes,
                'compliance_percent' => round(($unitsWithNotes / $totalUnits) * 100, 1),
            ],
            'planner_activity' => [
                'last_edit_date' => now()->subDays(fake()->numberBetween(0, 7))->format('Y-m-d'),
                'days_since_last_edit' => fake()->numberBetween(0, 7),
            ],
            'aging_units' => [
                'pending_over_threshold' => fake()->numberBetween(0, 8),
                'threshold_days' => config('ws_data_collection.thresholds.aging_unit_days', 14),
            ],
            'suspicious' => false,
        ];
    }
}
