<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

test('forwards a zero-based chunk_index to the external upload API', function () {
    Http::fake([
        '*upload-chunks' => Http::response([
            'status' => 200,
            'message' => 'Upload complete',
            'data' => ['file' => 'admin_docs/2026/05/example.pdf'],
        ], 200),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('project-files.upload-chunk'), [
        'upload_id' => '50',
        'chunk_index' => 1,
        'total_chunks' => 1,
        'original_name' => 'example.pdf',
        'file' => UploadedFile::fake()->create('example.pdf', 10),
    ]);

    $response->assertOk()->assertJsonPath('data.file', 'admin_docs/2026/05/example.pdf');

    Http::assertSent(function ($request) {
        $body = collect($request->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

        return str_contains($request->url(), 'upload-chunks')
            && (string) $body['chunk_index'] === '0'
            && (string) $body['total_chunks'] === '1';
    });
});

test('requires a one-based chunk_index of at least 1', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson(route('project-files.upload-chunk'), [
        'upload_id' => '50',
        'chunk_index' => 0,
        'total_chunks' => 1,
        'original_name' => 'example.pdf',
        'file' => UploadedFile::fake()->create('example.pdf', 10),
    ])->assertJsonValidationErrors('chunk_index');
});
