<?php

use App\Jobs\DeleteProjectFilesJob;
use App\Models\ProjectFileSize;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
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
        ['id' => 2, 'title' => 'kontrak', 'created_at' => '2026-06-02T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/kontrak.pdf', 'size' => '1 MB']],
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
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSee('Kontrak')
        ->assertSee('laporan.pdf')
        ->assertSee('foto.png')
        ->assertDontSee('kontrak.pdf');
});

test('searching reaches files inside subfolders and exposes their location', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    $component = Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('search', 'kontrak')
        ->assertSee('kontrak.pdf');

    $row = collect($component->get('files'))->firstWhere('id', 2);

    expect($row['dir'])->toBe('Kontrak');
});

test('searching hides folders whose name does not match', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Laporan Bulanan']);

    $component = Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('search', 'laporan');

    expect(collect($component->get('folders'))->pluck('name')->all())->toBe(['Laporan Bulanan']);
});

test('file sizes come from the workspace DB, not the zero size BEPM reports', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id, docs: [
        ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '0 KB']],
    ]);

    ProjectFileSize::record(5, 1, 3 * 1024 * 1024);

    $component = Volt::actingAs($leader)->test('project.components.file-manager', ['id' => 5]);

    $row = collect($component->get('files'))->firstWhere('id', 1);

    expect($row['size'])->toBe('3.00 MB')
        ->and($row['size_bytes'])->toBe(3.0 * 1024 * 1024);
});

test('two documents can share the same display name — the suffix lives only in the key', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id, docs: [
        ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '2 MB']],
        ['id' => 2, 'title' => 'laporan', 'created_at' => '2026-06-02T00:00:00Z', 'admin_doc_category_id' => 7, 'files' => ['url' => 'projects_docs/2026/5/laporan (1).pdf', 'size' => '1 MB']],
    ]);

    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    $component = Volt::actingAs($leader)->test('project.components.file-manager', ['id' => 5]);

    expect(collect($component->get('files'))->firstWhere('id', 1)['name'])->toBe('laporan.pdf');

    $component->call('openFolder', $folder->id);

    $row = collect($component->get('files'))->firstWhere('id', 2);
    expect($row['name'])->toBe('laporan.pdf')
        ->and($row['key'])->toBe('projects_docs/2026/5/laporan (1).pdf');
});

test('renaming to a name already used by another document is allowed', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 2)
        ->set('renameDocName', 'laporan')
        ->call('renameDoc')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/2')
        && $request['title'] === 'laporan');
});

test('opening a folder shows only the files mapped to it', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openFolder', $folder->id)
        ->assertSee('kontrak.pdf')
        ->assertDontSee('laporan.pdf');
});

test('goBack navigates to the parent folder', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $parent = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Induk']);
    $child = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $parent->id, 'name' => 'Anak']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openFolder', $child->id)
        ->assertSet('currentFolderId', $child->id)
        ->call('goBack')
        ->assertSet('currentFolderId', $parent->id)
        ->call('goBack')
        ->assertSet('currentFolderId', null);
});

test('goBack refreshes the view to the parent folder (no stale computed)', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openFolder', $folder->id)
        ->assertSee('kontrak.pdf')
        ->assertDontSee('laporan.pdf')
        ->call('goBack')
        ->assertSet('currentFolderId', null)
        ->assertSee('laporan.pdf')
        ->assertDontSee('kontrak.pdf');
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

test('renaming a file patches only the title — the object key never changes', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)->shouldNotReceive('move');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 1)
        ->set('renameDocName', 'laporan-final')
        ->call('renameDoc')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['title'] === 'laporan-final'
        && ! array_key_exists('file', $request->data()));
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

test('renaming a folder with files applies instantly without touching storage or BEPM', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameFolder', $folder->id)
        ->set('renameFolderName', 'Kontrak 2026')
        ->call('renameFolder')
        ->assertHasNoErrors();

    $folder->refresh();
    expect($folder->name)->toBe('Kontrak 2026')
        ->and($folder->status)->toBeNull()
        ->and(ProjectFolderFile::query()->where('doc_id', 2)->value('project_folder_id'))->toBe($folder->id);

    Queue::assertNothingPushed();
    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

test('moving a folder to another parent applies instantly and keeps its files mapped', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $target = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startMoveFolder', $folder->id)
        ->set('moveFolderTargetId', $target->id)
        ->call('moveFolder');

    $folder->refresh();
    expect($folder->parent_id)->toBe($target->id)
        ->and($folder->status)->toBeNull()
        ->and(ProjectFolderFile::query()->where('doc_id', 2)->value('project_folder_id'))->toBe($folder->id);

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

test('updating keywords prefills from the document and patches only keyword to BEPM', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id, docs: [
        ['id' => 1, 'title' => 'laporan', 'created_at' => '2026-06-01T00:00:00Z', 'admin_doc_category_id' => 7, 'keyword' => ['lama'], 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '2 MB']],
    ]);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startEditKeyword', 1)
        ->assertSet('keywordDocId', 1)
        ->assertSet('keywordTags', ['lama'])
        ->set('keywordTags', [' kontrak', 'addendum ', 'kontrak', '2026'])
        ->call('updateKeyword')
        ->assertHasNoErrors()
        ->assertSet('keywordDocId', null)
        ->assertSet('keywordTags', []);

    Http::assertSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1')
        && $request['keyword'] === ['kontrak', 'addendum', '2026']
        && ! array_key_exists('file', $request->data()));
});

test('blank keyword tags are rejected', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startEditKeyword', 1)
        ->set('keywordTags', [' ', ''])
        ->call('updateKeyword')
        ->assertHasErrors('keywordTags');

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH'
        && str_contains($request->url(), 'admin-docs/1'));
});

test('keyword suggestions merge timeline titles first with other document keywords', function () {
    $leader = User::factory()->create();

    Http::fake([
        '*projects/5' => Http::response(['status' => 200, 'data' => [[
            'id' => 5, 'start_date' => '2026-01-01', 'project_leader_id' => $leader->id, 'support_team_internals' => [],
        ]]], 200),
        '*timelines/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 20, 'title' => 'PENGIRIMAN'],
            ['id' => 21, 'title' => 'PEMERIKSAAN'],
        ]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 1, 'title' => 'laporan', 'keyword' => ['kontrak', 'pengiriman'], 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf', 'size' => '2 MB']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);

    $component = Volt::actingAs($leader)->test('project.components.file-manager', ['id' => 5]);

    expect($component->get('keywordSuggestions'))
        ->toBe(['PENGIRIMAN', 'PEMERIKSAAN', 'kontrak']);
});

test('bulk delete dispatches DeleteProjectFilesJob and hides the rows immediately', function () {
    Queue::fake();
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    $component = Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1', '4'])
        ->call('deleteSelected')
        ->assertSet('selected', [])
        ->assertSet('pendingDeleteIds', [1, 4]);

    expect(collect($component->get('files'))->pluck('id')->all())
        ->not->toContain(1)
        ->not->toContain(4);

    Queue::assertPushed(DeleteProjectFilesJob::class, function (DeleteProjectFilesJob $job) {
        return $job->projectId === 5
            && $job->folderId === null
            && collect($job->items)->sortBy('doc_id')->values()->all() === [
                ['doc_id' => 1, 'key' => 'projects_docs/2026/5/laporan.pdf'],
                ['doc_id' => 4, 'key' => 'projects_docs/2026/5/foto.png'],
            ];
    });
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
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('confirmDeleteFolder', $folder->id)
        ->assertSet('deletingFolderFileCount', 1)
        ->call('deleteFolder');

    expect($folder->refresh()->status)->toBe('deleting');

    Queue::assertPushed(DeleteProjectFilesJob::class, function (DeleteProjectFilesJob $job) use ($folder) {
        return $job->folderId === $folder->id
            && $job->items === [['doc_id' => 2, 'key' => 'projects_docs/2026/5/kontrak.pdf']];
    });
});

test('bulk move only updates the folder mapping — no MinIO move, no BEPM patch', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $target = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->set('selected', ['1', '4'])
        ->set('moveSelectedTargetId', $target->id)
        ->call('moveSelected')
        ->assertSet('selected', []);

    expect(ProjectFolderFile::query()->where('doc_id', 1)->value('project_folder_id'))->toBe($target->id)
        ->and(ProjectFolderFile::query()->where('doc_id', 4)->value('project_folder_id'))->toBe($target->id);

    Http::assertNotSent(fn ($request) => $request->method() === 'PATCH');
});

test('moving a file back to the root removes its mapping row', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);
    ProjectFolderFile::place(5, 2, $folder->id);

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openFolder', $folder->id)
        ->set('selected', ['2'])
        ->set('moveSelectedTargetId', null)
        ->call('moveSelected');

    expect(ProjectFolderFile::query()->where('doc_id', 2)->exists())->toBeFalse();
});

test('preview presigns the real object key but names the download after the display title', function () {
    $leader = User::factory()->create();
    fakeBepmForFileManager(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('presignedGetUrl')
        ->once()
        ->with('projects_docs/2026/5/laporan.pdf', (int) config('uploads.project_files.presign_ttl'), 'laporan.pdf')
        ->andReturn('https://minio/presigned/laporan.pdf');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('openPreview', 1)
        ->assertSet('previewUrl', 'https://minio/presigned/laporan.pdf')
        ->assertSet('previewExt', 'pdf');
});

test('a rename rejected by BEPM shows an error and keeps the modal state', function () {
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

    mock(ProjectFileStorage::class)->shouldNotReceive('move');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->call('startRenameDoc', 1)
        ->set('renameDocName', 'laporan-final')
        ->call('renameDoc')
        ->assertSet('renamingDocId', 1);
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
        ->with('projects_docs/2026/5/Pengajuan Dana (1).pdf', (int) config('uploads.project_files.presign_ttl'), 'survei.pdf')
        ->andReturn('https://minio/presigned');

    Volt::actingAs($leader)
        ->test('project.components.file-manager', ['id' => 5])
        ->assertSee('survei.pdf')
        ->call('openPreview', 10)
        ->assertSet('previewUrl', 'https://minio/presigned');
});
