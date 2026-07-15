<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportAnnouncement;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use NotificationChannels\WebPush\WebPushChannel;

beforeEach(function () {
    Livewire::withoutLazyLoading();
});

function announcementAuthor(): User
{
    $role = Role::factory()->create();

    $permission = Permission::query()->firstOrCreate(
        ['name' => 'knowledge.create'],
        ['module' => 'knowledge', 'action' => 'create', 'label' => 'Create Knowledge'],
    );

    $role->permissions()->attach($permission);

    return User::factory()->create(['role_id' => $role->id]);
}

test('creating an announcement notifies every user except the author', function () {
    Notification::fake();

    $author = announcementAuthor();
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    Volt::actingAs($author)->test('knowledge.announcements')
        ->set('title', 'Libur Nasional')
        ->set('content', 'Kantor tutup pada tanggal merah.')
        ->set('priority', 'important')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertSentTo($userA, AnnouncementPublished::class);
    Notification::assertSentTo($userB, AnnouncementPublished::class);
    Notification::assertNotSentTo($author, AnnouncementPublished::class);
});

test('editing an existing announcement does not notify users', function () {
    Notification::fake();

    $author = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    User::factory()->create();

    $announcement = SupportAnnouncement::create([
        'title' => 'Judul Lama',
        'content' => 'Isi',
        'priority' => 'normal',
        'is_active' => true,
        'created_by' => $author->id,
    ]);

    Volt::actingAs($author)->test('knowledge.announcements')
        ->call('openEdit', $announcement->id)
        ->set('title', 'Judul Baru')
        ->call('save')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

test('the announcement web push carries the priority prefix and announcements url', function () {
    $notification = new AnnouncementPublished(
        announcementId: 5,
        title: 'Libur Nasional',
        priority: 'important',
        authorId: 1,
        authorName: 'Admin',
    );

    expect($notification->via(new stdClass))->toContain('database', WebPushChannel::class);

    $message = $notification->toWebPush(new stdClass, $notification)->toArray();

    expect($message['title'])->toContain('Pengumuman penting')
        ->and($message['title'])->toContain('Libur Nasional')
        ->and($message['data']['url'])->toBe(route('knowledge.announcements'));
});
