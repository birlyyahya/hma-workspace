<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\DarCommentReceived;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function notifyDarComment(User $user, int $activityId): void
{
    $user->notify(new DarCommentReceived(
        activityId: $activityId,
        activityTitle: 'Task #'.$activityId,
        commentId: $activityId * 10,
        commenterId: 999,
        commenterName: 'Someone',
        body: 'A comment',
    ));
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['data' => []])]);
});

test('opening a message deletes its notifications so the board does not pile up', function () {
    $user = User::factory()->create();

    notifyDarComment($user, 5);
    notifyDarComment($user, 5);
    notifyDarComment($user, 9);

    expect($user->notifications()->count())->toBe(3);

    Volt::actingAs($user)
        ->test('dar.widget.board-overview-dar')
        ->call('openMessage', 5);

    expect($user->fresh()->notifications()->count())->toBe(1);
    expect($user->notifications()->get()->every(fn ($n) => (int) $n->data['activity_id'] === 9))->toBeTrue();
});

test('read all deletes the current user\'s own messages', function () {
    $user = User::factory()->create();

    notifyDarComment($user, 5);
    notifyDarComment($user, 5);
    notifyDarComment($user, 9);

    expect($user->notifications()->count())->toBe(3);

    Volt::actingAs($user)
        ->test('dar.widget.board-overview-dar')
        ->call('markAllAsRead');

    expect($user->fresh()->notifications()->count())->toBe(0);
});

test('read all only deletes own messages even for an all-scope viewer', function () {
    $viewer = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    $other = User::factory()->create();

    notifyDarComment($viewer, 5);
    notifyDarComment($other, 5);
    notifyDarComment($other, 9);

    Volt::actingAs($viewer)
        ->test('dar.widget.board-overview-dar')
        ->call('markAllAsRead');

    expect($viewer->fresh()->notifications()->count())->toBe(0)
        ->and($other->fresh()->notifications()->count())->toBe(2);
});

test('opening a message redirects to the dar detail page', function () {
    $user = User::factory()->create();
    notifyDarComment($user, 7);

    Volt::actingAs($user)
        ->test('dar.widget.board-overview-dar')
        ->call('openMessage', 7)
        ->assertRedirect(route('dar.dar-show', ['id' => 7]));
});
