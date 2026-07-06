<?php

use App\Jobs\MoveProjectFilesJob;
use App\Models\ProjectFolder;
use App\Services\ProjectFileStorage;

use function Pest\Laravel\mock;

function runMoveJob(MoveProjectFilesJob $job): void
{
    $job->handle(app(ProjectFileStorage::class));
}

test('it lists objects under the old prefix and moves each to the new prefix', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak 3', 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->with('projects/5/Kontrak 3/')
        ->andReturn(['projects/5/Kontrak 3/a.pdf', 'projects/5/Kontrak 3/sub/b.pdf']);
    $storage->shouldReceive('move')->once()->with('projects/5/Kontrak 3/a.pdf', 'projects/5/Kontrak 2/a.pdf');
    $storage->shouldReceive('move')->once()->with('projects/5/Kontrak 3/sub/b.pdf', 'projects/5/Kontrak 2/sub/b.pdf');

    runMoveJob(new MoveProjectFilesJob(5, 'projects/5/Kontrak 3/', 'projects/5/Kontrak 2/', $folder->id, ['name' => 'Kontrak 2']));

    $folder->refresh();
    expect($folder->name)->toBe('Kontrak 2')
        ->and($folder->status)->toBeNull();
});

test('a failed move is logged and does not stop the rest; folder is finalized', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->andReturn(['projects/5/A/a.pdf', 'projects/5/A/b.pdf']);
    $storage->shouldReceive('move')->once()->with('projects/5/A/a.pdf', 'projects/5/B/a.pdf')
        ->andThrow(new RuntimeException('MinIO down'));
    $storage->shouldReceive('move')->once()->with('projects/5/A/b.pdf', 'projects/5/B/b.pdf');

    runMoveJob(new MoveProjectFilesJob(5, 'projects/5/A/', 'projects/5/B/', $folder->id));

    expect($folder->refresh()->status)->toBeNull();
});

test('an empty listing still applies the folder update and releases the lock', function () {
    $folder = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'X', 'status' => 'moving']);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('listUnder')->once()->andReturn([]);
    $storage->shouldNotReceive('move');

    runMoveJob(new MoveProjectFilesJob(5, 'projects/5/X/', 'projects/5/Y/', $folder->id, ['name' => 'Y']));

    $folder->refresh();
    expect($folder->name)->toBe('Y')
        ->and($folder->status)->toBeNull();
});
