<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlannerCareerEntry>
 */
class PlannerCareerEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $workingDays = fake()->numberBetween(15, 60);
        $totalFootageFeet = fake()->randomFloat(1, 10000, 80000);
        $firstDate = fake()->dateTimeBetween('-6 months', '-2 months');
        $lastDate = fake()->dateTimeBetween($firstDate, '-1 month');

        return [
            'planner_username' => fake()->userName(),
            'planner_display_name' => fake()->name(),
            'job_guid' => '{'.Str::uuid()->toString().'}',
            'line_name' => fake()->numerify('Circuit-####'),
            'region' => fake()->randomElement(['NORTH', 'SOUTH', 'EAST', 'WEST', 'CENTRAL']),
            'scope_year' => (string) now()->year,
            'cycle_type' => fake()->randomElement(['Trim', 'Herbicide', 'Hazard Tree', null]),
            'assessment_total_miles' => fake()->randomFloat(4, 5, 25),
            'assessment_completed_miles' => fake()->randomFloat(4, 3, 20),
            'assessment_pickup_date' => $firstDate,
            'assessment_qc_date' => $lastDate,
            'assessment_close_date' => fake()->dateTimeBetween($lastDate, 'now'),
            'went_to_rework' => false,
            'rework_details' => null,
            'daily_metrics' => $this->generateDailyMetrics($firstDate, $workingDays),
            'summary_totals' => [
                'total_footage_feet' => $totalFootageFeet,
                'total_footage_miles' => round($totalFootageFeet / 5280, 2),
                'total_stations' => fake()->numberBetween(100, 400),
                'total_work_units' => fake()->numberBetween(100, 500),
                'total_nw_units' => fake()->numberBetween(10, 80),
                'working_days' => $workingDays,
                'avg_daily_footage_feet' => round($totalFootageFeet / $workingDays, 1),
                'first_activity_date' => $firstDate->format('Y-m-d'),
                'last_activity_date' => $lastDate->format('Y-m-d'),
            ],
            'source' => 'bootstrap',
        ];
    }

    public function withRework(): static
    {
        return $this->state(fn (array $attributes) => [
            'went_to_rework' => true,
            'rework_details' => [
                'rework_count' => fake()->numberBetween(1, 3),
                'audit_user' => 'ONEPPL\\'.fake()->userName(),
                'audit_date' => fake()->date(),
                'audit_notes' => fake()->sentence(),
                'failed_unit_count' => fake()->numberBetween(1, 10),
            ],
        ]);
    }

    public function fromBootstrap(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'bootstrap',
        ]);
    }

    public function fromLiveMonitor(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'live_monitor',
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function generateDailyMetrics(\DateTimeInterface $startDate, int $days): array
    {
        $metrics = [];
        $date = \Carbon\CarbonImmutable::instance($startDate);

        for ($i = 0; $i < min($days, 10); $i++) {
            $key = $date->format('Y-m-d');
            $metrics[$key] = [
                'footage_feet' => fake()->randomFloat(1, 500, 3000),
                'stations_completed' => fake()->numberBetween(3, 20),
                'work_units' => fake()->numberBetween(5, 25),
                'nw_units' => fake()->numberBetween(0, 5),
            ];
            $date = $date->addDay();
        }

        return $metrics;
    }
}
