<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Log;

/**
 * Operasi WRITE (POST/PUT/DELETE) ke API eksternal untuk domain Inventaris.
 *
 * Setiap method mengembalikan struktur konsisten dan TIDAK pernah throw:
 *   ['ok' => bool, 'body' => array, 'status' => ?int, 'error' => ?string]
 * - ok     : write berhasil secara logis (caller tampilkan UI sukses).
 * - body   : payload JSON dari respons (atau []) — caller pakai untuk pesan error/data.
 * - status : HTTP status code (atau null bila exception).
 * - error  : pesan exception (atau null) — membedakan kegagalan transport dari kegagalan logis.
 *
 * Cache di-invalidate (InventarisCache::flush) otomatis saat ok=true —
 * flush global meniru IzinWriter karena write inventaris jarang.
 *
 * Path endpoint mengikuti konvensi awal /inventaris/* — sesuaikan saat
 * kontrak API final.
 */
class InventarisWriter
{
    use MakesExternalRequests;

    public function __construct(
        private readonly string $apiBase,
        private readonly InventarisCache $cache,
    ) {}

    /**
     * Tambah item inventaris baru. NON-idempotent — double submit = item duplikat.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createItem(array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)
                ->post($this->apiBase.'/inventaris/create', $payload);

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'inventaris',
                'event' => 'created',
                'description' => 'Menambah item inventaris baru',
                'properties' => ['nama' => $payload['nama'] ?? null],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createItem', $e);
        }
    }

    /**
     * Perbarui item inventaris. Idempotent — real value replace.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateItem(int $id, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)
                ->put($this->apiBase.'/inventaris/update/'.$id, $payload);

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'inventaris',
                'event' => 'updated',
                'description' => "Memperbarui item inventaris #{$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateItem', $e, ['id' => $id]);
        }
    }

    /**
     * Hapus item inventaris. Idempotent — 2xx ATAU body.success dianggap sukses
     * (404 ditoleransi).
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteItem(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)
                ->delete($this->apiBase.'/inventaris/delete/'.$id);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'inventaris',
                'event' => 'deleted',
                'description' => "Menghapus item inventaris #{$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteItem', $e, ['id' => $id]);
        }
    }

    /**
     * Bangun struktur hasil; flush cache Inventaris & catat activity saat sukses.
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
            Log::warning('InventarisWriter write non-success', ['status' => $status, 'body' => $body]);
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
        Log::error("InventarisWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
