<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user has completed WorkStudio onboarding.
     *
     * @param  array<string, mixed>  $overrides  Override any WS field
     */
    public function withWorkStudio(array $overrides = []): static
    {
        return $this->state(fn (array $attributes) => array_merge([
            'ws_username' => 'jsmith',
            'ws_full_name' => 'John Smith',
            'ws_domain' => 'ASPLUNDH',
            'ws_groups' => ['WorkStudio\\Everyone', 'ASPLUNDH\\VEG_PLANNERS'],
            'ws_resource_groups' => [
                'CENTRAL', 'HARRISBURG', 'LEHIGH', 'LANCASTER',
                'DISTRIBUTION', 'PRE_PLANNER', 'VEG_ASSESSORS', 'VEG_PLANNERS',
            ],
            'ws_validated_at' => now(),
        ], $overrides));
    }

    /**
     * Assign a role to the user after creation.
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function ($user) use ($role) {
            $user->assignRole($role);
        });
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
