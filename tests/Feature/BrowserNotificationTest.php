<?php

use App\Models\User;
use App\Notifications\DarCommentReceived;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

test('the subscribe action stores a push subscription for the logged-in user', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('components.browser-notification')
        ->call('subscribe', [
            'endpoint' => 'https://push.example.com/endpoint-1',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
        ])
        ->assertHasNoErrors();

    expect($user->pushSubscriptions()->count())->toBe(1)
        ->and($user->pushSubscriptions()->first()->endpoint)->toBe('https://push.example.com/endpoint-1');
});

test('subscribing twice with the same endpoint does not duplicate the subscription', function () {
    $user = User::factory()->create();

    $subscription = [
        'endpoint' => 'https://push.example.com/endpoint-1',
        'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
    ];

    $component = Volt::actingAs($user)->test('components.browser-notification');

    $component->call('subscribe', $subscription);
    $component->call('subscribe', $subscription);

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('a payload without an endpoint is ignored', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('components.browser-notification')
        ->call('subscribe', ['keys' => ['p256dh' => 'x', 'auth' => 'y']])
        ->assertHasNoErrors();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('dar comment notification is delivered via database and web push channels', function () {
    $notification = new DarCommentReceived(
        activityId: 1,
        activityTitle: 'Task X',
        commentId: 42,
        commenterId: 99,
        commenterName: 'Budi',
        body: 'Halo tim',
    );

    expect($notification->via(new stdClass))->toContain('database', WebPushChannel::class);
});

test('the web push message carries the comment details and task url', function () {
    $notification = new DarCommentReceived(
        activityId: 7,
        activityTitle: 'Task X',
        commentId: 42,
        commenterId: 99,
        commenterName: 'Budi',
        body: 'Halo tim',
    );

    $message = $notification->toWebPush(new stdClass, $notification)->toArray();

    expect($message['title'])->toBe('Komentar baru: Task X')
        ->and($message['body'])->toBe('Budi: Halo tim')
        ->and($message['data']['url'])->toBe(route('dar.dar-show', ['id' => 7]));
});
