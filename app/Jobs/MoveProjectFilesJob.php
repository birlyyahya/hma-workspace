<?php

namespace App\Jobs;

use App\Models\ProjectFolder;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pindahkan seluruh objek MinIO sebuah folder saat folder di-rename/dipindah.
 *
 * Sumber daftar objek adalah MinIO sendiri (listUnder prefix lama) — BUKAN
 * cache BEPM — supaya rename beruntun tetap benar. Di dalam satu bucket, tiap
 * objek cukup Storage::move (copy+delete server-side). Setelah objek pindah,
 * path `file` dokumen di BEPM ikut di-PATCH agar daftar file (bersumber dari
 * BEPM) mengikuti key baru. Kegagalan satu objek/dokumen di-log dan tidak
 * menghentikan sisanya; perubahan folder tetap diterapkan lalu kunci dilepas.
 */
class MoveProjectFilesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    /**
     * @param  array{name?: string, parent_id?: ?int}|null  $folderUpdate
     */
    public function __construct(
        public readonly int $projectId,
        public readonly string $oldPrefix,
        public readonly string $newPrefix,
        public readonly int $folderId,
        public readonly ?array $folderUpdate = null,
    ) {}

    public function handle(ProjectFileStorage $storage, ProjectCache $cache, ProjectWriter $writer): void
    {
        foreach ($storage->listUnder($this->oldPrefix) as $key) {
            $newKey = $this->newPrefix.substr($key, strlen($this->oldPrefix));

            if ($newKey === $key) {
                continue;
            }

            try {
                $storage->move($key, $newKey);
            } catch (\Throwable $e) {
                Log::warning('MoveProjectFilesJob: move objek gagal', [
                    'from' => $key, 'to' => $newKey, 'error' => $e->getMessage(),
                ]);
            }
        }

        $this->syncBepmPaths($cache, $writer);
        $cache->flushDocs($this->projectId);

        $this->finalizeFolder();
    }

    /**
     * Sesuaikan path `file` dokumen BEPM mengikuti prefix baru. Daftar dokumen
     * dibaca dari BEPM (key lama); tiap dokumen di bawah prefix lama di-PATCH
     * ke key baru. Kegagalan satu dokumen di-log dan tidak menghentikan sisanya.
     */
    private function syncBepmPaths(ProjectCache $cache, ProjectWriter $writer): void
    {
        foreach ($cache->documentsFor($this->projectId) as $doc) {
            $key = $this->keyFromUrl((string) data_get($doc, 'files.url', ''));

            if (! str_starts_with($key, $this->oldPrefix)) {
                continue;
            }

            $newKey = $this->newPrefix.substr($key, strlen($this->oldPrefix));
            $result = $writer->updateDoc((int) $doc['id'], ['file' => $newKey]);

            if (! $result['ok']) {
                Log::warning('MoveProjectFilesJob: update path BEPM gagal, diserahkan ke background', [
                    'doc_id' => $doc['id'] ?? null, 'to' => $newKey,
                ]);
                SyncProjectDocPathJob::dispatch($this->projectId, (int) $doc['id'], $newKey);
            }
        }
    }

    /**
     * Object key MinIO dari `files.url` BEPM (ter-encode, bisa URL penuh).
     */
    private function keyFromUrl(string $url): string
    {
        $decoded = rawurldecode($url);

        if (str_contains($decoded, '/storage/')) {
            $decoded = Str::after($decoded, '/storage/');
        }

        return ltrim($decoded, '/');
    }

    public function failed(?\Throwable $exception): void
    {
        ProjectFolder::query()->whereKey($this->folderId)->update(['status' => null]);

        Log::error('MoveProjectFilesJob gagal total', [
            'project_id' => $this->projectId,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function finalizeFolder(): void
    {
        $folder = ProjectFolder::query()->find($this->folderId);

        if ($folder === null) {
            return;
        }

        if ($this->folderUpdate !== null) {
            $folder->fill($this->folderUpdate);
        }

        $folder->status = null;
        $folder->save();
    }
}
