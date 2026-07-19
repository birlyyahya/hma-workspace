<?php

use App\Services\ProjectFileStorage;
use Illuminate\Support\Facades\Storage;

/**
 * Presigning murni kripto lokal — tak butuh MinIO nyata — jadi kita bisa
 * memverifikasi bahwa presigned URL memakai endpoint PUBLIK sementara operasi
 * server-side memakai disk internal.
 */
beforeEach(function () {
    $base = [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'us-east-1',
        'bucket' => 'workspace',
        'use_path_style_endpoint' => true,
    ];

    config()->set('filesystems.disks.project-files', [...$base, 'endpoint' => 'http://minio:9000']);
    config()->set('filesystems.disks.project-files-public', [...$base, 'endpoint' => 'https://storage.hanatekindo.com']);
    config()->set('uploads.project_files.disk', 'project-files');
    config()->set('uploads.project_files.signing_disk', 'project-files-public');

    Storage::forgetDisk(['project-files', 'project-files-public']);
});

test('a presigned GET url is signed with the public endpoint, not the internal one', function () {
    $url = (new ProjectFileStorage)->presignedGetUrl('projects_docs/2026/5/laporan.pdf', 5);

    expect($url)->toContain('storage.hanatekindo.com')
        ->and($url)->not->toContain('minio:9000')
        ->and($url)->toContain('X-Amz-Signature');
});

test('a presigned GET with a download name sets an inline content-disposition', function () {
    $url = (new ProjectFileStorage)->presignedGetUrl('projects_docs/2026/5/xyz.pdf', 5, 'Laporan Akhir.pdf');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['response-content-disposition'] ?? '')
        ->toContain('inline')
        ->toContain('filename="Laporan Akhir.pdf"');
});

test('presigned upload part urls are signed with the public endpoint', function () {
    $urls = (new ProjectFileStorage)->signParts('projects_docs/2026/5/laporan.pdf', 'upload-1', [1]);

    expect($urls[1])->toContain('storage.hanatekindo.com')
        ->and($urls[1])->not->toContain('minio:9000')
        ->and($urls[1])->toContain('partNumber=1');
});
