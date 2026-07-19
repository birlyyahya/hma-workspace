<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectFiles\AbortUploadRequest;
use App\Http\Requests\ProjectFiles\CompleteUploadRequest;
use App\Http\Requests\ProjectFiles\InitiateUploadRequest;
use App\Http\Requests\ProjectFiles\SignUploadPartsRequest;
use App\Jobs\RegisterProjectDocJob;
use App\Models\ProjectFileSize;
use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Control plane direct multipart upload project files (browser → MinIO).
 * Byte file tidak pernah melewati endpoint ini — hanya initiate, sign,
 * complete, dan abort. Otorisasi & validasi ada di FormRequest ProjectFiles.
 */
class ProjectFileUploadController extends Controller
{
    public function __construct(
        private readonly ProjectFileStorage $storage,
        private readonly ProjectCache $cache,
        private readonly ProjectWriter $writer,
    ) {}

    /**
     * Mulai multipart upload. Object key SELALU flat di root project dengan
     * prefix ULID: projects_docs/{tahun}/{project}/{ulid}-{nama}. Folder murni
     * virtual (dicatat di project_folder_files saat complete), dan ULID
     * menjamin key unik tanpa pengecekan dedup — kebal race dua upload
     * bernama sama yang saling menimpa. Nama asli tetap dipertahankan di key
     * demi keterbacaan operasional; nama tampilan datang dari title BEPM.
     */
    public function initiate(InitiateUploadRequest $request, int $project): JsonResponse
    {
        $filename = $this->sanitizeFilename((string) $request->validated('filename'));

        $year = project_storage_year($this->cache->projectFor($project));
        $key = "projects_docs/{$year}/{$project}/".strtolower((string) Str::ulid())."-{$filename}";

        $uploadId = $this->storage->initiateMultipart($key, (string) $request->validated('mime'));

        return response()->json([
            'upload_id' => $uploadId,
            'key' => $key,
            'part_size' => (int) config('uploads.project_files.part_size'),
        ], 201);
    }

    /**
     * Presigned URL UploadPart per batch part number.
     */
    public function sign(SignUploadPartsRequest $request, int $project, string $uploadId): JsonResponse
    {
        $urls = $this->storage->signParts(
            (string) $request->validated('key'),
            $uploadId,
            array_map('intval', (array) $request->validated('part_numbers')),
        );

        return response()->json(['urls' => $urls]);
    }

    /**
     * Selesaikan multipart lalu registrasikan dokumen ke BEPM. Jika registrasi
     * gagal, objek MinIO yang baru jadi dihapus supaya tidak ada file yatim.
     * Penempatan folder dicatat di project_folder_files setelah doc id BEPM
     * diketahui (key flat tidak memuat folder).
     */
    public function complete(CompleteUploadRequest $request, int $project, string $uploadId): JsonResponse
    {
        $key = (string) $request->validated('key');
        $name = basename($key);

        $folderId = $request->validated('folder_id') !== null ? (int) $request->validated('folder_id') : null;
        $folder = $folderId !== null ? ProjectFolder::query()->find($folderId) : null;
        $sizeBytes = $request->validated('size') !== null ? (int) $request->validated('size') : null;

        $categoryId = $request->validated('admin_doc_category_id') !== null
            ? (int) $request->validated('admin_doc_category_id')
            : $this->defaultCategoryId();

        if ($categoryId === null) {
            return response()->json([
                'message' => 'Kategori dokumen tidak tersedia — periksa data kategori di BEPM.',
            ], 422);
        }

        $parts = collect((array) $request->validated('parts'))
            ->map(fn (array $part) => [
                'PartNumber' => (int) $part['part_number'],
                'ETag' => (string) $part['etag'],
            ])
            ->all();

        $this->storage->completeMultipart($key, $uploadId, $parts);

        // Nama asli dari client dipakai untuk title/original_name supaya
        // suffix " (n)" pada key (anti-tabrakan MinIO) tidak bocor ke tampilan.
        $original = $request->validated('filename') !== null
            ? $this->sanitizeFilename((string) $request->validated('filename'))
            : $name;

        $payload = [
            'title' => (string) ($request->validated('title') ?? pathinfo($original, PATHINFO_FILENAME)),
            'admin_doc_category_id' => $categoryId,
            'filename' => $key,
            'original_name' => $original,
            'keyword' => $this->keywordsFor($project, $key, $folder?->path(), $original),
        ];

        $result = $this->writer->registerDocument($project, $payload);

        if (! $result['ok']) {
            if ($this->isTransientFailure($result)) {
                RegisterProjectDocJob::dispatch($project, $payload, $folderId, $sizeBytes);

                return response()->json([
                    'name' => $name,
                    'key' => $key,
                    'pending' => true,
                    'message' => 'File terupload; registrasi dokumen diproses di latar belakang.',
                ], 202);
            }

            $this->deleteOrphanedObject($key);

            // 422, BUKAN 5xx: respons 5xx dari origin diganti halaman error
            // oleh Cloudflare sehingga pesan penolakan BEPM tidak pernah
            // sampai ke browser di production.
            return response()->json([
                'message' => 'File terupload tetapi registrasi dokumen ditolak BEPM — upload dibatalkan.',
                'errors' => $result['body']['errors'] ?? [],
            ], 422);
        }

        $docId = (int) data_get($result, 'body.data.id', 0);

        if ($docId > 0) {
            if ($folderId !== null) {
                ProjectFolderFile::place($project, $docId, $folderId);
            }

            if ($sizeBytes !== null) {
                ProjectFileSize::record($project, $docId, $sizeBytes);
            }
        } elseif ($folderId !== null) {
            Log::warning('Upload complete: BEPM tidak mengembalikan doc id — file jatuh ke root', [
                'project_id' => $project, 'key' => $key, 'folder_id' => $folderId,
            ]);
        }

        return response()->json([
            'name' => $name,
            'key' => $key,
            'document' => $result['body']['data'] ?? [],
        ], 201);
    }

    /**
     * Batalkan multipart upload yang sedang berjalan.
     */
    public function abort(AbortUploadRequest $request, int $project, string $uploadId): Response
    {
        $this->storage->abortMultipart((string) $request->validated('key'), $uploadId);

        return response()->noContent();
    }

    /**
     * Buang karakter path & traversal, pertahankan ekstensi.
     */
    private function sanitizeFilename(string $original): string
    {
        $name = (string) preg_replace('/[\x00-\x1F\/\\\\<>:"|?*]+/', '', $original);

        while (str_contains($name, '..')) {
            $name = str_replace('..', '.', $name);
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = trim(pathinfo($name, PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'file';
        }

        return $base.($extension !== '' ? ".{$extension}" : '');
    }

    /**
     * Kata kunci otomatis untuk dokumen (parameter wajib BEPM). Lihat
     * project_doc_keywords() untuk aturannya. $folderPath dan $displayName
     * dipasok eksplisit karena key flat ber-ULID tidak lagi memuat segmen
     * folder maupun nama yang bermakna.
     *
     * @return array<int, string>
     */
    private function keywordsFor(int $project, string $key, ?string $folderPath = null, ?string $displayName = null): array
    {
        return project_doc_keywords($this->cache->projectFor($project), $project, $key, $folderPath ?? '', $displayName);
    }

    /**
     * Kategori default saat client tidak memilih: entri pertama daftar
     * kategori dokumen BEPM.
     */
    private function defaultCategoryId(): ?int
    {
        $first = collect($this->cache->docCategories())->first();

        return isset($first['id']) ? (int) $first['id'] : null;
    }

    /**
     * Kegagalan registrasi yang bersifat sementara (timeout/transport atau 5xx)
     * — layak diselesaikan via retry di background. Penolakan definitif dari
     * BEPM (mis. validasi 4xx) BUKAN transient: objek jadi yatim dan dibersihkan.
     *
     * @param  array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}  $result
     */
    private function isTransientFailure(array $result): bool
    {
        $bodyStatus = (int) ($result['body']['status'] ?? 0);

        return $result['status'] === null
            || $result['status'] >= 500
            || $bodyStatus >= 500;
    }

    private function deleteOrphanedObject(string $key): void
    {
        try {
            $this->storage->deleteObject($key);
        } catch (\Throwable $e) {
            Log::error('Gagal menghapus objek yatim setelah registrasi BEPM gagal', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
