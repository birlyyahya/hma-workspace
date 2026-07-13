<?php

use App\Models\User;
use App\Services\DarCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    app(DarCache::class)->flush();
});

function calendarActivity(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->unique()->numberBetween(1, 100000),
        'user_id' => 1,
        'activity' => 'Rapat',
        'description' => null,
        'status' => 1,
        'project_id' => null,
        'start_date' => now()->startOfMonth()->addDays(4)->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->startOfMonth()->addDays(4)->setTime(11, 0)->format('Y-m-d H:i:s'),
        'team_user' => [],
    ], $overrides);
}

test('loads events for the visible month keyed by start date', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => [calendarActivity(['activity' => 'Rapat awal bulan'])]])]);

    $component = Volt::actingAs(User::factory()->create())->test('widget.dashboard.event-calendar');

    $map = $component->get('eventMap');
    $key = now()->startOfMonth()->addDays(4)->toDateString();

    expect($map)->toHaveKey($key)
        ->and($map[$key][0]['title'])->toBe('Rapat awal bulan');
});

test('fetches the visible month range from the API, scope all (no team_user)', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())->test('widget.dashboard.event-calendar');

    Http::assertSent(fn ($request) => ($request->data()['start_date'] ?? null) === now()->startOfMonth()->toDateString()
        && ($request->data()['end_date'] ?? null) === now()->endOfMonth()->toDateString()
        && ! array_key_exists('team_user', $request->data()));
});

test('navigating to the next month refetches that month range', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.event-calendar')
        ->call('nextMonth');

    $next = now()->startOfMonth()->addMonth();

    Http::assertSent(fn ($request) => ($request->data()['start_date'] ?? null) === $next->copy()->startOfMonth()->toDateString()
        && ($request->data()['end_date'] ?? null) === $next->copy()->endOfMonth()->toDateString());
});
