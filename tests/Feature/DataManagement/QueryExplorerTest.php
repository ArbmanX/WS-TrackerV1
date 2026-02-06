<?php

use App\Livewire\DataManagement\QueryExplorer;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolePermissionSeeder::class);
    $user = \App\Models\User::factory()->withWorkStudio()->withRole('sudo-admin')->create();
    \App\Models\UserSetting::factory()->onboarded()->create(['user_id' => $user->id]);
    $this->actingAs($user);
});

test('guests cannot access query explorer', function () {
    auth()->logout();

    $this->get(route('data-management.query-explorer'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit query explorer', function () {
    Livewire::test(QueryExplorer::class)
        ->assertOk()
        ->assertSee('Query Explorer')
        ->assertSee('Query Builder');
});

test('table is required to run a query', function () {
    Livewire::test(QueryExplorer::class)
        ->set('table', '')
        ->call('runQuery')
        ->assertSet('error', 'Table name is required.');
});

test('top must be between 1 and 500', function () {
    Livewire::test(QueryExplorer::class)
        ->set('table', 'VEGJOB')
        ->set('top', 0)
        ->call('runQuery')
        ->assertHasErrors(['top']);
});

test('successful query displays results', function () {
    Http::fake([
        '*/GETQUERY' => Http::response([
            'protocol' => 'SUCCESS',
            'Heading' => ['JOBGUID', 'STATUS', 'REGION'],
            'Data' => [
                ['{ABC-123}', 'ACTIV', 'NORTH'],
                ['{DEF-456}', 'QC', 'SOUTH'],
            ],
        ]),
    ]);

    Livewire::test(QueryExplorer::class)
        ->set('table', 'VEGJOB')
        ->set('fields', 'JOBGUID, STATUS, REGION')
        ->set('top', 5)
        ->call('runQuery')
        ->assertSet('rowCount', 2)
        ->assertSet('executedSql', 'SELECT TOP 5 JOBGUID, STATUS, REGION FROM VEGJOB')
        ->assertSee('2 rows');
});

test('where clause is appended to sql', function () {
    Http::fake([
        '*/GETQUERY' => Http::response([
            'protocol' => 'SUCCESS',
            'Heading' => ['JOBGUID'],
            'Data' => [['{ABC-123}']],
        ]),
    ]);

    Livewire::test(QueryExplorer::class)
        ->set('table', 'VEGJOB')
        ->set('fields', 'JOBGUID')
        ->set('top', 10)
        ->set('whereClause', "STATUS = 'ACTIV'")
        ->call('runQuery')
        ->assertSet('executedSql', "SELECT TOP 10 JOBGUID FROM VEGJOB WHERE STATUS = 'ACTIV'");
});

test('api error is displayed', function () {
    Http::fake([
        '*/GETQUERY' => Http::response([
            'protocol' => 'ERROR',
            'errorMessage' => 'Invalid object name BADTABLE',
        ]),
    ]);

    Livewire::test(QueryExplorer::class)
        ->set('table', 'BADTABLE')
        ->call('runQuery')
        ->assertSet('error', 'Invalid object name BADTABLE')
        ->assertSee('Query Failed');
});

test('clear results resets state', function () {
    Livewire::test(QueryExplorer::class)
        ->set('results', '[]')
        ->set('error', 'some error')
        ->set('executedSql', 'SELECT 1')
        ->set('queryTime', 1.5)
        ->set('rowCount', 10)
        ->call('clearResults')
        ->assertSet('results', null)
        ->assertSet('error', '')
        ->assertSet('executedSql', '')
        ->assertSet('queryTime', null)
        ->assertSet('rowCount', 0);
});

test('common tables are available', function () {
    Livewire::test(QueryExplorer::class)
        ->assertSee('VEGJOB')
        ->assertSee('VEGUNIT')
        ->assertSee('STATIONS');
});
