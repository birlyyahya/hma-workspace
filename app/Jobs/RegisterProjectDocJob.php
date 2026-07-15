<?php

namespace App\Jobs;

use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registrasikan dokumen ke BEPM setelah objek sudah aman di MinIO, dipakai saat
 * registrasi inline di endpoint complete gagal karena kegagalan sementara
 * (timeout/5xx).
 *
 * MinIO adalah sumber kebenaran — objek TIDAK dihapus. Idempotent: sebelum POST
 * dicek apakah object key sudah terdaftar (menangani kasus timeout padahal BEPM
 * sebenarnya sudah membuat dokumen) agar tidak terjadi duplikat. Melempar saat
 * belum sukses supaya queue menjadwalkan retry.
 */
class RegisterProjectDocJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $backoff = 30;

    /**
     * @param  array{title: string, admin_doc_category_id: int, filename: string, original_name: string, keyword: array<int, string>}  $payload
     */
    public function __construct(
        public readonly int $projectId,
        public readonly array $payload,
    ) {}

    public function handle(ProjectCache $cache, ProjectWriter $writer): void
    {
        $key = (string) ($this->payload['filename'] ?? '');

        $cache->flushDocs($this->projectId);

        foreach ($cache->documentsFor($this->projectId) as $doc) {
            if ($this->keyFromUrl((string) data_get($doc, 'files.url', '')) === $key) {
                return;
            }
        }

        $result = $writer->registerDocument($this->projectId, $this->payload);

        if (! $result['ok']) {
            Log::warning('RegisterProjectDocJob: registrasi BEPM belum sukses, dijadwalkan retry', [
                'project_id' => $this->projectId, 'key' => $key, 'status' => $result['status'],
            ]);

            throw new \RuntimeException("Registrasi dokumen BEPM gagal untuk {$key}");
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('RegisterProjectDocJob gagal total setelah retry — objek MinIO ada tapi belum terdaftar', [
            'project_id' => $this->projectId,
            'key' => $this->payload['filename'] ?? null,
            'error' => $exception?->getMessage(),
        ]);
    }

    private function keyFromUrl(string $url): string
    {
        $decoded = rawurldecode($url);

        if (str_contains($decoded, '/storage/')) {
            $decoded = Str::after($decoded, '/storage/');
        }

        return ltrim($decoded, '/');
    }
}
