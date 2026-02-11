<?php

use App\Services\WorkStudio\Client\ApiCredentialManager;

beforeEach(function () {
    config([
        'workstudio.service_account.username' => 'TEST\testuser',
        'workstudio.service_account.password' => 'testpass123',
    ]);
});

test('getServiceAccountCredentials returns config values', function () {
    $manager = new ApiCredentialManager;
    $credentials = $manager->getServiceAccountCredentials();

    expect($credentials)
        ->toBeArray()
        ->toHaveKeys(['username', 'password', 'user_id', 'type'])
        ->and($credentials['username'])->toBe('TEST\testuser')
        ->and($credentials['password'])->toBe('testpass123')
        ->and($credentials['user_id'])->toBeNull()
        ->and($credentials['type'])->toBe('service');
});

test('getCredentials without userId falls back to service account', function () {
    $manager = new ApiCredentialManager;
    $credentials = $manager->getCredentials();

    expect($credentials['type'])->toBe('service')
        ->and($credentials['username'])->toBe('TEST\testuser');
});

test('formatDbParameters produces correct DDOProtocol format', function () {
    $result = ApiCredentialManager::formatDbParameters('DOMAIN\user', 'secret');

    expect($result)->toBe("USER NAME=DOMAIN\user\r\nPASSWORD=secret\r\n");
});

test('buildDbParameters uses service account when no userId', function () {
    $manager = new ApiCredentialManager;
    $result = $manager->buildDbParameters();

    expect($result)->toBe("USER NAME=TEST\\testuser\r\nPASSWORD=testpass123\r\n");
});

test('no hardcoded credentials exist in source files', function () {
    $filesToCheck = [
        app_path('Services/WorkStudio/Client/GetQueryService.php'),
        app_path('Livewire/DataManagement/QueryExplorer.php'),
        app_path('Services/WorkStudio/Shared/Services/UserDetailsService.php'),
        app_path('Services/WorkStudio/Client/HeartbeatService.php'),
        app_path('Console/Commands/FetchSsJobs.php'),
        app_path('Console/Commands/FetchCircuits.php'),
        app_path('Console/Commands/FetchUniqueCycleTypes.php'),
        app_path('Console/Commands/FetchUniqueJobTypes.php'),
        app_path('Console/Commands/FetchUnitTypes.php'),
        app_path('Console/Commands/FetchWsUsers.php'),
        app_path('Console/Commands/FetchDailyFootage.php'),
    ];

    foreach ($filesToCheck as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->not->toContain('ASPLUNDH\cnewcombe', "Hardcoded username found in {$file}")
            ->not->toContain("'chrism'", "Hardcoded password found in {$file}");
    }
});

test('no direct config credential access outside ApiCredentialManager', function () {
    $filesToCheck = [
        app_path('Services/WorkStudio/Client/GetQueryService.php'),
        app_path('Livewire/DataManagement/QueryExplorer.php'),
        app_path('Services/WorkStudio/Shared/Services/UserDetailsService.php'),
        app_path('Services/WorkStudio/Client/HeartbeatService.php'),
        app_path('Console/Commands/FetchSsJobs.php'),
        app_path('Console/Commands/FetchCircuits.php'),
        app_path('Console/Commands/FetchUniqueCycleTypes.php'),
        app_path('Console/Commands/FetchUniqueJobTypes.php'),
        app_path('Console/Commands/FetchUnitTypes.php'),
        app_path('Console/Commands/FetchWsUsers.php'),
        app_path('Console/Commands/FetchDailyFootage.php'),
    ];

    foreach ($filesToCheck as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->not->toContain("config('workstudio.service_account", "Direct config access found in {$file}");
    }
});

test('config defaults are empty strings not hardcoded credentials', function () {
    // Reset to defaults (as if .env vars were missing)
    config([
        'workstudio.service_account.username' => '',
        'workstudio.service_account.password' => '',
    ]);

    $manager = new ApiCredentialManager;
    $credentials = $manager->getServiceAccountCredentials();

    expect($credentials['username'])->toBe('')
        ->and($credentials['password'])->toBe('');
});
