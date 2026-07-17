<?php

use App\Jobs\RegisterProjectDocJob;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Illuminate\Support\Facades\Http;

/**
 * @return array{title: string, admin_doc_category_id: int, filename: string, original_name: string, keyword: array<int, string>}
 */
function registerPayload(): array
{
    return [
        'title' => 'laporan',
        'admin_doc_category_id' => 7,
        'filename' => 'projects_docs/2026/5/laporan.pdf',
        'original_name' => 'laporan.pdf',
        'keyword' => ['laporan'],
    ];
}

test('it registers the document when the key is not yet in BEPM', function () {
    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-docs' => Http::response(['status' => 201, 'data' => ['id' => 10]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    (new RegisterProjectDocJob(5, registerPayload()))->handle(app(ProjectCache::class), app(ProjectWriter::class));

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), 'admin-docs')
        && $request['file'] === 'projects_docs/2026/5/laporan.pdf');
});

test('it skips registration when the key already exists (idempotent under timeout-but-succeeded)', function () {
    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 10, 'files' => ['url' => 'projects_docs%2F2026%2F5%2Flaporan.pdf']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    (new RegisterProjectDocJob(5, registerPayload()))->handle(app(ProjectCache::class), app(ProjectWriter::class));

    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), 'admin-docs'));
});

test('it records the folder placement after a successful registration', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-docs' => Http::response(['status' => 201, 'data' => ['id' => 10]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    (new RegisterProjectDocJob(5, registerPayload(), $folder->id))->handle(app(ProjectCache::class), app(ProjectWriter::class));

    expect(ProjectFolderFile::query()->where('doc_id', 10)->value('project_folder_id'))->toBe($folder->id);
});

test('it records the folder placement even when the key turns out to be already registered', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 10, 'files' => ['url' => 'projects_docs%2F2026%2F5%2Flaporan.pdf']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    (new RegisterProjectDocJob(5, registerPayload(), $folder->id))->handle(app(ProjectCache::class), app(ProjectWriter::class));

    expect(ProjectFolderFile::query()->where('doc_id', 10)->value('project_folder_id'))->toBe($folder->id);
});

test('it throws so the queue retries when registration fails', function () {
    Http::fake([
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => []], 200),
        '*admin-docs' => Http::response(['message' => 'timeout'], 500),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    expect(fn () => (new RegisterProjectDocJob(5, registerPayload()))->handle(app(ProjectCache::class), app(ProjectWriter::class)))
        ->toThrow(RuntimeException::class);
});
