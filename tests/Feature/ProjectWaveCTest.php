<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * project-team-tabs is covered end-to-end by ProjectTeamAuthorizationTest
 * (renders with auth + Http::fake, asserting identical BEPM requests). This
 * covers project-timeline-tabs mount, which reads timelines via ProjectCache
 * and DAR activities via DarCache.
 */
test('timeline-tabs mount loads timelines and DAR activities through the services', function () {
    Http::fake([
        '*timelines/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-01'],
        ]], 200),
        '*global/dar/list*' => Http::response(['success' => true, 'data' => [
            ['id' => 9, 'activity' => 'Aktivitas A', 'description' => 'Deskripsi', 'status' => 1, 'project_category_id' => 1, 'start_date' => '2026-01-05', 'end_date' => '2026-01-20'],
        ]], 200),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-timeline-tabs', ['id' => 69, 'user_id' => 1])
        ->assertSet('timelines', [
            ['id' => 1, 'title' => 'Fase 1', 'start_date' => '2026-01-01', 'end_date' => '2026-03-01'],
        ])
        ->assertSet('activities', [
            ['id' => 9, 'activity' => 'Aktivitas A', 'description' => 'Deskripsi', 'status' => 1, 'project_category_id' => 1, 'start_date' => '2026-01-05', 'end_date' => '2026-01-20'],
        ])
        ->assertSee('Aktivitas A')
        ->assertStatus(200);
});

test('timeline-tabs mount degrades gracefully when the APIs are down', function () {
    Http::fake([
        '*timelines/search*' => Http::response([], 500),
        '*global/dar/list*' => Http::response([], 500),
    ]);

    $this->actingAs(User::factory()->create());

    Volt::test('project.components.project-timeline-tabs', ['id' => 70, 'user_id' => 1])
        ->assertSet('timelines', [])
        ->assertSet('activities', [])
        ->assertStatus(200);
});
