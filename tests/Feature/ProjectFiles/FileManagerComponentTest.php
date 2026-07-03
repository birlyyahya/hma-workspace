<?php

use App\Jobs\DeleteProjectFilesJob;
use App\Jobs\MoveProjectFilesJob;
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
        ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects/5/laporan.pdf', 'size' => '2 MB']],
        ['id' => 2, 'title' => 'kontrak', 'created_at' => '2026-06-02T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects/5/Kontrak/kontrak.pdf', 'size' => '1 MB']],
        ['id' => 3, 'title' => 'Dokumen Lama', 'created_at' => '2026-05-01T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'uploads/old-doc.pdf', 'size' => '3 MB']],
        ['id' => 4, 'title' => 'foto', 'created_at' => '2026-06-03T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects/5/foto.png', 'size' => '500 KB']],
    ];

    Http::fake([
        '*projects/5' => Http::response([
            'status' => 200,
            'data' => [[
                'id' => 5,
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

test('the root shows folders plus root-level and legacy files only', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSee('Kontrak')
        ->assertSee('laporan.pdf')
        ->assertSee('foto.png')
        ->assertSee('Dokumen Lama')
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
        ->assertDontSee('laporan.pdf')
        ->assertDontSee('Dokumen Lama');
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

test('renaming a file dispatches MoveProjectFilesJob with the new key', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 1)
        ->set('renameDocName', 'laporan-final')
        ->call('renameDoc')
        ->assertHasNoErrors();

    Queue::assertPushed(MoveProjectFilesJob::class, function (MoveProjectFilesJob $job) {
        return $job->projectId === 5
            && $job->items[0]['from_key'] === 'projects/5/laporan.pdf'
            && $job->items[0]['to_key'] === 'projects/5/laporan-final.pdf';
    });
});

test('a legacy file cannot be renamed', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 3)
        ->assertSet('renamingDocId', null);

    Queue::assertNothingPushed();
});

test('renaming a folder locks it and dispatches the move job with re-prefixed keys', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameFolder', $folder->id)
        ->set('renameFolderName', 'Kontrak 2026')
        ->call('renameFolder')
        ->assertHasNoErrors();

    expect($folder->refresh()->status)->toBe('moving');

    Queue::assertPushed(MoveProjectFilesJob::class, function (MoveProjectFilesJob $job) use ($folder) {
        return $job->folderId === $folder->id
            && $job->folderUpdate === ['name' => 'Kontrak 2026']
            && $job->items[0]['from_key'] === 'projects/5/Kontrak/kontrak.pdf'
            && $job->items[0]['to_key'] === 'projects/5/Kontrak 2026/kontrak.pdf';
    });
});

test('deleting a file removes the BEPM record first and then the MinIO object', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('deleteObject')
        ->once()
        ->with('projects/5/laporan.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteDoc', 1)
        ->call('deleteDoc');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/1'));
});

test('deleting a legacy file skips the MinIO object', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)->shouldNotReceive('deleteObject');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteDoc', 3)
        ->call('deleteDoc');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), 'admin-docs/3'));
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
            && $job->items === [['doc_id' => 2, 'key' => 'projects/5/Kontrak/kontrak.pdf']];
    });
});

test('bulk move dispatches one job for the selected non-legacy files', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $target = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1', '3', '4'])
        ->set('moveSelectedTargetId', $target->id)
        ->call('moveSelected');

    Queue::assertPushed(MoveProjectFilesJob::class, function (MoveProjectFilesJob $job) {
        $toKeys = array_column($job->items, 'to_key');

        return count($job->items) === 2
            && in_array('projects/5/Arsip/laporan.pdf', $toKeys, true)
            && in_array('projects/5/Arsip/foto.png', $toKeys, true);
    });
});

test('preview uses a presigned url for MinIO files', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('presignedGetUrl')
        ->once()
        ->with('projects/5/laporan.pdf', (int) config('uploads.project_files.presign_ttl'))
        ->andReturn('https://minio/presigned/laporan.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openPreview', 1)
        ->assertSet('previewUrl', 'https://minio/presigned/laporan.pdf')
        ->assertSet('previewExt', 'pdf');
});
