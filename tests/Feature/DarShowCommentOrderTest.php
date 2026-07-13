<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(fn () => Livewire::withoutLazyLoading());

function fakeDarTaskWithComments(int $ownerId, array $comments = []): void
{
    $task = [
        'id' => 1,
        'activity' => 'Sample Task',
        'user_id' => $ownerId,
        'team_user' => [],
        'comments' => $comments,
    ];

    Http::fake([
        '*log-activity*' => Http::response(['data' => []]),
        '*activity?id=*' => Http::response(['data' => $task]),
        '*' => Http::response(['status' => 200, 'success' => true, 'data' => []]),
    ]);
}

test('the newest comment is rendered above older comments', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithComments($owner->id, [
        ['id' => 1, 'user_id' => $owner->id, 'body' => 'Komentar lama', 'created_at' => now()->subDay()->toDateTimeString(), 'files' => []],
        ['id' => 2, 'user_id' => $owner->id, 'body' => 'Komentar terbaru', 'created_at' => now()->toDateTimeString(), 'files' => []],
    ]);

    $html = Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->html();

    $commentList = (string) str($html)->after('x-ref="commentList"');

    expect(strpos($commentList, 'Komentar terbaru'))->toBeLessThan(strpos($commentList, 'Komentar lama'));
});

test('the comment input is positioned above the comment list', function () {
    $owner = User::factory()->create();
    fakeDarTaskWithComments($owner->id, [
        ['id' => 1, 'user_id' => $owner->id, 'body' => 'Komentar pertama', 'created_at' => now()->toDateTimeString(), 'files' => []],
    ]);

    $html = Volt::actingAs($owner)
        ->test('dar.dar-show', ['id' => 1])
        ->html();

    expect(strpos($html, 'Tulis komentar...'))->toBeLessThan(strpos($html, 'x-ref="commentList"'))
        ->and(str($html)->after('x-ref="commentList"')->contains('Komentar pertama'))->toBeTrue();
});
