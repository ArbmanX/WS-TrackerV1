<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WsUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserWsIdentity>
 */
class UserWsIdentityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ws_user_id' => WsUser::factory(),
            'is_primary' => false,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
