<?php

use App\Models\User;
use App\Services\DarCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function boardActivity(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->unique()->numberBetween(1, 100000),
        'user_id' => 1,
        'activity' => 'Task',
        'description' => null,
        'status' => 1,
        'project_id' => null,
        'date' => now()->format('Y-m-d H:i:s'),
        'start_date' => now()->format('Y-m-d H:i:s'),
        'end_date' => now()->addHour()->format('Y-m-d H:i:s'),
    ], $overrides);
}

beforeEach(function () {
    app(DarCache::class)->flush();
});

test('todos include open and done-today, excluding pending, cancelled and past-closed', function () {
    $open = boardActivity(['activity' => 'Open task', 'status' => 1, 'end_date' => now()->addDay()->format('Y-m-d H:i:s')]);
    $doneToday = boardActivity(['activity' => 'Done today', 'status' => 4, 'end_date' => now()->format('Y-m-d H:i:s')]);
    $closedPast = boardActivity(['activity' => 'Closed last week', 'status' => 4, 'end_date' => now()->subWeek()->format('Y-m-d H:i:s')]);
    $pending = boardActivity(['activity' => 'Pending task', 'status' => 2]);
    $cancelled = boardActivity(['activity' => 'Cancelled task', 'status' => 3]);

    Http::fake(['*' => Http::response(['data' => [$open, $doneToday, $closedPast, $pending, $cancelled]])]);

    $component = Volt::actingAs(User::factory()->create())->test('dar.widget.board-overview-dar');

    $todos = collect($component->get('todos')['project'])
        ->merge($component->get('todos')['nonproject'])
        ->pluck('activity')
        ->all();

    expect($todos)->toContain('Open task')
        ->toContain('Done today')
        ->not->toContain('Closed last week')
        ->not->toContain('Pending task')
        ->not->toContain('Cancelled task');
});
