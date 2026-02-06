<?php

use App\Livewire\UserManagement\CreateUser;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding', 'permission:manage-users'])->prefix('user-management')->name('user-management.')->group(function () {
    Route::get('/create', CreateUser::class)->name('create');
});
