<?php

use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;

test('a mapping row places a document inside a folder', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $mapping = ProjectFolderFile::factory()->create(['project_id' => 5, 'doc_id' => 42, 'project_folder_id' => $folder->id]);

    expect($mapping->folder->is($folder))->toBeTrue()
        ->and($folder->files()->pluck('doc_id')->all())->toBe([42]);
});

test('mapping rows are removed when their folder is deleted', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolderFile::factory()->create(['project_id' => 5, 'doc_id' => 42, 'project_folder_id' => $folder->id]);

    $folder->delete();

    expect(ProjectFolderFile::query()->where('doc_id', 42)->exists())->toBeFalse();
});

test('a document can only be mapped to one folder', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5]);
    ProjectFolderFile::factory()->create(['project_id' => 5, 'doc_id' => 42, 'project_folder_id' => $folder->id]);

    expect(fn () => ProjectFolderFile::factory()->create(['project_id' => 5, 'doc_id' => 42, 'project_folder_id' => $folder->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
