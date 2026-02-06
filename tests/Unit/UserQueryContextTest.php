<?php

use App\Models\User;
use App\Services\WorkStudio\Shared\ValueObjects\UserQueryContext;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

test('fromUser builds context from onboarded user with ws_resource_groups', function () {
    $user = User::factory()->withWorkStudio()->create();

    $context = UserQueryContext::fromUser($user);

    expect($context->resourceGroups)->toBe([
        'CENTRAL', 'HARRISBURG', 'LEHIGH', 'LANCASTER',
        'DISTRIBUTION', 'PRE_PLANNER', 'VEG_ASSESSORS', 'VEG_PLANNERS',
    ]);
    expect($context->contractors)->toBe(['Asplundh']);
    expect($context->domain)->toBe('ASPLUNDH');
    expect($context->username)->toBe('jsmith');
    expect($context->userId)->toBe($user->id);
});

test('fromUser falls back to config for unonboarded user', function () {
    $user = User::factory()->create();

    $context = UserQueryContext::fromUser($user);

    expect($context->resourceGroups)->toBe(config('workstudio_resource_groups.roles.planner'));
    expect($context->contractors)->toBe(config('ws_assessment_query.contractors'));
    expect($context->username)->toBe('system');
    expect($context->userId)->toBe($user->id);
});

test('fromUser resolves groups dynamically when ws_resource_groups is null', function () {
    $user = User::factory()->withWorkStudio([
        'ws_resource_groups' => null,
    ])->create();

    $context = UserQueryContext::fromUser($user);

    // VEG_PLANNERS maps to all 8 planner regions via group_to_region_map
    expect($context->resourceGroups)->toHaveCount(8);
    expect($context->resourceGroups)->toContain('CENTRAL', 'HARRISBURG');
});

test('fromConfig returns config-based defaults', function () {
    $context = UserQueryContext::fromConfig(99);

    expect($context->resourceGroups)->toBe(config('workstudio_resource_groups.roles.planner'));
    expect($context->contractors)->toBe(config('ws_assessment_query.contractors'));
    expect($context->userId)->toBe(99);
});

test('fromConfig works with null userId', function () {
    $context = UserQueryContext::fromConfig();

    expect($context->userId)->toBe(0);
});

test('cacheHash is deterministic for same data', function () {
    $user = User::factory()->withWorkStudio()->create();

    $context1 = UserQueryContext::fromUser($user);
    $context2 = UserQueryContext::fromUser($user);

    expect($context1->cacheHash())->toBe($context2->cacheHash());
});

test('cacheHash is identical for users with same access', function () {
    $user1 = User::factory()->withWorkStudio(['ws_username' => 'user1'])->create();
    $user2 = User::factory()->withWorkStudio(['ws_username' => 'user2'])->create();

    $context1 = UserQueryContext::fromUser($user1);
    $context2 = UserQueryContext::fromUser($user2);

    // Same resource groups + contractors = same hash, despite different usernames
    expect($context1->cacheHash())->toBe($context2->cacheHash());
});

test('cacheHash differs when resource groups differ', function () {
    $user1 = User::factory()->withWorkStudio()->create();
    $user2 = User::factory()->withWorkStudio([
        'ws_resource_groups' => ['CENTRAL', 'HARRISBURG'],
    ])->create();

    $context1 = UserQueryContext::fromUser($user1);
    $context2 = UserQueryContext::fromUser($user2);

    expect($context1->cacheHash())->not->toBe($context2->cacheHash());
});

test('cacheHash is order-independent', function () {
    $context1 = new UserQueryContext(
        resourceGroups: ['CENTRAL', 'HARRISBURG'],
        contractors: ['Asplundh'],
        domain: 'ASPLUNDH',
        username: 'test',
        userId: 1,
    );

    $context2 = new UserQueryContext(
        resourceGroups: ['HARRISBURG', 'CENTRAL'],
        contractors: ['Asplundh'],
        domain: 'ASPLUNDH',
        username: 'test',
        userId: 1,
    );

    expect($context1->cacheHash())->toBe($context2->cacheHash());
});

test('isValid requires at least one region and one contractor', function () {
    $valid = new UserQueryContext(
        resourceGroups: ['CENTRAL'],
        contractors: ['Asplundh'],
        domain: 'ASPLUNDH',
        username: 'test',
        userId: 1,
    );

    $noRegions = new UserQueryContext(
        resourceGroups: [],
        contractors: ['Asplundh'],
        domain: 'ASPLUNDH',
        username: 'test',
        userId: 1,
    );

    $noContractors = new UserQueryContext(
        resourceGroups: ['CENTRAL'],
        contractors: [],
        domain: 'ASPLUNDH',
        username: 'test',
        userId: 1,
    );

    expect($valid->isValid())->toBeTrue();
    expect($noRegions->isValid())->toBeFalse();
    expect($noContractors->isValid())->toBeFalse();
});

test('contractor is derived from ws_domain with proper casing', function () {
    $user = User::factory()->withWorkStudio(['ws_domain' => 'ASPLUNDH'])->create();

    $context = UserQueryContext::fromUser($user);

    // "ASPLUNDH" → ucfirst(strtolower()) → "Asplundh"
    expect($context->contractors)->toBe(['Asplundh']);
    expect($context->domain)->toBe('ASPLUNDH');
});
