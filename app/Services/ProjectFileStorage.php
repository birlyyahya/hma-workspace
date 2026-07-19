<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            $client = $this->signingClient();
            $expires = '+'.((int) config('uploads.project_files.presign_ttl')).' minutes';
            $urls = [];

            foreach ($partNumbers as $partNumber) {
                $command = $client->getCommand('UploadPart', [
                    'Bucket' => $this->signingBucket(),
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
     *
     * $downloadName menyetel Content-Disposition "inline; filename=..." pada
     * respons MinIO: nama file yang diterima user mengikuti nama tampilan
     * (title), BUKAN basename object key — key tidak pernah di-rename, jadi
     * nama unduhan harus datang dari metadata. "inline" agar preview iframe/
     * img tetap jalan; browser tetap memakai filename saat menyimpan.
     */
    public function presignedGetUrl(string $key, int $ttlMinutes = 10, ?string $downloadName = null): string
    {
        try {
            $params = [
                'Bucket' => $this->signingBucket(),
                'Key' => $key,
            ];

            if ($downloadName !== null && $downloadName !== '') {
                $ascii = str_replace(['"', '\\'], '', Str::ascii($downloadName));
                $params['ResponseContentDisposition'] =
                    "inline; filename=\"{$ascii}\"; filename*=UTF-8''".rawurlencode($downloadName);
            }

            $command = $this->signingClient()->getCommand('GetObject', $params);

            return (string) $this->signingClient()
                ->createPresignedRequest($command, "+{$ttlMinutes} minutes")
                ->getUri();
        } catch (\Throwable $e) {
            throw $this->wrap('presignedGetUrl', $e, ['key' => $key]);
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
     * Object key sebenarnya di MinIO di bawah sebuah prefix (rekursif).
     * Sumber otoritatif untuk rekonsiliasi — TIDAK bergantung pada cache BEPM.
     *
     * Catatan: operasi server-side di service ini sengaja menghindari
     * HeadObject — HEAD bertanda tangan ditolak 403 oleh reverse proxy publik
     * MinIO (storage.hanatekindo.com) yang dipakai saat pengembangan lokal.
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
     * Ukuran objek (bytes) per key di bawah prefix. BEPM tidak pernah tahu
     * ukuran file (byte diupload langsung browser → MinIO dan selalu
     * melaporkan "0 KB"), jadi ukuran diambil dari MinIO lewat sini.
     *
     * @return array<string, int> map object key => bytes
     */
    public function sizesUnder(string $prefix): array
    {
        try {
            $sizes = [];
            $params = ['Bucket' => $this->bucket(), 'Prefix' => rtrim($prefix, '/').'/'];

            do {
                $result = $this->client()->listObjectsV2($params);

                foreach ($result['Contents'] ?? [] as $object) {
                    $sizes[(string) $object['Key']] = (int) $object['Size'];
                }

                $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
            } while (($result['IsTruncated'] ?? false) && $params['ContinuationToken'] !== null);

            return $sizes;
        } catch (\Throwable $e) {
            throw $this->wrap('sizesUnder', $e, ['prefix' => $prefix]);
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

    /**
     * Klien endpoint INTERNAL — untuk semua operasi server-side (create/complete
     * multipart, copy/move, exists, delete, list).
     */
    private function client(): S3Client
    {
        return $this->adapterFor((string) config('uploads.project_files.disk'))->getClient();
    }

    private function bucket(): string
    {
        return (string) $this->adapterFor((string) config('uploads.project_files.disk'))->getConfig()['bucket'];
    }

    /**
     * Klien endpoint PUBLIK — HANYA untuk menandatangani presigned URL yang
     * diambil browser. Penandatanganan murni kripto lokal (tanpa jaringan),
     * jadi server tak perlu bisa menjangkau endpoint publik untuk sign.
     */
    private function signingClient(): S3Client
    {
        return $this->adapterFor((string) config('uploads.project_files.signing_disk'))->getClient();
    }

    private function signingBucket(): string
    {
        return (string) $this->adapterFor((string) config('uploads.project_files.signing_disk'))->getConfig()['bucket'];
    }

    private function adapterFor(string $disk): AwsS3V3Adapter
    {
        $instance = Storage::disk($disk);

        if (! $instance instanceof AwsS3V3Adapter) {
            throw new \RuntimeException("Disk '{$disk}' bukan disk S3 — periksa config filesystems & uploads.project_files.");
        }

        return $instance;
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
