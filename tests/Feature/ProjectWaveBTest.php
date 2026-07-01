<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

/**
 * project-create / project-edit / project-show are covered end-to-end by the
 * existing tests in tests/Feature/Project/* and ProjectShowAuthorizationTest —
 * the ProjectWriter migration keeps the outgoing BEPM requests identical, so
 * those suites validate the wiring. These tests cover project-cards delete,
 * which has no existing coverage.
 */
test('project-cards deleteProject sends the DELETE through ProjectWriter on success', function () {
    Http::fake([
        '*projects/search*' => Http::response(['data' => [], 'pagination' => []], 200),
        '*companies*' => Http::response(['data' => []], 200),
        '*projects/2' => Http::response([], 200),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.project-cards')
        ->set('projects', [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']])
        ->set('pendingDeleteId', 2)
        ->set('pendingDeleteName', 'B')
        ->call('deleteProject');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/projects/2'));
});

test('project-cards deleteProject keeps the list when the API fails', function () {
    Http::fake([
        '*projects/search*' => Http::response(['data' => [], 'pagination' => []], 200),
        '*companies*' => Http::response(['data' => []], 200),
        '*projects/2' => Http::response(['message' => 'server error'], 500),
    ]);
    $this->actingAs(User::factory()->create());

    Volt::test('project.project-cards')
        ->set('projects', [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']])
        ->set('pendingDeleteId', 2)
        ->call('deleteProject')
        ->assertSet('projects', [['id' => 1, 'name' => 'A'], ['id' => 2, 'name' => 'B']]);
});
