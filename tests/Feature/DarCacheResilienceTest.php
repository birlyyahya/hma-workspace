<?php

use App\Services\DarCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('list returns an empty data set without throwing when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $cache = new DarCache('http://api.test');

    expect($cache->list('all'))->toBe(['data' => []]);
});

test('list caches a successful response', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [['id' => 1]]], 200),
    ]);

    $cache = new DarCache('http://api.test');

    expect($cache->list('all'))->toBe(['data' => [['id' => 1]]]);

    $cache->list('all');

    Http::assertSentCount(1);
});

test('a failed fetch is not cached so a later success still loads', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        // First list() call makes 2 attempts (initial + 1 retry) that all fail.
        if ($attempts <= 2) {
            throw new ConnectionException('Connection timed out');
        }

        return Http::response(['data' => [['id' => 9]]], 200);
    });

    $cache = new DarCache('http://api.test');

    expect($cache->list('user', 7))->toBe(['data' => []]);
    expect($cache->list('user', 7))->toBe(['data' => [['id' => 9]]]);
});
