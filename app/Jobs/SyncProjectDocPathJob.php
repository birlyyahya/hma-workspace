<?php

namespace App\Jobs;

use App\Services\ProjectWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Selaraskan path `file` sebuah dokumen BEPM dengan object key MinIO terbaru
 * setelah objek dipindah/rename di storage.
 *
 * Jaring pengaman saat PATCH inline gagal/terputus: MinIO adalah sumber
 * kebenaran, jadi job ini hanya membuat BEPM menyusul. Idempotent — mengirim
 * key yang sama berulang kali aman — sehingga bisa diretry queue tanpa efek
 * samping. Melempar saat belum sukses agar queue menjadwalkan retry.
 */
class SyncProjectDocPathJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(
        public readonly int $projectId,
        public readonly int $docId,
        public readonly string $newKey,
    ) {}

    public function handle(ProjectWriter $writer): void
    {
        $result = $writer->updateDoc($this->docId, ['file' => $this->newKey], $this->projectId);

        if (! $result['ok']) {
            Log::warning('SyncProjectDocPathJob: update path BEPM belum sukses, dijadwalkan retry', [
                'doc_id' => $this->docId, 'to' => $this->newKey, 'status' => $result['status'],
            ]);

            throw new \RuntimeException("Update path BEPM gagal untuk dokumen #{$this->docId}");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('SyncProjectDocPathJob gagal total setelah retry — perlu rekonsiliasi manual', [
            'project_id' => $this->projectId,
            'doc_id' => $this->docId,
            'to' => $this->newKey,
            'error' => $exception?->getMessage(),
        ]);
    }
}
