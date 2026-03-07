<?php

use App\Livewire\UserManagement\CreateUser;
use App\Livewire\UserManagement\UserWizard;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding', 'permission:manage-users'])->prefix('user-management')->name('user-management.')->group(function () {
    Route::get('/create', CreateUser::class)->name('create');
    Route::get('/wizard', UserWizard::class)->name('wizard');
});
