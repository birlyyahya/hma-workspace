<?php

use App\Jobs\DeleteProjectFilesJob;
use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

function runDeleteJob(DeleteProjectFilesJob $job): void
{
    $job->handle(app(ProjectFileStorage::class), app(ProjectWriter::class), app(ProjectCache::class));
}

function fakeBepmForDeleteJob(int $deleteStatus = 200): void
{
    Http::fake(function ($request) use ($deleteStatus) {
        if ($request->method() === 'DELETE') {
            return Http::response(['status' => $deleteStatus], $deleteStatus >= 400 ? $deleteStatus : 200);
        }

        return Http::response(['status' => 200, 'data' => []], 200);
    });
}

test('records are deleted from BEPM first and MinIO objects only for managed projects_docs keys', function () {
    fakeBepmForDeleteJob();

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('deleteObject')->once()->with('projects_docs/2026/5/Kontrak/kontrak.pdf');

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak', 'status' => 'deleting']);

    runDeleteJob(new DeleteProjectFilesJob(5, [
        ['doc_id' => 2, 'key' => 'projects_docs/2026/5/Kontrak/kontrak.pdf'],
        ['doc_id' => 3, 'key' => 'uploads/old-doc.pdf'],
    ], $folder->id));

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'admin-docs/2'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'admin-docs/3'));

    expect(ProjectFolder::query()->whereKey($folder->id)->exists())->toBeFalse();
});

test('when a BEPM delete fails the folder is kept and unlocked, and the object stays', function () {
    fakeBepmForDeleteJob(deleteStatus: 500);

    mock(ProjectFileStorage::class)->shouldNotReceive('deleteObject');

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak', 'status' => 'deleting']);

    runDeleteJob(new DeleteProjectFilesJob(5, [
        ['doc_id' => 2, 'key' => 'projects_docs/2026/5/Kontrak/kontrak.pdf'],
    ], $folder->id));

    $folder->refresh();
    expect($folder->status)->toBeNull();
});

test('a 404 from BEPM counts as already deleted so retries stay idempotent', function () {
    fakeBepmForDeleteJob(deleteStatus: 404);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('deleteObject')->once()->with('projects_docs/2026/5/Kontrak/kontrak.pdf');

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak', 'status' => 'deleting']);

    runDeleteJob(new DeleteProjectFilesJob(5, [
        ['doc_id' => 2, 'key' => 'projects_docs/2026/5/Kontrak/kontrak.pdf'],
    ], $folder->id));

    expect(ProjectFolder::query()->whereKey($folder->id)->exists())->toBeFalse();
});
