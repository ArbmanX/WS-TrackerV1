<?php

use App\Models\Assessment;
use App\Models\User;
use App\Models\UserAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('factory creates valid model', function () {
    $ua = UserAssessment::factory()->create();

    expect($ua)->toBeInstanceOf(UserAssessment::class)
        ->and($ua->user_id)->not->toBeNull()
        ->and($ua->assessment_id)->not->toBeNull();
});

test('belongs to user', function () {
    $ua = UserAssessment::factory()->create();

    expect($ua->user)->toBeInstanceOf(User::class);
});

test('belongs to assessment', function () {
    $ua = UserAssessment::factory()->create();

    expect($ua->assessment)->toBeInstanceOf(Assessment::class);
});

test('unique constraint on user_id and assessment_id', function () {
    $user = User::factory()->create();
    $assessment = Assessment::factory()->create();

    UserAssessment::factory()->create(['user_id' => $user->id, 'assessment_id' => $assessment->id]);
    UserAssessment::factory()->create(['user_id' => $user->id, 'assessment_id' => $assessment->id]);
})->throws(\Illuminate\Database\QueryException::class);
