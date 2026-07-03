<?php

use App\Services\ProjectFileStorage;

use function Pest\Laravel\mock;

test('the cleanup command aborts stale multipart uploads and reports the count', function () {
    mock(ProjectFileStorage::class)
        ->shouldReceive('abortStaleMultipartUploads')
        ->once()
        ->with((int) config('uploads.project_files.stale_multipart_hours'))
        ->andReturn(3);

    $this->artisan('project-files:cleanup-multipart')
        ->expectsOutputToContain('di-abort: 3')
        ->assertSuccessful();
});

test('the cleanup command fails gracefully when storage errors out', function () {
    mock(ProjectFileStorage::class)
        ->shouldReceive('abortStaleMultipartUploads')
        ->once()
        ->andThrow(new RuntimeException('MinIO tidak dapat dihubungi'));

    $this->artisan('project-files:cleanup-multipart')
        ->assertFailed();
});
