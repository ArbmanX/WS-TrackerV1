<?php

use App\Livewire\Assessments\Index;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Assessment Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'onboarding'])
    ->prefix('assessments')
    ->name('assessments.')
    ->group(function () {
        Route::get('/', Index::class)->name('index');
    });
