<?php

use App\Livewire\Settings\Notifications;
use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use NotificationChannels\WebPush\WebPushChannel;

test('guests are redirected from the notification settings page', function () {
    $this->get(route('notifications.edit'))->assertRedirect(route('login'));
});

test('the notification settings page renders with the registered device count', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/endpoint-1', 'key', 'token', 'aes128gcm');

    $this->actingAs($user)
        ->get(route('notifications.edit'))
        ->assertOk()
        ->assertSeeLivewire(Notifications::class)
        ->assertSee('1 perangkat');
});

test('a test push notification is sent to the user with a subscription', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->updatePushSubscription('https://push.example.com/endpoint-1', 'key', 'token', 'aes128gcm');

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->call('sendTestNotification')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, TestPushNotification::class, function ($notification, $channels) {
        return in_array(WebPushChannel::class, $channels, true);
    });
});

test('the device list shows the push service and unknown device label', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');
    $user->updatePushSubscription('https://web.push.apple.com/xyz', 'key', 'token', 'aes128gcm');

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->assertSee('Google (Chrome/Android)')
        ->assertSee('Apple (iOS/macOS)')
        ->assertSee('Perangkat tidak dikenal');
});

test('a device can be removed after confirming through the modal', function () {
    $user = User::factory()->create();
    $subscription = $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->call('confirmRemoveDevice', $subscription->id)
        ->assertSet('pendingDeleteDeviceId', $subscription->id)
        ->call('removeDevice')
        ->assertSet('pendingDeleteDeviceId', null)
        ->assertHasNoErrors();

    expect($user->pushSubscriptions()->count())->toBe(0);
});

test('removing without a pending confirmation does nothing', function () {
    $user = User::factory()->create();
    $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->call('removeDevice')
        ->assertHasNoErrors();

    expect($user->pushSubscriptions()->count())->toBe(1);
});

test('a user cannot remove another user\'s device', function () {
    $owner = User::factory()->create();
    $subscription = $owner->updatePushSubscription('https://fcm.googleapis.com/fcm/send/abc', 'key', 'token', 'aes128gcm');

    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test(Notifications::class)
        ->call('confirmRemoveDevice', $subscription->id)
        ->call('removeDevice');

    expect($owner->pushSubscriptions()->count())->toBe(1);
});

test('no test notification is sent when the user has no subscribed device', function () {
    Notification::fake();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Notifications::class)
        ->call('sendTestNotification')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});
