<?php

use App\Models\User;
use App\Services\DarCache;
use App\Services\ProjectCache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

beforeEach(function () {
    app(DarCache::class)->flush();
    app(ProjectCache::class)->flushProjects();
});

function darActivity(array $overrides = []): array
{
    return array_merge([
        'id' => fake()->unique()->numberBetween(1, 100000),
        'user_id' => 1,
        'activity' => 'Aktivitas',
        'status' => 1,
        'project_id' => 10,
        'start_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
        'end_date' => now()->subDays(3)->format('Y-m-d H:i:s'),
        'team_user' => [],
    ], $overrides);
}

test('aggregates dar activity per project and computes percentages', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [
            darActivity(['project_id' => 10]),
            darActivity(['project_id' => 10]),
            darActivity(['project_id' => 10]),
            darActivity(['project_id' => 20]),
        ]]),
        '*projects/search*' => Http::response(['data' => [
            ['id' => 10, 'name' => 'Gedung A', 'code' => 'GA-01'],
            ['id' => 20, 'name' => 'Gedung B', 'code' => 'GB-02'],
        ]]),
    ]);

    $stats = Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.project-activity')
        ->get('stats');

    expect($stats['total'])->toBe(4)
        ->and($stats['project_count'])->toBe(2)
        ->and($stats['top']['name'])->toBe('Gedung A')
        ->and($stats['top']['code'])->toBe('GA-01')
        ->and($stats['top']['count'])->toBe(3)
        ->and($stats['top']['pct'])->toBe(75.0)
        ->and($stats['slices'])->toHaveCount(2);
});

test('groups projects beyond the top limit into a Lainnya slice with its members listed', function () {
    $rows = [];
    $projects = [];

    // 9 projects with descending activity counts (9,8,...,1).
    foreach (range(1, 9) as $i) {
        $projectId = 100 + $i;
        $projects[] = ['id' => $projectId, 'name' => "Project {$i}"];

        foreach (range(1, 10 - $i) as $n) {
            $rows[] = darActivity(['project_id' => $projectId]);
        }
    }

    Http::fake([
        '*global/dar/list*' => Http::response(['data' => $rows]),
        '*projects/search*' => Http::response(['data' => $projects]),
    ]);

    $stats = Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.project-activity')
        ->get('stats');

    // 7 top slices + 1 "Lainnya" bucket.
    expect($stats['slices'])->toHaveCount(8)
        ->and($stats['project_count'])->toBe(9)
        ->and($stats['slices'][7]['name'])->toContain('Lainnya')
        ->and($stats['slices'][7]['is_other'])->toBeTrue()
        ->and($stats['slices'][7]['members'])->toHaveCount(2)
        ->and($stats['slices'][7]['members'][0]['name'])->toBe('Project 8')
        ->and($stats['slices'][7]['members'][1]['name'])->toBe('Project 9');

    // Percentages sum to ~100.
    expect(round(collect($stats['slices'])->sum('pct')))->toBe(100.0);
});

test('excludes non-project activities entirely from the chart', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [
            darActivity(['project_id' => null]),
            darActivity(['project_id' => null]),
            darActivity(['project_id' => 10]),
        ]]),
        '*projects/search*' => Http::response(['data' => [
            ['id' => 10, 'name' => 'Gedung A'],
        ]]),
    ]);

    $stats = Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.project-activity')
        ->get('stats');

    expect($stats['total'])->toBe(1)
        ->and($stats['slices'])->toHaveCount(1)
        ->and($stats['slices'][0]['name'])->toBe('Gedung A');
});

test('renders an empty state when there is no project-linked activity', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => [
            darActivity(['project_id' => null]),
        ]]),
        '*projects/search*' => Http::response(['data' => []]),
    ]);

    Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.project-activity')
        ->assertSet('stats.total', 0)
        ->assertSee('Belum ada aktivitas');
});

test('renders an empty state when there is no activity', function () {
    Http::fake([
        '*global/dar/list*' => Http::response(['data' => []]),
        '*projects/search*' => Http::response(['data' => []]),
    ]);

    Volt::actingAs(User::factory()->create())
        ->test('widget.dashboard.project-activity')
        ->assertSet('stats.total', 0)
        ->assertSee('Belum ada aktivitas');
});
