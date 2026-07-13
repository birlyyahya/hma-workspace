<?php

use App\Models\User;
use App\Notifications\DarActivityClosed;
use App\Notifications\DarActivityCreated;
use App\Services\DarNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

test('team members are notified when a dar activity is created, except the actor', function () {
    Notification::fake();

    $actor = User::factory()->create();
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();

    app(DarNotifier::class)->activityCreated([
        'id' => 7,
        'activity' => 'Task Baru',
        'user_id' => $actor->id,
        'team_user' => [$memberA->id, $memberB->id, $actor->id],
    ], $actor->id);

    Notification::assertSentTo($memberA, DarActivityCreated::class);
    Notification::assertSentTo($memberB, DarActivityCreated::class);
    Notification::assertNotSentTo($actor, DarActivityCreated::class);
});

test('the owner and team are notified when a dar activity is closed by someone else', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $actor = User::factory()->create();

    app(DarNotifier::class)->activityClosed([
        'id' => 7,
        'activity' => 'Task X',
        'user_id' => $owner->id,
        'team_user' => [['user_id' => $member->id], ['user_id' => $actor->id]],
    ], $actor->id);

    Notification::assertSentTo($owner, DarActivityClosed::class);
    Notification::assertSentTo($member, DarActivityClosed::class);
    Notification::assertNotSentTo($actor, DarActivityClosed::class);
});

test('nothing is sent when the actor is the only person involved', function () {
    Notification::fake();

    $actor = User::factory()->create();

    app(DarNotifier::class)->activityCreated([
        'id' => 7,
        'activity' => 'Solo Task',
        'user_id' => $actor->id,
        'team_user' => [],
    ], $actor->id);

    Notification::assertNothingSent();
});

test('dar activity notifications use database and web push channels with the task url', function () {
    $notification = new DarActivityCreated(
        activityId: 7,
        activityTitle: 'Task X',
        actorId: 1,
        actorName: 'Budi',
    );

    expect($notification->via(new stdClass))->toContain('database', WebPushChannel::class);

    $message = $notification->toWebPush(new stdClass, $notification)->toArray();

    expect($message['title'])->toBe('DAR baru: Task X')
        ->and($message['data']['url'])->toBe(route('dar.dar-show', ['id' => 7]));
});

test('marking a task as done from dar-show notifies the team', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $actor = User::factory()->create();

    Http::fake([
        '*update-status*' => Http::response(['success' => true]),
        '*log-activity*' => Http::response(['data' => []]),
        '*activity?id=*' => Http::response(['data' => [
            'id' => 1,
            'activity' => 'Task X',
            'status' => 1,
            'user_id' => $owner->id,
            'team_user' => [['user_id' => $member->id], ['user_id' => $actor->id]],
            'comments' => [],
        ]]),
        '*' => Http::response(['success' => true, 'data' => []]),
    ]);

    Livewire::withoutLazyLoading();

    Volt::actingAs($actor)
        ->test('dar.dar-show', ['id' => 1])
        ->assertSet('forbidden', false)
        ->call('markAsDone')
        ->assertHasNoErrors();

    Notification::assertSentTo($owner, DarActivityClosed::class);
    Notification::assertSentTo($member, DarActivityClosed::class);
    Notification::assertNotSentTo($actor, DarActivityClosed::class);
});

test('marking an already closed task as done does not re-notify', function () {
    Notification::fake();

    $owner = User::factory()->create();
    $actor = User::factory()->create();

    Http::fake([
        '*update-status*' => Http::response(['success' => true]),
        '*log-activity*' => Http::response(['data' => []]),
        '*activity?id=*' => Http::response(['data' => [
            'id' => 1,
            'activity' => 'Task X',
            'status' => 4,
            'user_id' => $owner->id,
            'team_user' => [],
            'comments' => [],
        ]]),
        '*' => Http::response(['success' => true, 'data' => []]),
    ]);

    Livewire::withoutLazyLoading();

    Volt::actingAs($actor)
        ->test('dar.dar-show', ['id' => 1])
        ->call('markAsDone')
        ->assertHasNoErrors();

    Notification::assertNotSentTo($owner, DarActivityClosed::class);
});
