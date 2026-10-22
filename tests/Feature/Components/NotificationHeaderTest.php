<?php

use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Notifications\DarCommentReceived;
use App\Notifications\SpdApproved;
use Livewire\Volt\Volt;

function notifyDarComment(User $user, string $body = 'Mantap kerjanya'): void
{
    $user->notify(new DarCommentReceived(
        activityId: 7,
        activityTitle: 'Fix front end',
        commentId: 1,
        commenterId: 99,
        commenterName: 'Yahya',
        body: $body,
    ));
}

test('it shows an empty state without notifications', function () {
    Volt::actingAs(User::factory()->create())
        ->test('components.notification-header')
        ->assertSee('Tidak ada notifikasi baru')
        ->assertSet('tab', 'all')
        ->call('setTab', 'history')
        ->assertSee('Belum ada riwayat notifikasi');
});

test('it renders notification content from the database', function () {
    $user = User::factory()->create();
    notifyDarComment($user);

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->assertSee('Yahya')
        ->assertSee('Fix front end')
        ->assertSee('Mantap kerjanya')
        ->assertDontSee('Tidak ada notifikasi baru');
});

test('it only lists notifications of the current user', function () {
    $user = User::factory()->create();
    notifyDarComment(User::factory()->create(), 'Rahasia orang lain');

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->assertDontSee('Rahasia orang lain');
});

test('all tab only shows unread and history tab only shows read notifications', function () {
    $user = User::factory()->create();
    notifyDarComment($user, 'Sudah dibaca');
    $user->unreadNotifications->markAsRead();
    notifyDarComment($user, 'Belum dibaca');

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->assertSee('Belum dibaca')
        ->assertDontSee('Sudah dibaca')
        ->call('setTab', 'history')
        ->assertSee('Sudah dibaca')
        ->assertDontSee('Belum dibaca');
});

test('comment and announcement tabs filter unread notifications by kind', function () {
    $user = User::factory()->create();
    notifyDarComment($user, 'Isi komentar');
    $user->notify(new AnnouncementPublished(1, 'Libur Bersama', 'important', 5, 'Admin HR'));

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->call('setTab', 'comment')
        ->assertSee('Isi komentar')
        ->assertDontSee('Libur Bersama')
        ->call('setTab', 'announcement')
        ->assertSee('Libur Bersama')
        ->assertDontSee('Isi komentar');
});

test('read notifications are excluded from the comment tab', function () {
    $user = User::factory()->create();
    notifyDarComment($user, 'Komentar lama');
    $user->unreadNotifications->markAsRead();

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->call('setTab', 'comment')
        ->assertDontSee('Komentar lama');
});

test('an unknown tab falls back to all', function () {
    Volt::actingAs(User::factory()->create())
        ->test('components.notification-header')
        ->call('setTab', 'bogus')
        ->assertSet('tab', 'all');
});

test('opening a notification marks it as read and redirects to its target', function () {
    $user = User::factory()->create();
    $user->notify(new SpdApproved(12, 'Survey lokasi', 'Bandung'));
    $notification = $user->notifications()->first();

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->call('open', $notification->id)
        ->assertRedirect(route('izin.spd-preview', ['id' => 12]));

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot open another users notification', function () {
    $owner = User::factory()->create();
    notifyDarComment($owner);
    $notification = $owner->notifications()->first();

    Volt::actingAs(User::factory()->create())
        ->test('components.notification-header')
        ->call('open', $notification->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

test('mark all as read clears unread notifications', function () {
    $user = User::factory()->create();
    notifyDarComment($user);
    notifyDarComment($user);

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->assertSet('unreadCount', 2)
        ->call('markAllAsRead')
        ->assertSet('unreadCount', 0);

    expect($user->unreadNotifications()->count())->toBe(0);
});

test('marking all as read moves notifications from the all tab to history', function () {
    $user = User::factory()->create();
    notifyDarComment($user, 'Pindah ke riwayat');

    Volt::actingAs($user)
        ->test('components.notification-header')
        ->assertSee('Pindah ke riwayat')
        ->call('markAllAsRead')
        ->assertDontSee('Pindah ke riwayat')
        ->call('setTab', 'history')
        ->assertSee('Pindah ke riwayat');
});
