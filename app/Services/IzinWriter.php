<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Log;

/**
 * Operasi WRITE (POST/PUT/DELETE) ke DARBE untuk domain Izin/SPD.
 *
 * Setiap method mengembalikan struktur konsisten dan TIDAK pernah throw:
 *   ['ok' => bool, 'body' => array, 'status' => ?int, 'error' => ?string]
 * - ok     : write berhasil secara logis (caller tampilkan UI sukses).
 * - body   : payload JSON dari respons (atau []) — caller pakai untuk pesan error/data.
 * - status : HTTP status code (atau null bila exception).
 * - error  : pesan exception (atau null) — membedakan kegagalan transport dari kegagalan logis.
 *
 * Cache di-invalidate (IzinCache::flush) otomatis saat ok=true (flush global,
 * meniru DarWriter — write izin/SPD jarang sehingga churn cache kecil).
 *
 * Timeout default 10s (payload izin kecil). SPD multipart pakai 60s karena
 * mengandung upload lampiran.
 */
class IzinWriter
{
    use MakesExternalRequests;

    public function __construct(
        private readonly string $apiBase,
        private readonly IzinCache $cache,
    ) {}

    /**
     * Ajukan izin baru. NON-idempotent — double submit = izin duplikat.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createIzin(array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)
                ->post($this->apiBase.'/global/izin/create-izin-saya', $payload);

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'izin',
                'event' => 'created',
                'description' => 'Mengajukan izin baru #'.($body['data']['id'] ?? null),
                'properties' => [
                    'jenis' => $payload['jenis_izin'] ?? $payload['jenis'] ?? null,
                    'payload' => $payload,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createIzin', $e);
        }
    }

    /**
     * Buat atau perbarui SPD (multipart, timeout diperpanjang untuk upload lampiran).
     * - Tanpa $id → POST create-spd (NON-idempotent: double submit = SPD duplikat).
     * - Dengan $id → POST update-spd/{id} dengan _method=PUT (idempotent).
     *
     * @param  array<string, mixed>  $payload
     * @param  array{contents: string, name: string}|null  $file
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function saveSpd(?int $id, array $payload, ?array $file = null): array
    {
        try {
            $request = $this->externalWrite(timeout: 60)->asMultipart();

            if ($file !== null) {
                $request = $request->attach('attachment', $file['contents'], $file['name']);
            }

            if ($id !== null) {
                $response = $request->post($this->apiBase.'/global/dar/activity/update-spd/'.$id, [
                    '_method' => 'PUT',
                    ...$payload,
                ]);
            } else {
                $response = $request->post($this->apiBase.'/global/dar/activity/create-spd', $payload);
            }

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'izin',
                'event' => $id !== null ? 'updated' : 'created',
                'description' => $id !== null ? "Memperbarui SPD #{$id}" : 'Membuat SPD baru',
                'properties' => [
                    'id' => $id,
                    'payload' => $payload,
                    'attachment' => $file['name'] ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('saveSpd', $e, ['id' => $id]);
        }
    }

    /**
     * Hapus SPD. Idempotent — 2xx ATAU body.success dianggap sukses (404 ditoleransi).
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteSpd(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)
                ->delete($this->apiBase.'/global/dar/activity/delete-spd/'.$id);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'izin',
                'event' => 'deleted',
                'description' => "Menghapus SPD #{$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteSpd', $e, ['id' => $id]);
        }
    }

    /**
     * Perbarui tanda tangan user (data di BE izin). Idempotent — real value replace.
     * Sukses = body.success. Invalidasi cache tanda tangan user saat berhasil.
     * Payload base64 sengaja TIDAK dicatat di properties activity (terlalu besar).
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateSignature(string $username, string $base64): array
    {
        try {
            $response = $this->externalWrite(timeout: 15)
                ->post($this->apiBase.'/global/user/update-signature/'.$username, [
                    'base64' => $base64,
                ]);

            $body = (array) $response->json();
            $ok = (bool) ($body['success'] ?? false);

            if ($ok) {
                $this->cache->flushUser($username);
                $this->logActivity([
                    'name' => 'izin',
                    'event' => 'updated',
                    'description' => "Memperbarui tanda tangan ({$username})",
                    'properties' => ['username' => $username],
                ]);
            } else {
                Log::warning('IzinWriter updateSignature non-success', ['status' => $response->status(), 'body' => $body]);
            }

            return ['ok' => $ok, 'body' => $body, 'status' => $response->status(), 'error' => null];
        } catch (\Throwable $e) {
            return $this->fail('updateSignature', $e, ['username' => $username]);
        }
    }

    /**
     * Bangun struktur hasil; flush cache Izin & catat activity saat sukses.
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
            Log::warning('IzinWriter write non-success', ['status' => $status, 'body' => $body]);
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
        Log::error("IzinWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
