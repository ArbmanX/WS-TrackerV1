<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dev Routes (local environment only)
|--------------------------------------------------------------------------
|
| These routes are only registered when APP_ENV=local.
| Used for component previewing, design iteration, and testing.
|
| Access: /dev/preview — component preview sandbox (no auth required)
|
*/

Route::prefix('dev')->name('dev.')->group(function () {
    Route::get('/preview/{component?}', function (?string $component = null) {
        return view('dev.preview', [
            'component' => $component,
        ]);
    })->where('component', '.*')->name('preview');
});
