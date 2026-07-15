<?php

use App\Models\ProjectFolder;

test('path is assembled from the folder chain up to the root', function () {
    $root = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $mid = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $root->id, 'name' => 'Addendum']);
    $leaf = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $mid->id, 'name' => 'Revisi 2']);

    expect($root->path())->toBe('Kontrak')
        ->and($leaf->path())->toBe('Kontrak/Addendum/Revisi 2');
});

test('saving a folder whose parent is its own descendant is refused', function () {
    $root = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $child = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $root->id, 'name' => 'Addendum']);

    $root->parent_id = $child->id;

    expect(fn () => $root->save())->toThrow(LogicException::class);
});

test('a folder cannot be its own parent', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);

    $folder->parent_id = $folder->id;

    expect(fn () => $folder->save())->toThrow(LogicException::class);
});

test('folder names must be unique within the same parent and project', function () {
    $parent = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $parent->id, 'name' => 'Addendum']);

    expect(fn () => ProjectFolder::factory()->create([
        'project_id' => 5,
        'parent_id' => $parent->id,
        'name' => 'Addendum',
    ]))->toThrow(Illuminate\Database\QueryException::class);
});
