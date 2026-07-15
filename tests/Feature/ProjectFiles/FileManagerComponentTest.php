<?php

use App\Jobs\DeleteProjectFilesJob;
use App\Jobs\MoveProjectFilesJob;
use App\Jobs\SyncProjectDocPathJob;
use App\Models\ProjectFolder;
use App\Models\User;
use App\Services\ProjectFileStorage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(fn () => Livewire::withoutLazyLoading());

/**
 * @param  array<int, array<string, mixed>>  $docs
 */
function fakeBepmForFileManager(int $leaderId, ?array $docs = null): void
{
    $docs ??= [
        ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '2 MB']],
        ['id' => 2, 'title' => 'kontrak', 'created_at' => '2026-06-02T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/Kontrak/kontrak.pdf', 'size' => '1 MB']],
        ['id' => 4, 'title' => 'foto', 'created_at' => '2026-06-03T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/foto.png', 'size' => '500 KB']],
    ];

    Http::fake([
        '*projects/5' => Http::response([
            'status' => 200,
            'data' => [[
                'id' => 5,
                'start_date' => '2026-01-01',
                'project_leader_id' => $leaderId,
                'support_team_internals' => [],
            ]],
        ], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => $docs], 200),
        '*admin-doc-categories*' => Http::response(['status' => 200, 'data' => [['id' => 7, 'name' => 'Umum']]], 200),
        '*admin-docs/*' => Http::response(['status' => 200], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
}

test('a user without project access sees the forbidden state', function () {
    $intruder = User::factory()->create();
    fakeBepmForFileManager(leaderId: 999);

    Volt::actingAs($intruder)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSet('forbidden', true)
        ->assertSee('Akses Ditolak');
});

test('the root shows folders plus root-level files only', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSee('Kontrak')
        ->assertSee('laporan.pdf')
        ->assertSee('foto.png')
        ->assertDontSee('kontrak.pdf');
});

test('opening a folder shows only the files under its path', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openFolder', $folder->id)
        ->assertSee('kontrak.pdf')
        ->assertDontSee('laporan.pdf');
});

test('search filters files by name', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('search', 'foto')
        ->assertSee('foto.png')
        ->assertDontSee('laporan.pdf');
});

test('the category filter narrows files by extension group', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('categoryFilter', 'gambar')
        ->assertSee('foto.png')
        ->assertDontSee('laporan.pdf');
});

test('a folder can be created and duplicate names in the same parent are refused', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    $component = Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('newFolderName', 'Dokumen Kontrak')
        ->call('createFolder')
        ->assertHasNoErrors();

    expect(ProjectFolder::query()->where('project_id', 5)->where('name', 'Dokumen Kontrak')->exists())->toBeTrue();

    $component
        ->set('newFolderName', 'Dokumen Kontrak')
        ->call('createFolder')
        ->assertHasErrors('newFolderName');
});

test('a folder name with path characters is rejected', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('newFolderName', '../etc')
        ->call('createFolder')
        ->assertHasErrors('newFolderName');
});

test('an intruder cannot create folders even by calling the action directly', function () {
    $intruder = User::factory()->create();
    fakeBepmForFileManager(leaderId: 999);

    Volt::actingAs($intruder)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('newFolderName', 'Percobaan')
        ->call('createFolder');

    expect(ProjectFolder::query()->where('project_id', 5)->exists())->toBeFalse();
});

test('renaming a file moves the object synchronously in MinIO', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('move')
        ->once()
        ->with('projects_docs/2026/5/laporan.pdf', 'projects_docs/2026/5/laporan-final.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 1)
        ->set('renameDocName', 'laporan-final')
        ->call('renameDoc')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['file'] === 'projects_docs/2026/5/laporan-final.pdf'
        && $request['title'] === 'laporan-final');
});

test('renaming an unknown document id is a no-op', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)->shouldNotReceive('move');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 999)
        ->assertSet('renamingDocId', null);
});

test('renaming a non-empty folder locks it and dispatches the move job with prefixes', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    mock(ProjectFileStorage::class)
        ->shouldReceive('listUnder')
        ->once()
        ->with('projects_docs/2026/5/Kontrak/')
        ->andReturn(['projects_docs/2026/5/Kontrak/kontrak.pdf']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameFolder', $folder->id)
        ->set('renameFolderName', 'Kontrak 2026')
        ->call('renameFolder')
        ->assertHasNoErrors();

    expect($folder->refresh()->status)->toBe('moving');

    Queue::assertPushed(MoveProjectFilesJob::class, function (MoveProjectFilesJob $job) use ($folder) {
        return $job->folderId === $folder->id
            && $job->oldPrefix === 'projects_docs/2026/5/Kontrak/'
            && $job->newPrefix === 'projects_docs/2026/5/Kontrak 2026/'
            && $job->folderUpdate === ['name' => 'Kontrak 2026'];
    });
});

test('renaming an empty folder updates it directly without dispatching a job', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kosong']);

    mock(ProjectFileStorage::class)
        ->shouldReceive('listUnder')
        ->once()
        ->with('projects_docs/2026/5/Kosong/')
        ->andReturn([]);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameFolder', $folder->id)
        ->set('renameFolderName', 'Kosong Baru')
        ->call('renameFolder')
        ->assertHasNoErrors();

    $folder->refresh();
    expect($folder->name)->toBe('Kosong Baru')
        ->and($folder->status)->toBeNull();

    Queue::assertNothingPushed();
});

test('deleting a file removes the BEPM record first and then the MinIO object', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('deleteObject')
        ->once()
        ->with('projects_docs/2026/5/laporan.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteDoc', 1)
        ->call('deleteDoc');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/1'));
});

test('bulk delete removes each selected file record and its MinIO object', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('deleteObject')->once()->with('projects_docs/2026/5/laporan.pdf');
    $storage->shouldReceive('deleteObject')->once()->with('projects_docs/2026/5/foto.png');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1', '4'])
        ->call('deleteSelected');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'admin-docs/1'));
    Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), 'admin-docs/4'));
});

test('an empty folder is deleted immediately', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kosong']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteFolder', $folder->id);

    expect(ProjectFolder::query()->whereKey($folder->id)->exists())->toBeFalse();
});

test('deleting a folder with files locks it and dispatches DeleteProjectFilesJob', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteFolder', $folder->id)
        ->assertSet('deletingFolderFileCount', 1)
        ->call('deleteFolder');

    expect($folder->refresh()->status)->toBe('deleting');

    Queue::assertPushed(DeleteProjectFilesJob::class, function (DeleteProjectFilesJob $job) use ($folder) {
        return $job->folderId === $folder->id
            && $job->items === [['doc_id' => 2, 'key' => 'projects_docs/2026/5/Kontrak/kontrak.pdf']];
    });
});

test('bulk move moves each selected file synchronously', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $target = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/laporan.pdf', 'projects_docs/2026/5/Arsip/laporan.pdf');
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/foto.png', 'projects_docs/2026/5/Arsip/foto.png');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1', '4'])
        ->set('moveSelectedTargetId', $target->id)
        ->call('moveSelected');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['file'] === 'projects_docs/2026/5/Arsip/laporan.pdf');
    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/4')
        && $request['file'] === 'projects_docs/2026/5/Arsip/foto.png');
});

test('preview uses a presigned url for MinIO files', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('presignedGetUrl')
        ->once()
        ->with('projects_docs/2026/5/laporan.pdf', (int) config('uploads.project_files.presign_ttl'))
        ->andReturn('https://minio/presigned/laporan.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openPreview', 1)
        ->assertSet('previewUrl', 'https://minio/presigned/laporan.pdf')
        ->assertSet('previewExt', 'pdf');
});

test('a rename whose BEPM sync fails hands off to the background job without rollback', function () {
    Queue::fake();
    $leader = User::factory()->create();

    Http::fake([
        '*projects/5' => Http::response(['status' => 200, 'data' => [[
            'id' => 5, 'start_date' => '2026-01-01', 'project_leader_id' => $leader->id, 'support_team_internals' => [],
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7,
                'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '2 MB']],
        ]], 200),
        '*admin-doc-categories*' => Http::response(['status' => 200, 'data' => [['id' => 7, 'name' => 'Umum']]], 200),
        '*admin-docs/*' => Http::response(['status' => 500], 500),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('move')->once()->with('projects_docs/2026/5/laporan.pdf', 'projects_docs/2026/5/laporan-final.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 1)
        ->set('renameDocName', 'laporan-final')
        ->call('renameDoc')
        ->assertHasNoErrors();

    Queue::assertPushed(SyncProjectDocPathJob::class, fn (SyncProjectDocPathJob $job) => $job->docId === 1
        && $job->newKey === 'projects_docs/2026/5/laporan-final.pdf'
        && $job->extra['title'] === 'laporan-final');
});

test('a move whose object is already at the destination is treated as done and still syncs BEPM', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $target = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('move')->once()
        ->with('projects_docs/2026/5/laporan.pdf', 'projects_docs/2026/5/Arsip/laporan.pdf')
        ->andThrow(new RuntimeException('source missing'));
    $storage->shouldReceive('exists')->once()
        ->with('projects_docs/2026/5/Arsip/laporan.pdf')->andReturn(true);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1'])
        ->set('moveSelectedTargetId', $target->id)
        ->call('moveSelected');

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['file'] === 'projects_docs/2026/5/Arsip/laporan.pdf');
});

test('a percent-encoded BEPM url is decoded to the real object key', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id, docs: [
        ['id' => 10, 'title' => 'survei', 'created_at' => '2026-07-01T00:00:00Z', 'admin_doc_category_id' => 7,
            'files' => ['url' => 'projects_docs%2F2026%2F5%2FPengajuan%20Dana%20%281%29.pdf', 'size' => '1 MB']],
    ]);

    mock(ProjectFileStorage::class)
        ->shouldReceive('presignedGetUrl')
        ->once()
        ->with('projects_docs/2026/5/Pengajuan Dana (1).pdf', (int) config('uploads.project_files.presign_ttl'))
        ->andReturn('https://minio/presigned');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSee('Pengajuan Dana (1).pdf')
        ->call('openPreview', 10)
        ->assertSet('previewUrl', 'https://minio/presigned');
});
