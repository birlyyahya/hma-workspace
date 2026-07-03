<?php

namespace App\Jobs;

use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rename/move file project (physical move di MinIO + record BEPM).
 *
 * BEPM tidak punya endpoint update-path, jadi per file urutannya:
 *   1. CopyObject key lama → key baru (MinIO, server-side)
 *   2. Registrasi record BARU di BEPM dengan key baru
 *   3. Hapus record LAMA di BEPM (404 dianggap sudah terhapus)
 *   4. DeleteObject key lama
 * Gagal di langkah 2 → salinan dihapus, file lama utuh.
 * Gagal di langkah 3 → record baru + salinan di-rollback, file lama utuh.
 * Gagal di langkah 4 → objek lama yatim (hanya di-log; record sudah konsisten).
 *
 * Idempotent saat retry: item yang record lamanya sudah hilang dan key barunya
 * sudah terdaftar akan dilewati.
 *
 * Perubahan nama/parent folder hanya diterapkan bila SEMUA file sukses pindah,
 * supaya path folder tidak lepas dari prefix file yang tersisa.
 */
class MoveProjectFilesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array<int, array{doc_id: int, from_key: string, to_key: string, title: string, category_id: ?int}>  $items
     * @param  array{name?: string, parent_id?: ?int}|null  $folderUpdate
     */
    public function __construct(
        public readonly int $projectId,
        public readonly array $items,
        public readonly ?int $folderId = null,
        public readonly ?array $folderUpdate = null,
    ) {}

    public function handle(ProjectFileStorage $storage, ProjectWriter $writer, ProjectCache $cache): void
    {
        $failures = 0;

        $current = collect($cache->searchDocs(['project_id' => $this->projectId, 'limit' => 10000])['data'] ?? []);
        $byId = $current->keyBy('id');
        $existingUrls = $current->map(fn ($doc) => (string) data_get($doc, 'files.url', ''))->filter()->flip();

        foreach ($this->items as $item) {
            $old = $byId->get($item['doc_id']);

            if ($old === null) {
                if (! isset($existingUrls[$item['to_key']])) {
                    Log::warning('MoveProjectFilesJob: record lama hilang tanpa jejak key baru — dilewati', [
                        'project_id' => $this->projectId, 'item' => $item,
                    ]);
                }

                continue;
            }

            if (! $this->moveOne($storage, $writer, $item)) {
                $failures++;
            }
        }

        $this->finalizeFolder($failures);
        $cache->flushDocs($this->projectId);

        if ($failures > 0) {
            Log::warning('MoveProjectFilesJob selesai dengan kegagalan parsial', [
                'project_id' => $this->projectId,
                'failures' => $failures,
                'total' => count($this->items),
            ]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        if ($this->folderId !== null) {
            ProjectFolder::query()->whereKey($this->folderId)->update(['status' => null]);
        }

        Log::error('MoveProjectFilesJob gagal total', [
            'project_id' => $this->projectId,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * @param  array{doc_id: int, from_key: string, to_key: string, title: string, category_id: ?int}  $item
     */
    private function moveOne(ProjectFileStorage $storage, ProjectWriter $writer, array $item): bool
    {
        try {
            $storage->copyObject($item['from_key'], $item['to_key']);
        } catch (\Throwable $e) {
            Log::error('MoveProjectFilesJob: copy gagal', ['item' => $item, 'error' => $e->getMessage()]);

            return false;
        }

        $registered = $writer->registerDocument($this->projectId, [
            'title' => $item['title'],
            'admin_doc_category_id' => $item['category_id'],
            'filename' => $item['to_key'],
            'original_name' => basename($item['to_key']),
        ]);

        if (! $registered['ok']) {
            $this->quietDelete($storage, $item['to_key']);
            Log::error('MoveProjectFilesJob: registrasi key baru gagal — salinan dihapus', ['item' => $item]);

            return false;
        }

        $deleted = $writer->deleteDoc((int) $item['doc_id']);

        if (! $deleted['ok'] && $deleted['status'] !== 404) {
            $newId = data_get($registered['body'], 'data.id');

            if ($newId !== null) {
                $writer->deleteDoc((int) $newId);
            }

            $this->quietDelete($storage, $item['to_key']);
            Log::error('MoveProjectFilesJob: hapus record lama gagal — di-rollback', ['item' => $item]);

            return false;
        }

        $this->quietDelete($storage, $item['from_key'], orphanWarning: true);

        return true;
    }

    private function finalizeFolder(int $failures): void
    {
        if ($this->folderId === null) {
            return;
        }

        $folder = ProjectFolder::query()->find($this->folderId);

        if ($folder === null) {
            return;
        }

        if ($failures === 0 && $this->folderUpdate !== null) {
            $folder->fill($this->folderUpdate);
        }

        $folder->status = null;

        try {
            $folder->save();
        } catch (\Throwable $e) {
            Log::error('MoveProjectFilesJob: finalisasi folder gagal', [
                'folder_id' => $this->folderId, 'error' => $e->getMessage(),
            ]);
            ProjectFolder::query()->whereKey($this->folderId)->update(['status' => null]);
        }
    }

    private function quietDelete(ProjectFileStorage $storage, string $key, bool $orphanWarning = false): void
    {
        try {
            $storage->deleteObject($key);
        } catch (\Throwable $e) {
            $context = ['key' => $key, 'error' => $e->getMessage()];

            if ($orphanWarning) {
                Log::warning('MoveProjectFilesJob: hapus objek lama gagal — objek yatim', $context);
            } else {
                Log::error('MoveProjectFilesJob: hapus objek gagal', $context);
            }
        }
    }
}
