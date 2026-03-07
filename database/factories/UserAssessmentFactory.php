<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserAssessment>
 */
class UserAssessmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assessment_id' => Assessment::factory(),
        ];
    }
}
