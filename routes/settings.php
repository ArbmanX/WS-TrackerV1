<?php

/**
 * Settings Routes - Temporarily Disabled for UI Rebuild
 *
 * These routes will be re-enabled when the new settings UI is implemented.
 * The settings functionality (Profile, Password, Appearance, 2FA) will be
 * rebuilt with the new dashboard design.
 */

use Illuminate\Support\Facades\Route;

// Settings routes disabled - redirect to dashboard
Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'dashboard');
    Route::redirect('settings/profile', 'dashboard');
    Route::redirect('settings/password', 'dashboard');
    Route::redirect('settings/appearance', 'dashboard');
    Route::redirect('settings/two-factor', 'dashboard');
});

/*
|--------------------------------------------------------------------------
| Original Settings Routes (for reference)
|--------------------------------------------------------------------------
|
| use App\Livewire\Settings\Appearance;
| use App\Livewire\Settings\Password;
| use App\Livewire\Settings\Profile;
| use App\Livewire\Settings\TwoFactor;
| use Laravel\Fortify\Features;
|
| Route::middleware(['auth'])->group(function () {
|     Route::redirect('settings', 'settings/profile');
|     Route::livewire('settings/profile', Profile::class)->name('profile.edit');
| });
|
| Route::middleware(['auth', 'verified'])->group(function () {
|     Route::livewire('settings/password', Password::class)->name('user-password.edit');
|     Route::livewire('settings/appearance', Appearance::class)->name('appearance.edit');
|     Route::livewire('settings/two-factor', TwoFactor::class)
|         ->middleware(...)
|         ->name('two-factor.show');
| });
|
*/
