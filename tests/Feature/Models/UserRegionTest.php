<?php

use App\Models\Region;
use App\Models\User;
use App\Models\UserRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory creates valid model', function () {
    $ur = UserRegion::factory()->create();

    expect($ur)->toBeInstanceOf(UserRegion::class)
        ->and($ur->user_id)->not->toBeNull()
        ->and($ur->region_id)->not->toBeNull();
});

test('belongs to user', function () {
    $ur = UserRegion::factory()->create();

    expect($ur->user)->toBeInstanceOf(User::class);
});

test('belongs to region', function () {
    $ur = UserRegion::factory()->create();

    expect($ur->region)->toBeInstanceOf(Region::class);
});

test('unique constraint on user_id and region_id', function () {
    $user = User::factory()->create();
    $region = Region::factory()->create();

    UserRegion::factory()->create(['user_id' => $user->id, 'region_id' => $region->id]);
    UserRegion::factory()->create(['user_id' => $user->id, 'region_id' => $region->id]);
})->throws(\Illuminate\Database\QueryException::class);
