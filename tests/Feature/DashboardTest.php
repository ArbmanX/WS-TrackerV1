<?php

use App\Models\User;
use App\Models\UserSetting;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users with completed onboarding can visit the dashboard', function () {
    $user = User::factory()->create();
    UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('authenticated users without completed onboarding are redirected', function () {
    $user = User::factory()->create();
    UserSetting::factory()->firstLogin()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('onboarding.password'));
});
