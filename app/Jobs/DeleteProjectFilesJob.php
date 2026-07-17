<?php

namespace App\Jobs;

use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Hapus banyak file project (dipakai "hapus folder beserta isinya").
 *
 * Urutan per file: record BEPM dulu, baru objek MinIO — record yatim lebih
 * buruk daripada objek yatim (objek yatim hanya memakan disk dan bisa
 * dibersihkan manual, record yatim tampil sebagai file rusak di UI).
 * Respons 404 dari BEPM dianggap sudah terhapus (idempotent saat retry).
 *
 * Folder hanya dihapus bila SEMUA file sukses dihapus; kalau ada kegagalan,
 * status folder dikembalikan ke idle supaya bisa dicoba lagi.
 */
class DeleteProjectFilesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array<int, array{doc_id: int, key: string}>  $items
     */
    public function __construct(
        public readonly int $projectId,
        public readonly array $items,
        public readonly ?int $folderId = null,
    ) {}

    public function handle(ProjectFileStorage $storage, ProjectWriter $writer, ProjectCache $cache): void
    {
        $failures = 0;

        foreach ($this->items as $item) {
            $deleted = $writer->deleteDoc((int) $item['doc_id']);

            if (! $deleted['ok'] && $deleted['status'] !== 404) {
                $failures++;
                Log::error('DeleteProjectFilesJob: hapus record BEPM gagal', ['item' => $item]);

                continue;
            }

            ProjectFolderFile::query()->where('doc_id', (int) $item['doc_id'])->delete();

            if (str_starts_with($item['key'], 'projects_docs/')) {
                try {
                    $storage->deleteObject($item['key']);
                } catch (\Throwable $e) {
                    Log::warning('DeleteProjectFilesJob: hapus objek gagal — objek yatim', [
                        'key' => $item['key'], 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($this->folderId !== null) {
            if ($failures === 0) {
                ProjectFolder::query()->whereKey($this->folderId)->delete();
            } else {
                ProjectFolder::query()->whereKey($this->folderId)->update(['status' => null]);
            }
        }

        $cache->flushDocs($this->projectId);

        if ($failures > 0) {
            Log::warning('DeleteProjectFilesJob selesai dengan kegagalan parsial', [
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

        Log::error('DeleteProjectFilesJob gagal total', [
            'project_id' => $this->projectId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
