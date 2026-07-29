<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Operasi WRITE (POST/PUT) ke API Cash Advance untuk domain CA PL.
 *
 * Setiap method mengembalikan struktur konsisten dan TIDAK pernah throw:
 *   ['ok' => bool, 'body' => array, 'status' => ?int, 'error' => ?string]
 * - ok     : write berhasil secara logis (caller tampilkan UI sukses).
 * - body   : payload JSON dari respons (atau []) — caller pakai untuk pesan error/data.
 * - status : HTTP status code (atau null bila exception).
 * - error  : pesan exception (atau null) — membedakan kegagalan transport dari kegagalan logis.
 *
 * Cache CA di-invalidate (CaCache::flush) otomatis saat ok=true.
 */
class CaWriter
{
    use MakesExternalRequests;

    public function __construct(
        private readonly string $apiBase,
        private readonly CaCache $cache,
    ) {}

    /**
     * Catat transaksi pengeluaran pada dompet CA PL.
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function topupCaPL(array $payload)
    {
        try {
            $response = $this->sendTransaction($this->apiBase.'/esekusi-topup-wallet-pl', $payload);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Topup CA PL {$body['data']['kode_ca']}",
                'properties' => ['total_penerimaan' => $body['data']['total_penerimaan']],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('topupCaPL', $e, ['user_id' => $payload['user_id']]);
        }
    }

    /**
     * Catat transaksi pengeluaran pada dompet CA PL.
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createTransactionCaPl(string $kode, array $payload): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase."/ca/{$kode}/transaksi", $payload);
            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Mencatat pengeluaran CA PL {$kode}",
                'properties' => ['kode' => $kode],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createTransactionCaPl', $e, ['kode' => $kode]);
        }
    }

    /**
     * Perbarui transaksi pengeluaran CA PL.
     * NON-idempotent — POST + _method=PUT (bukti opsional, hanya dikirim bila diganti).
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateTransactionCaPl(string $kode, int $id, array $payload): array
    {
        try {
            $response = $this->sendTransaction(
                $this->apiBase."/ca/{$kode}/transaksi/{$id}",
                [...$payload, '_method' => 'PUT'],
            );

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => "Memperbarui transaksi CA PL {$kode} #{$id}",
                'properties' => ['kode' => $kode, 'id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateTransactionCaPl', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    public function deleteTransactionCaPL(string $kode, int $id)
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase."/ca/{$kode}/transaksi/{$id}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => "Delete transaksi CA PL {$kode} #{$id}",
                'properties' => ['kode' => $kode, 'id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteTransaction', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * Buat dompet kegiatan (wallet untuk kegiatan tertentu dalam CA).
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createDompetKegiatan(array $payload): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase.'/topup-wallet-kegiatan', $payload);
            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            $kodeCa = $body['data']['kode_ca'] ?? 'unknown';

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Buat dompet kegiatan {$kodeCa}",
                'properties' => ['user_id' => $payload['user_id'] ?? null],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createDompetKegiatan', $e, ['user_id' => $payload['user_id'] ?? null]);
        }
    }

    /**
     * Catat transaksi (masuk/keluar) pada dompet kegiatan.
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createTransaksiKegiatan(string $kode, array $payload): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase."/kegiatan/{$kode}/transaksi", $payload);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Mencatat transaksi dompet kegiatan {$kode}",
                'properties' => ['kode' => $kode],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createTransaksiKegiatan', $e, ['kode' => $kode]);
        }
    }

    /**
     * Perbarui transaksi dompet kegiatan.
     * NON-idempotent — POST + _method=PUT (bukti opsional, hanya dikirim bila diganti).
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateTransaksiKegiatan(string $kode, int $id, array $payload): array
    {
        try {
            $response = $this->sendTransaction(
                $this->apiBase."/kegiatan/{$kode}/transaksi/{$id}",
                [...$payload, '_method' => 'PUT'],
            );

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => "Memperbarui transaksi dompet kegiatan {$kode} #{$id}",
                'properties' => ['kode' => $kode, 'id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateTransaksiKegiatan', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteTransaksiKegiatan(string $kode, int $id): array
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase."/kegiatan/{$kode}/transaksi/{$id}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => "Delete transaksi dompet kegiatan {$kode} #{$id}",
                'properties' => ['kode' => $kode, 'id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteTransaksiKegiatan', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * Tutup dompet kegiatan (settlement). Bukti setor balik opsional.
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function closeKegiatan(array $payload): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase.'/close-kegiatan', $payload, fileField: 'bukti_setor');

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);
            $id = $payload['id'] ?? null;

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => "Tutup dompet kegiatan id={$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('closeKegiatan', $e, ['id' => $payload['id'] ?? null]);
        }
    }

    public function topup(array $payload)
    {
        try {
            $response = $this->externalWrite()->post($this->apiBase.'/esekusi-topup-wallet-pl', $payload);

            $body = $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "topup ca PL {$payload['tahun_anggaran']}",
                'properties' => ['payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('topupCaPL', $e, ['user_id' => $payload['user_id']]);
        }
    }

    /**
     * Kirim payload transaksi sebagai multipart; lampirkan bukti bila tersedia.
     * Bukti berbentuk ['contents' => string, 'name' => string] agar caller
     * tidak perlu membocorkan TemporaryUploadedFile ke Service.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendTransaction(string $url, array $payload, string $fileField = 'bukti'): Response
    {
        $file = $payload[$fileField] ?? null;
        unset($payload[$fileField]);

        $request = $this->externalWrite(timeout: 30)->asMultipart();

        if (is_array($file) && isset($file['contents'], $file['name'])) {
            $request = $request->attach($fileField, $file['contents'], $file['name']);
        }

        return $request->post($url, $payload);
    }

    /**
     * Bangun struktur hasil; flush cache CA & catat activity saat sukses.
     *
     * @param  array<string, mixed>  $body
     * @param  array{name: string, event: string, description: string, properties?: array<string, mixed>}|null  $log
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function result(bool $ok, array $body, ?int $status, ?array $log = null): array
    {
        if ($ok) {
            $this->cache->flush();

            if ($log !== null) {
                $this->logActivity($log);
            }
        } else {
            Log::warning('CaWriter write non-success', ['status' => $status, 'body' => $body]);
        }

        return ['ok' => $ok, 'body' => $body, 'status' => $status, 'error' => null];
    }

    /**
     * Catat activity untuk operasi domain eksternal (tanpa subject Eloquent lokal).
     *
     * @param  array{name: string, event: string, description: string, properties?: array<string, mixed>}  $log
     */
    private function logActivity(array $log): void
    {
        activity($log['name'])
            ->event($log['event'])
            ->withProperties($log['properties'] ?? [])
            ->log($log['description']);
    }

    /**
     * Struktur hasil untuk kegagalan transport (exception).
     *
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function fail(string $method, \Throwable $e, array $context = []): array
    {
        Log::error("CaWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
