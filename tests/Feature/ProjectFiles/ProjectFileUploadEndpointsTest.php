<?php

use App\Models\ProjectFolder;
use App\Models\Role;
use App\Models\User;
use App\Services\ProjectFileStorage;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\mock;

/**
 * @param  array<int, array<string, mixed>>  $docs
 */
function fakeBepmForUpload(int $leaderId, array $docs = [], int $registerStatus = 201): void
{
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
        '*admin-docs' => Http::response([
            'status' => $registerStatus,
            'data' => ['id' => 99],
            'errors' => $registerStatus === 201 ? [] : ['file' => ['File ditolak BEPM']],
        ], 200),
        '*' => Http::response(['status' => 200, 'data' => []], 200),
    ]);
}

// ------------------------------------------------------------------ Otorisasi

test('guest cannot initiate an upload', function () {
    $this->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
        'filename' => 'laporan.pdf',
        'size' => 1000,
        'mime' => 'application/pdf',
    ])->assertUnauthorized();
});

test('a user without access to the project gets forbidden', function () {
    $leader = User::factory()->create();
    $intruder = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $this->actingAs($intruder)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
        ])->assertForbidden();
});

test('a user with project view-all scope can initiate an upload', function () {
    $admin = User::factory()->create(['role_id' => Role::factory()->superAdmin()]);
    fakeBepmForUpload(leaderId: 999);

    mock(ProjectFileStorage::class)
        ->shouldReceive('initiateMultipart')
        ->once()
        ->andReturn('upload-abc');

    $this->actingAs($admin)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
        ])->assertCreated();
});

// ------------------------------------------------------------------- Initiate

test('the project leader can initiate and receives upload metadata', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('initiateMultipart')
        ->once()
        ->with('projects/5/laporan.pdf', 'application/pdf')
        ->andReturn('upload-abc');

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
        ])
        ->assertCreated()
        ->assertJson([
            'upload_id' => 'upload-abc',
            'key' => 'projects/5/laporan.pdf',
            'part_size' => (int) config('uploads.project_files.part_size'),
        ]);
});

test('a disallowed extension is rejected', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'malware.exe',
            'size' => 1000,
            'mime' => 'application/octet-stream',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('filename');
});

test('a file exceeding the max size is rejected', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => (int) config('uploads.project_files.max_file_size') + 1,
            'mime' => 'application/pdf',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('size');
});

test('a folder belonging to another project is rejected', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $foreignFolder = ProjectFolder::factory()->create(['project_id' => 6]);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
            'folder_id' => $foreignFolder->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('folder_id');
});

test('the object key is derived from the folder tree server-side', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $parent = ProjectFolder::factory()->create(['project_id' => 5, 'name' => 'Kontrak']);
    $child = ProjectFolder::factory()->create(['project_id' => 5, 'parent_id' => $parent->id, 'name' => 'Addendum']);

    mock(ProjectFileStorage::class)
        ->shouldReceive('initiateMultipart')
        ->once()
        ->with('projects/5/Kontrak/Addendum/laporan.pdf', 'application/pdf')
        ->andReturn('upload-abc');

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
            'folder_id' => $child->id,
        ])
        ->assertCreated()
        ->assertJsonPath('key', 'projects/5/Kontrak/Addendum/laporan.pdf');
});

test('path traversal characters in the filename are sanitized', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('initiateMultipart')
        ->once()
        ->andReturn('upload-abc');

    $response = $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => '../../etc/laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
        ])
        ->assertCreated();

    $key = $response->json('key');

    expect($key)->toStartWith('projects/5/')
        ->and($key)->not->toContain('..')
        ->and($key)->toEndWith('.pdf');
});

test('a duplicate filename gets a numeric suffix', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id, docs: [
        ['id' => 1, 'title' => 'laporan', 'files' => ['url' => 'projects/5/laporan.pdf']],
        ['id' => 2, 'title' => 'laporan (1)', 'files' => ['url' => 'projects/5/laporan (1).pdf']],
    ]);

    mock(ProjectFileStorage::class)
        ->shouldReceive('initiateMultipart')
        ->once()
        ->with('projects/5/laporan (2).pdf', 'application/pdf')
        ->andReturn('upload-abc');

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.initiate', ['project' => 5]), [
            'filename' => 'laporan.pdf',
            'size' => 1000,
            'mime' => 'application/pdf',
        ])
        ->assertCreated()
        ->assertJsonPath('key', 'projects/5/laporan (2).pdf');
});

// ----------------------------------------------------------------------- Sign

test('sign returns presigned urls per part number', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('signParts')
        ->once()
        ->with('projects/5/laporan.pdf', 'upload-abc', [1, 2])
        ->andReturn([1 => 'https://minio/part-1', 2 => 'https://minio/part-2']);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.sign', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/5/laporan.pdf',
            'part_numbers' => [1, 2],
        ])
        ->assertOk()
        ->assertJsonPath('urls.1', 'https://minio/part-1')
        ->assertJsonPath('urls.2', 'https://minio/part-2');
});

test('sign rejects a key outside the project prefix', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.sign', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/6/laporan.pdf',
            'part_numbers' => [1],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('key');
});

test('sign rejects a batch larger than the limit', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    $tooMany = range(1, (int) config('uploads.project_files.sign_batch_limit') + 1);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.sign', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/5/laporan.pdf',
            'part_numbers' => $tooMany,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('part_numbers');
});

// ------------------------------------------------------------------- Complete

test('complete finishes the multipart upload and registers the document to BEPM', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('completeMultipart')
        ->once()
        ->with('projects/5/laporan.pdf', 'upload-abc', [
            ['PartNumber' => 1, 'ETag' => '"etag-1"'],
            ['PartNumber' => 2, 'ETag' => '"etag-2"'],
        ]);

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.complete', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/5/laporan.pdf',
            'parts' => [
                ['part_number' => 1, 'etag' => '"etag-1"'],
                ['part_number' => 2, 'etag' => '"etag-2"'],
            ],
        ])
        ->assertCreated()
        ->assertJson(['name' => 'laporan.pdf', 'key' => 'projects/5/laporan.pdf']);

    Http::assertSent(function ($request) {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), 'admin-docs')
            && $request['filename'] === 'projects/5/laporan.pdf'
            && $request['project_id'] === 5
            && $request['admin_doc_category_id'] === 7;
    });
});

test('the MinIO object is deleted when BEPM registration fails', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id, registerStatus: 422);

    $storage = mock(ProjectFileStorage::class);
    $storage->shouldReceive('completeMultipart')->once();
    $storage->shouldReceive('deleteObject')->once()->with('projects/5/laporan.pdf');

    $this->actingAs($leader)
        ->postJson(route('project-files.uploads.complete', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/5/laporan.pdf',
            'parts' => [
                ['part_number' => 1, 'etag' => '"etag-1"'],
            ],
        ])
        ->assertStatus(502)
        ->assertJsonPath('errors.file.0', 'File ditolak BEPM');
});

// ---------------------------------------------------------------------- Abort

test('abort cancels the multipart upload', function () {
    $leader = User::factory()->create();
    fakeBepmForUpload(leaderId: $leader->id);

    mock(ProjectFileStorage::class)
        ->shouldReceive('abortMultipart')
        ->once()
        ->with('projects/5/laporan.pdf', 'upload-abc');

    $this->actingAs($leader)
        ->deleteJson(route('project-files.uploads.abort', ['project' => 5, 'uploadId' => 'upload-abc']), [
            'key' => 'projects/5/laporan.pdf',
        ])
        ->assertNoContent();
});
