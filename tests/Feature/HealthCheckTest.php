<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('Health Checks', function () {
    test('health endpoint returns JSON', function () {
        get('/health')
            ->assertOk()
            ->assertJsonStructure([
                'finishedAt',
                'checkResults' => [
                    '*' => [
                        'name',
                        'label',
                        'status',
                        'shortSummary',
                    ],
                ],
            ]);
    });

    test('health endpoint includes expected checks', function () {
        $response = get('/health')->json();

        $checkNames = collect($response['checkResults'])->pluck('name')->toArray();

        expect($checkNames)->toContain('Database');
        expect($checkNames)->toContain('Cache');
        expect($checkNames)->toContain('Disk Space');
        expect($checkNames)->toContain('WorkStudio API');
    });

    test('health dashboard returns HTML for authenticated users', function () {
        $user = User::factory()->create();

        actingAs($user)
            ->get('/health/dashboard')
            ->assertOk();
    });

    test('health dashboard requires authentication', function () {
        get('/health/dashboard')
            ->assertRedirect('/login');
    });
});
