<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeSidebarProjects(array $leader = [], array $teams = [], array $catalog = []): void
{
    Http::fake([
        '*project-teams/search*' => Http::response([
            'status' => 200,
            'data' => $teams,
        ], 200),
        '*projects/search?project_leader_id*' => Http::response([
            'status' => 200,
            'data' => $leader,
        ], 200),
        '*projects/search*' => Http::response([
            'status' => 200,
            'data' => $catalog,
        ], 200),
    ]);
}

test('sidebar lists projects the user leads', function () {
    fakeSidebarProjects(
        leader: [['id' => 10, 'code' => 'PRJ-10', 'name' => 'Project Lead']],
    );

    $this->actingAs(User::factory()->create());

    Volt::test('components.sidebar-item')
        ->assertSee('PRJ-10')
        ->assertSee('Project Lead');
});

test('sidebar also lists projects the user is a team member of, enriched with code', function () {
    fakeSidebarProjects(
        leader: [['id' => 10, 'code' => 'PRJ-10', 'name' => 'Project Lead']],
        teams: [['id' => 23, 'project_id' => 25, 'project_name' => 'Project Member', 'user_id' => 3]],
        catalog: [['id' => 25, 'code' => 'PRJ-25', 'name' => 'Project Member']],
    );

    $this->actingAs(User::factory()->create());

    Volt::test('components.sidebar-item')
        ->assertSee('PRJ-10')
        ->assertSee('PRJ-25')
        ->assertSee('Project Member');
});

test('flushUser clears the involved-projects cache for that user', function () {
    $teamCalls = 0;

    Http::fake(function ($request) use (&$teamCalls) {
        if (str_contains($request->url(), 'project-teams/search')) {
            $teamCalls++;

            return Http::response([
                'status' => 200,
                'data' => $teamCalls === 1
                    ? [['id' => 1, 'project_id' => 25, 'project_name' => 'Project Member', 'user_id' => 3]]
                    : [],
            ], 200);
        }

        if (str_contains($request->url(), 'project_leader_id')) {
            return Http::response(['status' => 200, 'data' => []], 200);
        }

        return Http::response([
            'status' => 200,
            'data' => [['id' => 25, 'code' => 'PRJ-25', 'name' => 'Project Member']],
        ], 200);
    });

    $cache = app(App\Services\ProjectCache::class);

    expect($cache->involvedProjects(3))->toHaveCount(1);

    expect($cache->involvedProjects(3))->toHaveCount(1);
    expect($teamCalls)->toBe(1);

    $cache->flushUser(3);

    expect($cache->involvedProjects(3))->toHaveCount(0);
    expect($teamCalls)->toBe(2);
});

test('sidebar does not duplicate a project where the user is both leader and team member', function () {
    fakeSidebarProjects(
        leader: [['id' => 25, 'code' => 'PRJ-25', 'name' => 'Shared Project']],
        teams: [['id' => 23, 'project_id' => 25, 'project_name' => 'Shared Project', 'user_id' => 3]],
        catalog: [['id' => 25, 'code' => 'PRJ-25', 'name' => 'Shared Project']],
    );

    $this->actingAs(User::factory()->create());

    $html = Volt::test('components.sidebar-item')->html();

    expect(substr_count($html, 'sidebar-project-25'))->toBe(1);
});
