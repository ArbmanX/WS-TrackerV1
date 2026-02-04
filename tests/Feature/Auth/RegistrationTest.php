<?php

// Registration is disabled in this application (admin creates users)
// These tests are skipped but kept for reference if registration is re-enabled

test('registration screen cannot be accessed when disabled', function () {
    // Registration route should not exist
    expect(Route::has('register'))->toBeFalse();
})->skip('Registration is disabled');

test('new users cannot self-register', function () {
    // This would fail since registration is disabled
})->skip('Registration is disabled');
