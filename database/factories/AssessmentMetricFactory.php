<?php

namespace Database\Factories;

use App\Models\Assessment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssessmentMetric>
 */
class AssessmentMetricFactory extends Factory
{
    public function definition(): array
    {
        $totalUnits = fake()->numberBetween(30, 120);
        $remaining = $totalUnits;
        $approved = fake()->numberBetween(10, (int) ($remaining * 0.5));
        $remaining -= $approved;
        $pending = fake()->numberBetween(2, max(2, (int) ($remaining * 0.5)));
        $remaining -= $pending;
        $refused = fake()->numberBetween(0, min(3, $remaining));
        $remaining -= $refused;
        $noContact = fake()->numberBetween(0, min(3, $remaining));
        $remaining -= $noContact;
        $deferred = fake()->numberBetween(0, min(2, $remaining));
        $remaining -= $deferred;
        $pplApproved = $remaining;

        $unitsRequiringNotes = fake()->numberBetween(10, $totalUnits);
        $unitsWithNotes = fake()->numberBetween(5, $unitsRequiringNotes);
        $unitsWithoutNotes = $unitsRequiringNotes - $unitsWithNotes;
        $compliancePercent = $unitsRequiringNotes > 0
            ? round(($unitsWithNotes / $unitsRequiringNotes) * 100, 1)
            : null;

        return [
            'job_guid' => fn () => Assessment::factory()->create()->job_guid,
            'work_order' => fake()->numerify('WO-######'),
            'extension' => '@',
            'total_units' => $totalUnits,
            'approved' => $approved,
            'pending' => $pending,
            'refused' => $refused,
            'no_contact' => $noContact,
            'deferred' => $deferred,
            'ppl_approved' => $pplApproved,
            'units_requiring_notes' => $unitsRequiringNotes,
            'units_with_notes' => $unitsWithNotes,
            'units_without_notes' => $unitsWithoutNotes,
            'notes_compliance_percent' => $compliancePercent,
            'pending_over_threshold' => fake()->numberBetween(0, 8),
            'stations_with_work' => fake()->numberBetween(5, 30),
            'stations_no_work' => fake()->numberBetween(0, 10),
            'stations_not_planned' => fake()->numberBetween(0, 5),
            'split_count' => null,
            'split_updated' => null,
            'taken_date' => fake()->date(),
            'sent_to_qc_date' => fake()->optional(0.5)->date(),
            'sent_to_rework_date' => null,
            'closed_date' => null,
            'first_unit_date' => fake()->date(),
            'last_unit_date' => fake()->date(),
            'oldest_pending_date' => fake()->optional(0.3)->date(),
            'oldest_pending_statname' => fake()->optional(0.3)->numerify('STA-###'),
            'oldest_pending_unit' => fake()->optional(0.3)->lexify('???###'),
            'oldest_pending_sequence' => fake()->optional(0.3)->numberBetween(1, 500),
            'work_type_breakdown' => $this->sampleWorkTypeBreakdown(),
        ];
    }

    public function withHighCompliance(): static
    {
        return $this->state(function (array $attributes) {
            $requiring = $attributes['units_requiring_notes'];
            $withNotes = (int) ($requiring * 0.95);

            return [
                'units_with_notes' => $withNotes,
                'units_without_notes' => $requiring - $withNotes,
                'notes_compliance_percent' => round(($withNotes / max($requiring, 1)) * 100, 1),
            ];
        });
    }

    public function withAgingUnits(): static
    {
        return $this->state(fn (array $attributes) => [
            'pending_over_threshold' => fake()->numberBetween(5, 20),
            'oldest_pending_date' => now()->subDays(30)->format('Y-m-d'),
            'oldest_pending_statname' => fake()->numerify('STA-###'),
            'oldest_pending_unit' => fake()->lexify('???###'),
            'oldest_pending_sequence' => fake()->numberBetween(1, 500),
        ]);
    }

    private function sampleWorkTypeBreakdown(): array
    {
        $codes = ['SPM', 'SPB', 'REM612', 'BRUSH', 'HAZ18'];

        return collect(fake()->randomElements($codes, fake()->numberBetween(2, 4)))
            ->map(fn (string $code) => [
                'unit' => $code,
                'display_name' => $code,
                'quantity' => fake()->numberBetween(1, 50),
            ])
            ->values()
            ->all();
    }
}
