<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Control plane S3 Multipart Upload untuk project files di MinIO.
 *
 * Byte file TIDAK pernah melewati service ini — browser meng-upload langsung
 * ke MinIO memakai presigned URL yang ditandatangani di sini. Client & bucket
 * diambil dari disk filesystem terkonfigurasi (config uploads.project_files.disk),
 * jangan pernah membangun S3Client manual dengan kredensial hardcode.
 *
 * Semua method melempar RuntimeException dengan konteks jelas saat operasi
 * S3 gagal; caller yang memutuskan penanganan (abort, rollback, dsb).
 */
class ProjectFileStorage
{
    /**
     * Mulai multipart upload; mengembalikan uploadId dari MinIO.
     */
    public function initiateMultipart(string $key, string $mime): string
    {
        try {
            $result = $this->client()->createMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'ContentType' => $mime,
            ]);

            return (string) $result['UploadId'];
        } catch (\Throwable $e) {
            throw $this->wrap('initiateMultipart', $e, ['key' => $key]);
        }
    }

    /**
     * Presigned URL UploadPart untuk sekumpulan part number (batch).
     *
     * @param  array<int, int>  $partNumbers
     * @return array<int, string> map part number => presigned URL
     */
    public function signParts(string $key, string $uploadId, array $partNumbers): array
    {
        try {
            $client = $this->client();
            $expires = '+'.((int) config('uploads.project_files.presign_ttl')).' minutes';
            $urls = [];

            foreach ($partNumbers as $partNumber) {
                $command = $client->getCommand('UploadPart', [
                    'Bucket' => $this->bucket(),
                    'Key' => $key,
                    'UploadId' => $uploadId,
                    'PartNumber' => (int) $partNumber,
                ]);

                $urls[(int) $partNumber] = (string) $client
                    ->createPresignedRequest($command, $expires)
                    ->getUri();
            }

            return $urls;
        } catch (\Throwable $e) {
            throw $this->wrap('signParts', $e, ['key' => $key, 'upload_id' => $uploadId]);
        }
    }

    /**
     * Selesaikan multipart upload.
     *
     * @param  array<int, array{PartNumber: int, ETag: string}>  $parts
     */
    public function completeMultipart(string $key, string $uploadId, array $parts): void
    {
        try {
            usort($parts, fn (array $a, array $b) => $a['PartNumber'] <=> $b['PartNumber']);

            $this->client()->completeMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => $parts],
            ]);
        } catch (\Throwable $e) {
            throw $this->wrap('completeMultipart', $e, ['key' => $key, 'upload_id' => $uploadId]);
        }
    }

    public function abortMultipart(string $key, string $uploadId): void
    {
        try {
            $this->client()->abortMultipartUpload([
                'Bucket' => $this->bucket(),
                'Key' => $key,
                'UploadId' => $uploadId,
            ]);
        } catch (\Throwable $e) {
            throw $this->wrap('abortMultipart', $e, ['key' => $key, 'upload_id' => $uploadId]);
        }
    }

    /**
     * Presigned GET URL untuk preview/download.
     */
    public function presignedGetUrl(string $key, int $ttlMinutes = 10): string
    {
        try {
            $command = $this->client()->getCommand('GetObject', [
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ]);

            return (string) $this->client()
                ->createPresignedRequest($command, "+{$ttlMinutes} minutes")
                ->getUri();
        } catch (\Throwable $e) {
            throw $this->wrap('presignedGetUrl', $e, ['key' => $key]);
        }
    }

    /**
     * Server-side copy — bagian dari urutan rename/move yang aman-gagal.
     */
    public function copyObject(string $fromKey, string $toKey): void
    {
        try {
            $this->client()->copyObject([
                'Bucket' => $this->bucket(),
                'Key' => $toKey,
                'CopySource' => rawurlencode($this->bucket().'/'.$fromKey),
            ]);
        } catch (\Throwable $e) {
            throw $this->wrap('copyObject', $e, ['from' => $fromKey, 'to' => $toKey]);
        }
    }

    public function deleteObject(string $key): void
    {
        try {
            $this->client()->deleteObject([
                'Bucket' => $this->bucket(),
                'Key' => $key,
            ]);
        } catch (\Throwable $e) {
            throw $this->wrap('deleteObject', $e, ['key' => $key]);
        }
    }

    /**
     * Pindahkan objek dalam bucket yang sama (copy + delete di sisi server,
     * satu panggilan Flysystem). Melempar bila sumber tidak ada / gagal.
     */
    public function move(string $fromKey, string $toKey): void
    {
        try {
            Storage::disk((string) config('uploads.project_files.disk'))->move($fromKey, $toKey);
        } catch (\Throwable $e) {
            throw $this->wrap('move', $e, ['from' => $fromKey, 'to' => $toKey]);
        }
    }

    /**
     * Object key sebenarnya di MinIO di bawah sebuah prefix (rekursif).
     * Sumber otoritatif untuk operasi move — TIDAK bergantung pada cache BEPM.
     *
     * @return array<int, string>
     */
    public function listUnder(string $prefix): array
    {
        try {
            return Storage::disk((string) config('uploads.project_files.disk'))->allFiles(rtrim($prefix, '/'));
        } catch (\Throwable $e) {
            throw $this->wrap('listUnder', $e, ['prefix' => $prefix]);
        }
    }

    /**
     * Multipart upload menggantung yang lebih tua dari N jam.
     *
     * @return array<int, array{Key: string, UploadId: string, Initiated: \DateTimeInterface}>
     */
    public function listStaleMultipartUploads(int $olderThanHours): array
    {
        try {
            $threshold = now()->subHours($olderThanHours);
            $stale = [];
            $params = ['Bucket' => $this->bucket()];

            do {
                $result = $this->client()->listMultipartUploads($params);

                foreach ($result['Uploads'] ?? [] as $upload) {
                    if ($upload['Initiated'] < $threshold) {
                        $stale[] = [
                            'Key' => (string) $upload['Key'],
                            'UploadId' => (string) $upload['UploadId'],
                            'Initiated' => $upload['Initiated'],
                        ];
                    }
                }

                $params['KeyMarker'] = $result['NextKeyMarker'] ?? null;
                $params['UploadIdMarker'] = $result['NextUploadIdMarker'] ?? null;
            } while (($result['IsTruncated'] ?? false) && $params['KeyMarker'] !== null);

            return $stale;
        } catch (\Throwable $e) {
            throw $this->wrap('listStaleMultipartUploads', $e, ['older_than_hours' => $olderThanHours]);
        }
    }

    /**
     * Abort semua multipart menggantung yang lebih tua dari N jam.
     * Kegagalan abort satu item di-log dan tidak menghentikan sisanya.
     *
     * @return int jumlah upload yang berhasil di-abort
     */
    public function abortStaleMultipartUploads(int $olderThanHours): int
    {
        $aborted = 0;

        foreach ($this->listStaleMultipartUploads($olderThanHours) as $upload) {
            try {
                $this->abortMultipart($upload['Key'], $upload['UploadId']);
                $aborted++;
            } catch (\Throwable $e) {
                Log::warning('ProjectFileStorage gagal abort multipart stale', [
                    'key' => $upload['Key'],
                    'upload_id' => $upload['UploadId'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $aborted;
    }

    private function client(): S3Client
    {
        return $this->adapter()->getClient();
    }

    private function bucket(): string
    {
        return (string) $this->adapter()->getConfig()['bucket'];
    }

    private function adapter(): AwsS3V3Adapter
    {
        $disk = Storage::disk((string) config('uploads.project_files.disk'));

        if (! $disk instanceof AwsS3V3Adapter) {
            throw new \RuntimeException('Disk project files bukan disk S3 — periksa config uploads.project_files.disk.');
        }

        return $disk;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function wrap(string $operation, \Throwable $e, array $context): \RuntimeException
    {
        Log::error("ProjectFileStorage::{$operation} gagal", [...$context, 'error' => $e->getMessage()]);

        return new \RuntimeException("Operasi storage {$operation} gagal: {$e->getMessage()}", 0, $e);
    }
}
