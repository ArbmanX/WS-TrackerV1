<?php

use App\Models\WsUser;
use Illuminate\Support\Facades\Http;

function fakeUserListResponse(): array
{
    return [
        'Heading' => ['username'],
        'Data' => [
            ['ASPLUNDH\\jsmith'],
            ['ASPLUNDH\\jdoe'],
            ['OTHERDOMAIN\\bwilson'],
        ],
    ];
}

function fakeUserDetailsResponse(string $fullName = 'John Smith', string $email = 'jsmith@test.com'): array
{
    return [
        'UserObject' => [
            'UserName' => 'ASPLUNDH\\jsmith',
            'FullName' => $fullName,
            'DomainName' => 'ASPLUNDH',
            'EmailAddress' => $email,
            'Enabled' => true,
        ],
        'Groups' => ['Field Crew', 'Vegetation Management'],
    ];
}

test('dry-run does not modify database', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUserListResponse())]);

    $this->artisan('ws:fetch-users --dry-run')
        ->assertSuccessful();

    expect(WsUser::count())->toBe(0);
});

test('creates ws_user records from API response', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUserListResponse())]);

    $this->artisan('ws:fetch-users')
        ->assertSuccessful();

    expect(WsUser::count())->toBe(3);

    $jsmith = WsUser::where('username', 'ASPLUNDH\\jsmith')->first();
    expect($jsmith->domain)->toBe('ASPLUNDH')
        ->and($jsmith->last_synced_at)->toBeNull();

    $bwilson = WsUser::where('username', 'OTHERDOMAIN\\bwilson')->first();
    expect($bwilson->domain)->toBe('OTHERDOMAIN');
});

test('does not duplicate users on re-run', function () {
    Http::fake(['*/GETQUERY' => Http::response(fakeUserListResponse())]);

    $this->artisan('ws:fetch-users')->assertSuccessful();
    $this->artisan('ws:fetch-users')->assertSuccessful();

    expect(WsUser::count())->toBe(3);
});

test('enrich flag enriches unenriched users', function () {
    Http::fake([
        '*/GETQUERY' => Http::response(fakeUserListResponse()),
        '*/GETUSERDETAILS' => Http::response(fakeUserDetailsResponse()),
    ]);

    $this->artisan('ws:fetch-users --enrich')
        ->assertSuccessful();

    expect(WsUser::count())->toBe(3);

    $enriched = WsUser::whereNotNull('last_synced_at')->count();
    expect($enriched)->toBe(3);
});

test('handles API error response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'protocol' => 'ERROR',
        'errorMessage' => 'Test error',
    ])]);

    $this->artisan('ws:fetch-users --dry-run')
        ->assertFailed();
});

test('handles empty API response gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response(null, 200)]);

    $this->artisan('ws:fetch-users')
        ->assertFailed();
});

test('handles empty data set gracefully', function () {
    Http::fake(['*/GETQUERY' => Http::response([
        'Heading' => ['username'],
        'Data' => [],
    ])]);

    $this->artisan('ws:fetch-users')
        ->assertSuccessful();

    expect(WsUser::count())->toBe(0);
});
