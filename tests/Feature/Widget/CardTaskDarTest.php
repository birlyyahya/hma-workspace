<?php

use App\Models\User;
use App\Services\DarCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function cardDarActivity(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->unique()->numberBetween(1, 100000),
        'user_id' => 1,
        'activity' => 'Untitled',
        'description' => null,
        'status' => 1,
        'project_id' => null,
        'team_user' => [],
        'comments' => [],
        'start_date' => now()->format('Y-m-d H:i:s'),
        'end_date' => now()->addHour()->format('Y-m-d H:i:s'),
    ], $overrides);
}

beforeEach(function () {
    app(DarCache::class)->flush();
});

test('fetchTasks loads tasks and visibleTasks returns them sorted by status', function () {
    Http::fake(['*global/dar/list*' => Http::response([
        'data' => [
            cardDarActivity(['activity' => 'Closed task', 'status' => 4]),
            cardDarActivity(['activity' => 'Open task', 'status' => 1]),
            cardDarActivity(['activity' => 'Pending task', 'status' => 2]),
        ],
    ])]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    expect($component->get('tasks'))->toHaveCount(3);

    $statuses = collect($component->instance()->visibleTasks())->pluck('status')->all();

    expect($statuses)->toBe([1, 2, 4]);
});

test('selecting a project filter sends project_id to the API', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar')
        ->set('projectFilter', '77');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ($request->data()['project_id'] ?? null) === '77');
});
