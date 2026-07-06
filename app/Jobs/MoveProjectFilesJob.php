<?php

namespace App\Jobs;

use App\Models\ProjectFolder;
use App\Services\ProjectFileStorage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Pindahkan seluruh objek MinIO sebuah folder saat folder di-rename/dipindah.
 *
 * Sumber daftar objek adalah MinIO sendiri (listUnder prefix lama) — BUKAN
 * cache BEPM — supaya rename beruntun tetap benar (BEPM di-hold, belum punya
 * endpoint update path). Di dalam satu bucket, tiap objek cukup Storage::move
 * (copy+delete server-side). Kegagalan satu objek di-log dan tidak
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

    public function handle(ProjectFileStorage $storage): void
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

        // TODO(BEPM): saat endpoint update path dokumen tersedia, perbarui
        // admin-docs agar daftar file (bersumber dari BEPM) mengikuti key baru.

        $this->finalizeFolder();
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
