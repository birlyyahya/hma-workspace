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
    $job->handle(app(ProjectFileStorage::class), app(ProjectCache::class), app(ProjectWriter::class));
}

/**
 * @param  array<int, array<string, mixed>>  $docs
 */
function fakeBepmForMoveJob(array $docs = []): void
{
    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => $docs], 200),
        '*admin-docs/*' => Http::response(['status' => 200], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
}

test('it lists objects under the old prefix, moves each, and syncs BEPM paths', function () {
    fakeBepmForMoveJob([
        ['id' => 8, 'files' => ['url' => 'projects_docs%2F2026%2F5%2FKontrak%203%2Fa.pdf']],
    ]);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak 3', 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->with('projects_docs/2026/5/Kontrak 3/')
        ->andReturn(['projects_docs/2026/5/Kontrak 3/a.pdf', 'projects_docs/2026/5/Kontrak 3/sub/b.pdf']);
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/Kontrak 3/a.pdf', 'projects_docs/2026/5/Kontrak 2/a.pdf');
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/Kontrak 3/sub/b.pdf', 'projects_docs/2026/5/Kontrak 2/sub/b.pdf');

    runMoveJob(new MoveProjectFilesJob(5, 'projects_docs/2026/5/Kontrak 3/', 'projects_docs/2026/5/Kontrak 2/', $folder->id, ['name' => 'Kontrak 2']));

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/8')
        && $request['file'] === 'projects_docs/2026/5/Kontrak 2/a.pdf');

    $folder->refresh();
    expect($folder->name)->toBe('Kontrak 2')
        ->and($folder->status)->toBeNull();
});

test('a failed move is logged and does not stop the rest; folder is finalized', function () {
    fakeBepmForMoveJob();
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->andReturn(['projects_docs/2026/5/A/a.pdf', 'projects_docs/2026/5/A/b.pdf']);
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/A/a.pdf', 'projects_docs/2026/5/B/a.pdf')
        ->andThrow(new RuntimeException('MinIO down'));
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/A/b.pdf', 'projects_docs/2026/5/B/b.pdf');

    runMoveJob(new MoveProjectFilesJob(5, 'projects_docs/2026/5/A/', 'projects_docs/2026/5/B/', $folder->id));

    expect($folder->refresh()->status)->toBeNull();
});

test('an empty listing still applies the folder update and releases the lock', function () {
    fakeBepmForMoveJob();
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'X', 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->andReturn([]);
    $storage->shouldNotReceive('move');

    runMoveJob(new MoveProjectFilesJob(5, 'projects_docs/2026/5/X/', 'projects_docs/2026/5/Y/', $folder->id, ['name' => 'Y']));

    $folder->refresh();
    expect($folder->name)->toBe('Y')
        ->and($folder->status)->toBeNull();
});
