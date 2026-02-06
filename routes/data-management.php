<?php

use App\Livewire\DataManagement\CacheControls;
use App\Livewire\DataManagement\QueryExplorer;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Data Management Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding'])->prefix('data-management')->name('data-management.')->group(function () {
    Route::get('/cache', CacheControls::class)->name('cache');
    Route::get('/query-explorer', QueryExplorer::class)->name('query-explorer');
});
