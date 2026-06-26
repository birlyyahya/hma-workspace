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

test('tasks are trimmed so the heavy comment files never reach the component state', function () {
    Http::fake(['*global/dar/list*' => Http::response([
        'data' => [
            cardDarActivity([
                'project_category_id' => 99,
                'comments' => [[
                    'id' => 1,
                    'user_id' => 5,
                    'body' => 'Done',
                    'created_at' => '2026-06-22 05:33:05',
                    'files' => [['id' => 1, 'url' => 'http://example.test/huge.jpeg', 'size' => 244985]],
                ]],
            ]),
        ],
    ])]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    $task = $component->get('tasks')[0];

    expect($task)->not->toHaveKey('project_category_id')
        ->and($task['comments'][0])->toBe([
            'user_id' => 5,
            'body' => 'Done',
            'created_at' => '2026-06-22 05:33:05',
        ]);
});

test('unchecking kegiatan project after picking a project saves a non-project activity', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => []]),
        '*timelines/search*' => Http::response(['data' => []]),
        '*global/dar/create*' => Http::response(['success' => true]),
    ]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar')
        ->set('form.isproject', true)
        ->set('projectSelected', '77')
        ->set('form.isproject', false)
        ->set('form.activity', 'Aktivitas non project')
        ->call('createActivity')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/create')
        && $request->data()['project_id'] === null
        && $request->data()['project_category_id'] === null);
});

test('selecting a project filter sends project_id to the API', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar')
        ->set('projectFilter', '77');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ($request->data()['project_id'] ?? null) === '77');
});
