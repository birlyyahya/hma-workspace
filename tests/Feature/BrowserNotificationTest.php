<?php

use App\Models\User;
use App\Notifications\DarCommentReceived;
use Livewire\Volt\Volt;

function sendDarCommentNotification(User $user): void
{
    $user->notify(new DarCommentReceived(
        activityId: 1,
        activityTitle: 'Task X',
        commentId: 42,
        commenterId: 99,
        commenterName: 'Budi',
        body: 'Halo tim',
    ));
}

test('it dispatches a browser push event for new unread dar comment notifications', function () {
    $user = User::factory()->create();

    $component = Volt::actingAs($user)->test('components.browser-notification');

    $this->travel(1)->minute();
    sendDarCommentNotification($user);

    $component->call('checkNewNotifications')
        ->assertDispatched('browser-push-notifications')
        ->assertDispatched('play-notification-sound');
});

test('it does not dispatch when there are no new notifications', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('components.browser-notification')
        ->call('checkNewNotifications')
        ->assertNotDispatched('browser-push-notifications')
        ->assertNotDispatched('play-notification-sound');
});

test('it does not re-dispatch the same notification on the next poll', function () {
    $user = User::factory()->create();

    $component = Volt::actingAs($user)->test('components.browser-notification');

    $this->travel(1)->minute();
    sendDarCommentNotification($user);

    $component->call('checkNewNotifications')
        ->assertDispatched('browser-push-notifications');

    $this->travel(1)->minute();

    $component->call('checkNewNotifications')
        ->assertNotDispatched('browser-push-notifications');
});

test('notifications created before mount are not pushed to the browser', function () {
    $user = User::factory()->create();

    sendDarCommentNotification($user);

    $this->travel(1)->minute();

    Volt::actingAs($user)
        ->test('components.browser-notification')
        ->call('checkNewNotifications')
        ->assertNotDispatched('browser-push-notifications');
});
