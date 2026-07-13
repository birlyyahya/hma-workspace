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

test('fetchTasks loads tasks and visibleTasks preserves the API order', function () {
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

    expect($statuses)->toBe([4, 1, 2]);
});

test('fetchTasks requests the first page with perPage 21', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ($request->data()['perPage'] ?? null) === 21
        && ($request->data()['page'] ?? null) === 1);
});

test('hasMore reflects the paginator meta from the API', function () {
    Http::fake(['*global/dar/list*' => Http::response([
        'data' => [cardDarActivity()],
        'current_page' => 1, 'last_page' => 3,
    ])]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    expect($component->get('hasMore'))->toBeTrue();
});

test('loadMore appends the next page and stops when the last page is reached', function () {
    Http::fakeSequence('*global/dar/list*')
        ->push([
            'data' => [cardDarActivity(['id' => 1]), cardDarActivity(['id' => 2])],
            'current_page' => 1, 'last_page' => 2,
        ])
        ->push([
            'data' => [cardDarActivity(['id' => 3]), cardDarActivity(['id' => 4])],
            'current_page' => 2, 'last_page' => 2,
        ]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    expect($component->get('tasks'))->toHaveCount(2)
        ->and($component->get('hasMore'))->toBeTrue();

    $component->call('loadMore');

    expect($component->get('tasks'))->toHaveCount(4)
        ->and($component->get('page'))->toBe(2)
        ->and($component->get('hasMore'))->toBeFalse();
});

test('loadMore is a no-op once there are no more pages', function () {
    Http::fake(['*global/dar/list*' => Http::response([
        'data' => [cardDarActivity(['id' => 1])],
        'current_page' => 1, 'last_page' => 1,
    ])]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    expect($component->get('hasMore'))->toBeFalse();

    Http::fake(); // fail loudly if another request goes out

    $component->call('loadMore');

    expect($component->get('page'))->toBe(1)
        ->and($component->get('tasks'))->toHaveCount(1);
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

test('setStatus filters by status server-side and resets to the first page', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar')
        ->call('loadMore') // bump the page so we can prove setStatus resets it
        ->call('setStatus', '1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ($request->data()['status'] ?? null) === '1'
        && ($request->data()['page'] ?? null) === 1);
});

test('the all status filter omits the status param and toggling updates the state', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    $component = Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar');

    // Default state is "all" → the mount request must not carry a status.
    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ! array_key_exists('status', $request->data()));

    $component->call('setStatus', '3');
    expect($component->get('statusFilter'))->toBe('3');

    $component->call('setStatus', 'all');
    expect($component->get('statusFilter'))->toBe('all');
});

test('fetchTasks forwards the current search term to the API', function () {
    Http::fake(['*global/dar/list*' => Http::response(['data' => []])]);

    Volt::actingAs(User::factory()->create())
        ->test('dar.widget.card-task-dar')
        ->set('search', 'laporan')
        ->call('fetchTasks');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'global/dar/list')
        && ($request->data()['search'] ?? null) === 'laporan');
});
