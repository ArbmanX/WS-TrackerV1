<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserRegion>
 */
class UserRegionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'region_id' => Region::factory(),
        ];
    }
}
