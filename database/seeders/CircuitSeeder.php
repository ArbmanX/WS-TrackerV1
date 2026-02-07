<?php

namespace Database\Seeders;

use App\Models\Circuit;
use App\Models\Region;
use Illuminate\Database\Seeder;

class CircuitSeeder extends Seeder
{
    /**
     * Seed the circuits table from the generated data file.
     *
     * Idempotent — safe to run multiple times.
     * Skips gracefully if the data file does not exist.
     */
    public function run(): void
    {
        $dataFile = database_path('data/circuits.php');

        if (! file_exists($dataFile)) {
            $this->command?->warn('Skipping CircuitSeeder — data file not found: database/data/circuits.php');
            $this->command?->info('Run "php artisan ws:fetch-circuits --save" to generate it.');

            return;
        }

        $circuits = require $dataFile;

        if (! is_array($circuits)) {
            $this->command?->error('Invalid data file format: database/data/circuits.php');

            return;
        }

        // Pre-load region name → id map
        $regionMap = Region::pluck('id', 'name')->all();

        $created = 0;
        $updated = 0;

        foreach ($circuits as $circuit) {
            $regionId = $regionMap[$circuit['region'] ?? ''] ?? null;

            $result = Circuit::updateOrCreate(
                ['line_name' => $circuit['line_name']],
                [
                    'region_id' => $regionId,
                    'last_seen_at' => now(),
                ],
            );

            $result->wasRecentlyCreated ? $created++ : $updated++;
        }

        $this->command?->info("CircuitSeeder complete: {$created} created, {$updated} updated.");
    }
}
