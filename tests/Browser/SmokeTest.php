<?php

use Laravel\Dusk\Browser;

/**
 * Smoke tests verify basic application functionality.
 * These run quickly and catch obvious deployment issues.
 */
test('login page loads successfully', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertSee('Login')
            ->assertPresent('input[type="email"]')
            ->assertPresent('input[type="password"]');
    });
});

test('login page has no javascript errors', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertNoJavascriptErrors();
    });
});

test('unauthenticated user is redirected to login', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/dashboard')
            ->assertPathIs('/login');
    });
});
