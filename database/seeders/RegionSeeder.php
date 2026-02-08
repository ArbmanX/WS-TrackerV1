<?php

namespace Database\Seeders;

use App\Models\Region;
use Illuminate\Database\Seeder;

class RegionSeeder extends Seeder
{
    /**
     * Geographic regions seeded into the database.
     *
     * @var array<int, array{name: string, display_name: string, sort_order: int}>
     */
    private const REGIONS = [
        ['name' => 'CENTRAL', 'display_name' => 'Central', 'sort_order' => 1],
        ['name' => 'HARRISBURG', 'display_name' => 'Harrisburg', 'sort_order' => 2],
        ['name' => 'LANCASTER', 'display_name' => 'Lancaster', 'sort_order' => 3],
        ['name' => 'LEHIGH', 'display_name' => 'Lehigh', 'sort_order' => 4],
        ['name' => 'NORTHEAST', 'display_name' => 'Northeast', 'sort_order' => 5],
        ['name' => 'SUSQUEHANNA', 'display_name' => 'Susquehanna', 'sort_order' => 6],
    ];

    /**
     * Seed the regions table.
     *
     * Idempotent — safe to run multiple times.
     */
    public function run(): void
    {
        foreach (self::REGIONS as $region) {
            Region::updateOrCreate(
                ['name' => $region['name']],
                [
                    'display_name' => $region['display_name'],
                    'sort_order' => $region['sort_order'],
                ],
            );
        }
    }
}
