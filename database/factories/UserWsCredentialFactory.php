<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserWsCredential>
 */
class UserWsCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'encrypted_username' => 'ASPLUNDH\\'.fake()->userName(),
            'encrypted_password' => fake()->password(12),
            'is_valid' => true,
            'validated_at' => now(),
            'last_used_at' => null,
        ];
    }

    /**
     * Mark credentials as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_valid' => false,
            'validated_at' => null,
        ]);
    }
}
