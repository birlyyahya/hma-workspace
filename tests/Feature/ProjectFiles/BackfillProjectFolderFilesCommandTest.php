<?php

use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
use Illuminate\Support\Facades\Http;

function fakeBepmForBackfill(): void
{
    Http::fake([
        '*projects/5' => Http::response(['status' => 200, 'data' => [[
            'id' => 5, 'start_date' => '2026-01-01', 'project_leader_id' => 1, 'support_team_internals' => [],
        ]]], 200),
        '*admin-docs/search*' => Http::response(['status' => 200, 'data' => [
            ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects_docs/2026/5/laporan.pdf']],
            ['id' => 2, 'title' => 'kontrak', 'files' => ['url' => 'projects_docs%2F2026%2F5%2FKontrak%2Fkontrak.pdf']],
            ['id' => 3, 'title' => 'addendum', 'files' => ['url' => 'projects_docs/2026/5/Kontrak/Addendum/addendum.pdf']],
            ['id' => 4, 'title' => 'hilang', 'files' => ['url' => 'projects_docs/2026/5/Folder Hilang/x.pdf']],
        ]], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
}

test('it maps legacy folder paths from object keys onto existing folders', function () {
    fakeBepmForBackfill();

    $kontrak = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $addendum = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $kontrak->id, 'name' => 'Addendum']);

    $this->artisan('projectfiles:backfill-folders', ['project' => 5])
        ->expectsOutputToContain('dipetakan=2 sudah-ada=0 root=1 tak-cocok=1')
        ->assertSuccessful();

    expect(ProjectFolderFile::query()->where('doc_id', 2)->value('project_folder_id'))->toBe($kontrak->id)
        ->and(ProjectFolderFile::query()->where('doc_id', 3)->value('project_folder_id'))->toBe($addendum->id)
        ->and(ProjectFolderFile::query()->where('doc_id', 1)->exists())->toBeFalse()
        ->and(ProjectFolderFile::query()->where('doc_id', 4)->exists())->toBeFalse();
});

test('a dry run reports the plan without writing any mapping', function () {
    fakeBepmForBackfill();

    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    $this->artisan('projectfiles:backfill-folders', ['project' => 5, '--dry-run' => true])
        ->assertSuccessful();

    expect(ProjectFolderFile::query()->count())->toBe(0);
});

test('existing mappings are not overwritten so manual moves survive a re-run', function () {
    fakeBepmForBackfill();

    ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $arsip = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Arsip']);
    ProjectFolderFile::place(5, 2, $arsip->id);

    $this->artisan('projectfiles:backfill-folders', ['project' => 5])
        ->expectsOutputToContain('sudah-ada=1')
        ->assertSuccessful();

    expect(ProjectFolderFile::query()->where('doc_id', 2)->value('project_folder_id'))->toBe($arsip->id);
});
