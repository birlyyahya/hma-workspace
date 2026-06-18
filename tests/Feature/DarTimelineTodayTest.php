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
