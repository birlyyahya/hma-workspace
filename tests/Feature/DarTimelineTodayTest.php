<?php

use App\Models\User;
use App\Services\DarCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function darActivity(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->unique()->numberBetween(1, 100000),
        'user_id' => 1,
        'activity' => 'Untitled',
        'description' => null,
        'status' => 1,
        'project_id' => null,
        'start_date' => now()->format('Y-m-d H:i:s'),
        'end_date' => now()->addHour()->format('Y-m-d H:i:s'),
    ], $overrides);
}

beforeEach(function () {
    app(DarCache::class)->flush();
});

test('timeline today only shows activities dated today', function () {
    $today = darActivity([
        'activity' => 'Today task',
        'status' => 1,
        'start_date' => now()->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->setTime(11, 0)->format('Y-m-d H:i:s'),
    ]);

    $inProgressYesterday = darActivity([
        'activity' => 'Yesterday in progress',
        'status' => 2,
        'start_date' => now()->subDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->subDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
    ]);

    $inProgressTomorrow = darActivity([
        'activity' => 'Tomorrow in progress',
        'status' => 3,
        'start_date' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->addDay()->setTime(11, 0)->format('Y-m-d H:i:s'),
    ]);

    Http::fake(['*' => Http::response([
        'data' => [$today, $inProgressYesterday, $inProgressTomorrow],
    ])]);

    $user = User::factory()->create();

    $component = Volt::actingAs($user)->test('dar.widget.timeline-today-dar');

    $events = $component->get('events');

    expect($events)->toHaveCount(1)
        ->and($events[0]['title'])->toBe('Today task');
});

test('a multi-day open task that started before today still shows via the open-status hit', function () {
    $spanning = darActivity([
        'activity' => 'Month-long open task',
        'status' => 1,
        'start_date' => now()->subDays(10)->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->addDays(20)->setTime(17, 0)->format('Y-m-d H:i:s'),
    ]);

    Http::fake(function ($request) use ($spanning) {
        // Hit 2 (status=1) returns the ongoing task; Hit 1 (start today) is empty.
        if (array_key_exists('status', $request->data())) {
            return Http::response(['data' => [$spanning]]);
        }

        return Http::response(['data' => []]);
    });

    $user = User::factory()->create();

    $events = Volt::actingAs($user)->test('dar.widget.timeline-today-dar')->get('events');

    expect($events)->toHaveCount(1)
        ->and($events[0]['title'])->toBe('Month-long open task');
});

test('an open task whose end_date already passed is surfaced as overdue, not on the grid', function () {
    $overdue = darActivity([
        'activity' => 'Overdue open task',
        'status' => 1,
        'start_date' => now()->subDays(6)->setTime(9, 0)->format('Y-m-d H:i:s'),
        'end_date' => now()->subDays(3)->setTime(17, 0)->format('Y-m-d H:i:s'),
    ]);

    Http::fake(['*' => Http::response(['data' => [$overdue]])]);

    $component = Volt::actingAs(User::factory()->create())->test('dar.widget.timeline-today-dar');

    expect($component->get('events'))->toHaveCount(0);

    $overdueList = $component->get('overdue');

    expect($overdueList)->toHaveCount(1)
        ->and($overdueList[0]['title'])->toBe('Overdue open task')
        ->and($overdueList[0]['days_late'])->toBe(3);
});
