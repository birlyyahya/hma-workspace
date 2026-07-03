<?php

use App\Jobs\MoveProjectFilesJob;
use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

function runMoveJob(MoveProjectFilesJob $job): void
{
    $job->handle(app(ProjectFileStorage::class), app(ProjectWriter::class), app(ProjectCache::class));
}

/**
 * @return array{doc_id: int, from_key: string, to_key: string, title: string, category_id: ?int}
 */
function moveItem(): array
{
    return [
        'doc_id' => 1,
        'from_key' => 'projects/5/laporan.pdf',
        'to_key' => 'projects/5/Arsip/laporan.pdf',
        'title' => 'laporan',
        'category_id' => 7,
    ];
}

/**
 * @param  array<int, array<string, mixed>>  $docs
 */
function fakeBepmForMove(array $docs, int $registerStatus = 201, int $deleteStatus = 200): void
{
    Http::fake(function ($request) use ($docs, $registerStatus, $deleteStatus) {
        $url = $request->url();

        if (str_contains($url, 'admin-docs/search')) {
            return Http::response(['status' => 200, 'data' => $docs], 200);
        }

        if ($request->method() === 'POST' && str_contains($url, 'admin-docs')) {
            return Http::response(['status' => $registerStatus, 'data' => ['id' => 99]], 200);
        }

        if ($request->method() === 'DELETE') {
            return Http::response(['status' => $deleteStatus], $deleteStatus >= 400 ? $deleteStatus : 200);
        }

        return Http::response(['status' => 200, 'data' => []], 200);
    });
}

test('a move copies the object, registers the new key, deletes the old record then the old object', function () {
    fakeBepmForMove([
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
    ]);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('copyObject')->once()->with('projects/5/laporan.pdf', 'projects/5/Arsip/laporan.pdf');
    $storage->shouldReceive('deleteObject')->once()->with('projects/5/laporan.pdf');

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()]));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'admin-docs')
        && $request['filename'] === 'projects/5/Arsip/laporan.pdf');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/1'));
});

test('when BEPM registration fails the copy is removed and the old file stays intact', function () {
    fakeBepmForMove([
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
    ], registerStatus: 422);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('copyObject')->once();
    $storage->shouldReceive('deleteObject')->once()->with('projects/5/Arsip/laporan.pdf');

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()]));

    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

test('when deleting the old record fails the new record and copy are rolled back', function () {
    fakeBepmForMove([
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
    ], deleteStatus: 500);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('copyObject')->once();
    $storage->shouldReceive('deleteObject')->once()->with('projects/5/Arsip/laporan.pdf');

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()]));

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/1'));

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/99'));
});

test('a retried job skips items that were already moved', function () {
    fakeBepmForMove([
        ['id' => 99, 'title' => 'laporan', 'files' => ['url' => 'projects/5/Arsip/laporan.pdf']],
    ]);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldNotReceive('copyObject');
    $storage->shouldNotReceive('deleteObject');

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()]));

    Http::assertNotSent(fn ($request) => $request->method() === 'POST'
        && str_contains($request->url(), 'admin-docs')
        && ! str_contains($request->url(), 'search'));
});

test('the folder update is applied and the lock released when every file succeeds', function () {
    fakeBepmForMove([
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
    ]);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('copyObject')->once();
    $storage->shouldReceive('deleteObject')->once();

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Lama', 'status' => 'moving']);

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()], $folder->id, ['name' => 'Baru']));

    $folder->refresh();
    expect($folder->name)->toBe('Baru')
        ->and($folder->status)->toBeNull();
});

test('the folder update is NOT applied when a file fails, but the lock is released', function () {
    fakeBepmForMove([
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
    ], registerStatus: 422);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('copyObject')->once();
    $storage->shouldReceive('deleteObject')->once();

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Lama', 'status' => 'moving']);

    runMoveJob(new MoveProjectFilesJob(5, [moveItem()], $folder->id, ['name' => 'Baru']));

    $folder->refresh();
    expect($folder->name)->toBe('Lama')
        ->and($folder->status)->toBeNull();
});
