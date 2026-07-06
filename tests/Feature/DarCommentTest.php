<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\DarCommentReceived;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

function fakeDarApi(User $user): void
{
    Http::fake([
        // create-comment API returns success but no id in `data`.
        '*create-comment*' => Http::response(['success' => true, 'data' => []]),
        '*delete-comment*' => Http::response(['success' => true]),
        '*log-activity*' => Http::response(['data' => []]),
        // Authoritative activity fetch carries the real comment id.
        '*activity?id=*' => Http::response(['data' => [
            'id' => 1,
            'activity' => 'Task X',
            'user_id' => $user->id,
            'team_user' => [],
            'comments' => [
                [
                    'id' => 42,
                    'user_id' => $user->id,
                    'body' => 'Hello',
                    'created_at' => '2026-06-17 10:00:00',
                    'files' => [],
                ],
            ],
        ]]),
        '*' => Http::response(['data' => []]),
    ]);
}

test('a newly added comment carries a valid id from the reloaded task', function () {
    Notification::fake();

    $user = User::factory()->create();
    fakeDarApi($user);

    $component = Volt::actingAs($user)
        ->test('dar.dar-show', ['id' => 1])
        ->set('comment', 'Hello')
        ->call('addComment')
        ->assertHasNoErrors();

    $comments = $component->get('comments');

    expect($comments)->not->toBeEmpty();

    foreach ($comments as $comment) {
        expect($comment['id'] ?? null)->not->toBeNull();
    }
});

test('an all-scope super-admin receives the comment notification even when not on the team', function () {
    Notification::fake();

    $superAdmin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);

    $user = User::factory()->create();
    fakeDarApi($user);

    Volt::actingAs($user)
        ->test('dar.dar-show', ['id' => 1])
        ->set('comment', 'Hello')
        ->call('addComment')
        ->assertHasNoErrors();

    Notification::assertSentTo($superAdmin, DarCommentReceived::class);
    Notification::assertNotSentTo($user, DarCommentReceived::class);
});

test('a just-added comment can be deleted without a binding resolution error', function () {
    Notification::fake();

    $user = User::factory()->create();
    fakeDarApi($user);

    Volt::actingAs($user)
        ->test('dar.dar-show', ['id' => 1])
        ->set('comment', 'Hello')
        ->call('addComment')
        ->call('confirmDeleteComment', 42)
        ->assertSet('pendingDeleteCommentId', 42)
        ->call('deleteComment')
        ->assertHasNoErrors()
        ->assertSet('pendingDeleteCommentId', null);
});
