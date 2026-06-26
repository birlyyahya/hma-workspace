<?php

use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;

function addUnreadNotification(User $user): void
{
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => TaskAssignedNotification::class,
        'data' => ['assigned_by' => 'Tester', 'message' => 'New task assigned'],
        'read_at' => null,
    ]);
}

test('checkNotification dispatches the sound event when there are unread notifications', function () {
    $user = User::factory()->create();
    addUnreadNotification($user);

    Volt::actingAs($user)
        ->test('widget.dashboard.task')
        ->call('checkNotification')
        ->assertDispatched('play-notification-sound');
});

test('checkNotification stays silent when there are no unread notifications', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)
        ->test('widget.dashboard.task')
        ->call('checkNotification')
        ->assertNotDispatched('play-notification-sound');
});
