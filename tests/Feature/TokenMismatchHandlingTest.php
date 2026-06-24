<?php

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__throw-token-mismatch', function () {
        throw new TokenMismatchException('CSRF token mismatch.');
    });
});

test('expired session (419) redirects web requests to login with a status message', function () {
    $this->get('/__throw-token-mismatch')
        ->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Sesi Anda telah berakhir. Silakan masuk kembali.');
});

test('expired session (419) still returns 419 for json requests', function () {
    $this->getJson('/__throw-token-mismatch')
        ->assertStatus(419);
});
