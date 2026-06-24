<?php

use App\Services\IzinCache;
use Illuminate\Support\Facades\Http;

test('spdListForUser sends user_id and limit, then serves the second call from cache', function () {
    Http::fake([
        '*list-spd*' => Http::response(['data' => [['id' => 1], ['id' => 2]]], 200),
    ]);

    $cache = new IzinCache('http://api.test');

    expect($cache->spdListForUser(7, 'budi'))->toBe(['data' => [['id' => 1], ['id' => 2]]]);

    // Call kedua harus dari cache, tidak hit API lagi.
    $cache->spdListForUser(7, 'budi');

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_contains($request->url(), 'list-spd')
        && (int) $request['user_id'] === 7
        && (int) $request['limit'] === 1000);
});

test('spdListForUser returns empty array when the API responds with an error', function () {
    Http::fake([
        '*list-spd*' => Http::response([], 500),
    ]);

    $cache = new IzinCache('http://api.test');

    expect($cache->spdListForUser(7, 'budi'))->toBe([]);
});

test('flushUser clears the cached per-user SPD list', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        return Http::response(['data' => [['id' => $attempts]]], 200);
    });

    $cache = new IzinCache('http://api.test');

    expect($cache->spdListForUser(7, 'budi'))->toBe(['data' => [['id' => 1]]]);

    $cache->flushUser('budi');

    // Setelah flush, fetch ulang -> attempt kedua.
    expect($cache->spdListForUser(7, 'budi'))->toBe(['data' => [['id' => 2]]]);
    Http::assertSentCount(2);
});
