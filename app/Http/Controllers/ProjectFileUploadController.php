<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProjectFiles\AbortUploadRequest;
use App\Http\Requests\ProjectFiles\CompleteUploadRequest;
use App\Http\Requests\ProjectFiles\InitiateUploadRequest;
use App\Http\Requests\ProjectFiles\SignUploadPartsRequest;
use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

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
     * Mulai multipart upload: bangun object key dari folder (server-side,
     * tidak pernah menerima path mentah dari client) + nama file tersanitasi.
     */
    public function initiate(InitiateUploadRequest $request, int $project): JsonResponse
    {
        $filename = $this->sanitizeFilename((string) $request->validated('filename'));

        $folderPath = '';
        if ($request->validated('folder_id') !== null) {
            $folderPath = ProjectFolder::query()
                ->findOrFail((int) $request->validated('folder_id'))
                ->path();
        }

        $year = project_storage_year($this->cache->projectFor($project));
        $prefix = "projects/{$year}/{$project}/".($folderPath !== '' ? $folderPath.'/' : '');
        $key = $this->uniqueKey($project, $prefix, $filename);

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
     */
    public function complete(CompleteUploadRequest $request, int $project, string $uploadId): JsonResponse
    {
        $key = (string) $request->validated('key');
        $name = basename($key);

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

        $result = $this->writer->registerDocument($project, [
            'title' => (string) ($request->validated('title') ?? pathinfo($name, PATHINFO_FILENAME)),
            'admin_doc_category_id' => $categoryId,
            'filename' => $key,
            'original_name' => $name,
        ]);

        if (! $result['ok']) {
            $this->deleteOrphanedObject($key);

            return response()->json([
                'message' => 'File terupload tetapi registrasi dokumen gagal — upload dibatalkan.',
                'errors' => $result['body']['errors'] ?? [],
            ], 502);
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
     * Hindari tabrakan nama dengan dokumen yang sudah terdaftar di BEPM
     * dengan suffix " (n)" ala file manager.
     */
    private function uniqueKey(int $project, string $prefix, string $filename): string
    {
        $existing = collect($this->cache->documentsFor($project))
            ->map(fn (array $doc) => (string) data_get($doc, 'files.url', ''))
            ->filter()
            ->flip();

        $key = $prefix.$filename;

        if (! isset($existing[$key])) {
            return $key;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = 1;

        do {
            $candidate = $prefix.$base." ({$suffix})".($extension !== '' ? ".{$extension}" : '');
            $suffix++;
        } while (isset($existing[$candidate]));

        return $candidate;
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
