<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('allows access from a mobile (iPhone) user agent', function () {
    $iphoneUa = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    $this->withHeader('User-Agent', $iphoneUa)
        ->get(route('izin.quick'))
        ->assertOk();
});

it('allows access from an Android phone user agent', function () {
    $androidPhoneUa = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';

    $this->withHeader('User-Agent', $androidPhoneUa)
        ->get(route('izin.quick'))
        ->assertOk();
});

it('redirects desktop browser to izin index', function () {
    $desktopUa = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15';

    $this->withHeader('User-Agent', $desktopUa)
        ->get(route('izin.quick'))
        ->assertRedirect(route('izin'));
});

it('redirects iPad (treated as non-phone) to izin index', function () {
    $ipadUa = 'Mozilla/5.0 (iPad; CPU OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1';

    $this->withHeader('User-Agent', $ipadUa)
        ->get(route('izin.quick'))
        ->assertRedirect(route('izin'));
});

it('redirects when User-Agent is empty', function () {
    $this->withHeader('User-Agent', '')
        ->get(route('izin.quick'))
        ->assertRedirect(route('izin'));
});
