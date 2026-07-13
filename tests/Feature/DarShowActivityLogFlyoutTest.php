<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

function fakeDarTaskWithLogs(int $ownerId, array $logs = [], array $comments = []): void
{
    $task = [
        'id' => 1,
        'activity' => 'Sample Task',
        'user_id' => $ownerId,
        'team_user' => [],
        'comments' => $comments,
    ];

    Http::fake([
        '*log-activity*' => Http::response(['data' => $logs]),
        '*activity?id=*' => Http::response(['data' => $task]),
        '*' => Http::response(['status' => 200, 'success' => true, 'data' => []]),
    ]);
}

test('the comments section shows a riwayat perubahan trigger with the log count', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithLogs($owner->id, logs: [
        ['id' => 1, 'action' => 'created', 'created_at' => now()->subDay()->toDateTimeString(), 'changes' => []],
        ['id' => 2, 'action' => 'updated', 'created_at' => now()->toDateTimeString(), 'changes' => []],
    ]);

    Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->call('loadLogs')
        ->assertSee('Riwayat Perubahan')
        ->assertSeeHtml('activity-log-flyout');
});

test('activity logs render inside the flyout with old and new values', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithLogs($owner->id, logs: [
        [
            'id' => 10,
            'action' => 'updated',
            'created_at' => now()->toDateTimeString(),
            'changes' => [
                'status' => ['old' => 1, 'new' => 4],
                'activity' => ['old' => 'Judul lama', 'new' => 'Judul baru'],
            ],
        ],
        ['id' => 11, 'action' => 'created', 'created_at' => now()->subDay()->toDateTimeString(), 'changes' => []],
    ]);

    Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->call('loadLogs')
        ->assertSet('logs', fn ($logs) => count($logs) === 2)
        ->assertSee('Data diubah')
        ->assertSee('Task dibuat')
        ->assertSee('OPEN')
        ->assertSee('CLOSED')
        ->assertSee('Judul lama')
        ->assertSee('Judul baru');
});

test('logs are no longer rendered between comments in the comment list', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithLogs($owner->id, logs: [
        ['id' => 20, 'action' => 'updated', 'created_at' => now()->toDateTimeString(), 'changes' => []],
    ], comments: [
        ['id' => 5, 'user_id' => $owner->id, 'body' => 'Komentar pertama', 'created_at' => now()->toDateTimeString(), 'files' => []],
    ]);

    $html = Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->call('loadLogs')
        ->assertSee('Komentar pertama')
        ->html();

    $commentList = str($html)->after('x-ref="commentList"')->before('Riwayat Perubahan');

    expect($commentList->contains('Data diubah'))->toBeFalse();
});

test('the flyout shows an empty state when there are no logs', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithLogs($owner->id);

    Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->call('loadLogs')
        ->assertSee('Belum ada riwayat perubahan');
});
