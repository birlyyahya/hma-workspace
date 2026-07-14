<?php

use App\Services\ProjectCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('spectechFor returns empty without throwing when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $cache = new ProjectCache('http://api.test');

    expect($cache->spectechFor(123))->toBe([]);
});

test('spectechFor caches a successful response', function () {
    Http::fake([
        '*spekteks/search*' => Http::response(['status' => 200, 'data' => [['id' => 1]]], 200),
    ]);

    $cache = new ProjectCache('http://api.test');

    expect($cache->spectechFor(7))->toBe([['id' => 1]]);

    $cache->spectechFor(7);

    Http::assertSentCount(1);
});

test('a failed fetch is not cached so a later success still loads', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        // First spectechFor call makes 2 attempts (initial + 1 retry) that all fail.
        if ($attempts <= 2) {
            throw new ConnectionException('Connection timed out');
        }

        return Http::response(['status' => 200, 'data' => [['id' => 9]]], 200);
    });

    $cache = new ProjectCache('http://api.test');

    expect($cache->spectechFor(50))->toBe([]);
    expect($cache->spectechFor(50))->toBe([['id' => 9]]);
});

test('defaultProjectsList returns empty without throwing when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $cache = new ProjectCache('http://api.test');

    expect($cache->defaultProjectsList())->toBe([]);
});
