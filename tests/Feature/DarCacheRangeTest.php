<?php

use App\Services\DarCache;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('listForRange sends the date range to the API', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [['id' => 1]]], 200),
    ]);

    $cache = new DarCache('http://api.test');

    $result = $cache->listForRange('all', null, '2026-07-13', '2026-07-13');

    expect($result)->toBe(['data' => [['id' => 1]]]);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return ($data['start_date'] ?? null) === '2026-07-13'
            && ($data['end_date'] ?? null) === '2026-07-13'
            && ! array_key_exists('status', $data);
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

test('listByStatus sends a single status and scopes to team_user', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => [['id' => 9]]], 200)]);

    $cache = new DarCache('http://api.test');

    expect($cache->listByStatus('user', 7, 1))->toBe(['data' => [['id' => 9]]]);

    Http::assertSent(fn ($request) => (int) ($request->data()['status'] ?? 0) === 1
        && (int) ($request->data()['team_user'] ?? 0) === 7);
});

test('listForRange caches successful responses within the same range', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => [['id' => 1]]], 200)]);

    $cache = new DarCache('http://api.test');

    $cache->listForRange('all', null, '2026-07-13', '2026-07-13');
    $cache->listForRange('all', null, '2026-07-13', '2026-07-13');

    Http::assertSentCount(1);
});

test('timelineToday merges tasks starting today with open tasks, deduped by id', function () {
    Http::fake(function ($request) {
        // Hit 2: open tasks (status=1), started earlier but still ongoing.
        if (array_key_exists('status', $request->data())) {
            return Http::response(['data' => [['id' => 2], ['id' => 3]]], 200);
        }

        // Hit 1: tasks starting today.
        return Http::response(['data' => [['id' => 1], ['id' => 2]]], 200);
    });

    $cache = new DarCache('http://api.test');

    $ids = collect($cache->timelineToday('all')['data'])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([1, 2, 3]);
});

test('timelineToday queries todays range and the open status', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []], 200)]);

    $cache = new DarCache('http://api.test');
    $cache->timelineToday('all');

    $today = now()->format('Y-m-d');

    Http::assertSent(fn ($request) => ($request->data()['start_date'] ?? null) === $today
        && ($request->data()['end_date'] ?? null) === $today);
    Http::assertSent(fn ($request) => (int) ($request->data()['status'] ?? 0) === 1);
});

test('board merges open tasks with the recent-start window, deduped by id', function () {
    Http::fake(function ($request) {
        if (array_key_exists('status', $request->data())) {
            return Http::response(['data' => [['id' => 1], ['id' => 2]]], 200);
        }

        return Http::response(['data' => [['id' => 2], ['id' => 3]]], 200);
    });

    $cache = new DarCache('http://api.test');

    $ids = collect($cache->board('all')['data'])->pluck('id')->sort()->values()->all();

    expect($ids)->toBe([1, 2, 3]);
});

test('board queries the open status and a 30-day start window', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []], 200)]);

    $cache = new DarCache('http://api.test');
    $cache->board('all');

    Http::assertSent(fn ($request) => (int) ($request->data()['status'] ?? 0) === 1);
    Http::assertSent(fn ($request) => ($request->data()['start_date'] ?? null) === now()->subDays(30)->format('Y-m-d')
        && ! array_key_exists('end_date', $request->data()));
});

test('listForRange returns an empty data set when the API connection fails', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $cache = new DarCache('http://api.test');

    expect($cache->listForRange('user', 7, '2026-07-13', '2026-07-13'))->toBe(['data' => []]);
});
