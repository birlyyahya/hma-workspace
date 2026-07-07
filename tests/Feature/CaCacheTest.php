<?php

use App\Services\CaCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('dashboard returns the json payload on success', function () {
    Http::fake([
        '*/cashadvance*' => Http::response(['data' => [['id' => 1, 'amount' => 500]]], 200),
    ]);

    $result = (new CaCache('http://api.test'))->dashboard(1);

    expect($result['data'])->toBe([['id' => 1, 'amount' => 500]]);
});

test('dashboard caches a successful response so the API is hit once', function () {
    Http::fake([
        '*/cashadvance*' => Http::response(['data' => [['id' => 1]]], 200),
    ]);

    $cache = new CaCache('http://api.test');

    $cache->dashboard(1);
    $cache->dashboard(1);

    Http::assertSentCount(1);
});

test('dashboard returns empty without throwing when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    expect((new CaCache('http://api.test'))->dashboard(1))->toBe([]);
});

test('dashboard returns empty without throwing when the API responds with a server error', function () {
    Http::fake([
        '*/cashadvance*' => Http::response(['message' => 'boom'], 500),
    ]);

    expect((new CaCache('http://api.test'))->dashboard(1))->toBe([]);
});

test('flush clears the cached dashboard so a later call re-fetches', function () {
    Http::fake([
        '*/cashadvance*' => Http::response(['data' => [['id' => 1]]], 200),
    ]);

    $cache = new CaCache('http://api.test');

    $cache->dashboard(1);
    $cache->flush();
    $cache->dashboard(1);

    Http::assertSentCount(2);
});
