<?php

use App\Services\DarCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('listForRange sends date range and status to the API', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [['id' => 1]]], 200),
    ]);

    $cache = new DarCache('http://api.test');

    $result = $cache->listForRange('all', null, '2026-07-13', '2026-07-13', [1, 2, 3, 4]);

    expect($result)->toBe(['data' => [['id' => 1]]]);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return ($data['start_date'] ?? null) === '2026-07-13'
            && ($data['end_date'] ?? null) === '2026-07-13'
            && ($data['status'] ?? null) === '1,2,3,4';
    });
});

test('listForRange omits team_user for the all scope', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $cache = new DarCache('http://api.test');
    $cache->listForRange('all', 7, '2026-07-01', '2026-07-31');

    Http::assertSent(fn ($request) => ! array_key_exists('team_user', $request->data()));
});

test('listForRange scopes to team_user for the user scope', function () {
    Http::fake(['*' => Http::response(['data' => []], 200)]);

    $cache = new DarCache('http://api.test');
    $cache->listForRange('user', 7, '2026-07-01', '2026-07-31');

    Http::assertSent(fn ($request) => (int) ($request->data()['team_user'] ?? 0) === 7);
});

test('listForRange caches successful responses within the same range', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => [['id' => 1]]], 200)]);

    $cache = new DarCache('http://api.test');

    $cache->listForRange('all', null, '2026-07-13', '2026-07-13');
    $cache->listForRange('all', null, '2026-07-13', '2026-07-13');

    Http::assertSentCount(1);
});

test('listForRange returns an empty data set when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $cache = new DarCache('http://api.test');

    expect($cache->listForRange('user', 7, '2026-07-13', '2026-07-13'))->toBe(['data' => []]);
});
