<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Seed reference data tables.
     *
     * Runs RegionSeeder first (FK dependency), then CircuitSeeder.
     */
    public function run(): void
    {
        $this->call([
            RegionSeeder::class,
            CircuitSeeder::class,
        ]);
    }
}
