<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Log;

/**
 * Operasi WRITE (POST/PUT/DELETE) ke DARBE untuk domain DAR.
 *
 * Setiap method mengembalikan struktur konsisten dan TIDAK pernah throw:
 *   ['ok' => bool, 'body' => array, 'status' => ?int, 'error' => ?string]
 * - ok     : write berhasil secara logis (caller tampilkan UI sukses).
 * - body   : payload JSON dari respons (atau []) — caller pakai untuk pesan error/data.
 * - status : HTTP status code (atau null bila exception).
 * - error  : pesan exception (atau null) — membedakan kegagalan transport dari kegagalan logis.
 *
 * Cache di-invalidate (DarCache::flush) otomatis saat ok=true.
 */
class DarWriter
{
    use MakesExternalRequests;

    public function __construct(
        private readonly string $apiBase,
        private readonly DarCache $cache,
    ) {}

    /**
     * Update aktivitas DAR (#4). Idempotent — POST dengan _method=PUT (pola existing).
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateActivity(int $id, array $payload): array
    {
        try {
            $response = $this->externalWrite()
                ->post($this->apiBase."/global/dar/update/{$id}", [
                    '_method' => 'PUT',
                    ...$payload,
                ]);

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'dar',
                'event' => 'updated',
                'description' => "Memperbarui aktivitas DAR #{$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateActivity', $e, ['id' => $id]);
        }
    }

    /**
     * Update status aktivitas (#5 markAsDone, #12 toggleTodo). Idempotent — real PUT.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateStatus(int $id, int $status): array
    {
        try {
            $response = $this->externalWrite()
                ->put($this->apiBase."/global/dar/activity/{$id}/status", [
                    'status' => $status,
                ]);

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'dar',
                'event' => 'updated',
                'description' => "Mengubah status aktivitas DAR #{$id}",
                'properties' => ['id' => $id, 'status' => $status],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateStatus', $e, ['id' => $id, 'status' => $status]);
        }
    }

    /**
     * Tambah komentar (#6). NON-idempotent — multipart upload, timeout diperpanjang.
     *
     * @param  array<int, array{contents: string, name: string}>  $files
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function addComment(int $activityId, int $userId, ?string $body, array $files = []): array
    {
        try {
            $request = $this->externalWrite(timeout: 30)->asMultipart();

            foreach ($files as $file) {
                $request = $request->attach('files[]', $file['contents'], $file['name']);
            }

            $response = $request->post($this->apiBase.'/global/dar/activity/create-comment', [
                'activity_id' => $activityId,
                'user_id' => $userId,
                'body' => $body,
            ]);

            $payload = (array) $response->json();

            return $this->result((bool) ($payload['success'] ?? false), $payload, $response->status(), [
                'name' => 'dar',
                'event' => 'created',
                'description' => "Menambah komentar pada aktivitas DAR #{$activityId}",
                'properties' => ['activity_id' => $activityId],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('addComment', $e, ['activity_id' => $activityId]);
        }
    }

    /**
     * Update komentar (#7). Idempotent — real PUT.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateComment(int $commentId, string $body): array
    {
        try {
            $response = $this->externalWrite()
                ->put($this->apiBase."/global/dar/activity/update-comment/{$commentId}", [
                    'body' => $body,
                ]);

            $payload = (array) $response->json();

            return $this->result((bool) ($payload['success'] ?? false), $payload, $response->status(), [
                'name' => 'dar',
                'event' => 'updated',
                'description' => "Memperbarui komentar DAR #{$commentId}",
                'properties' => ['comment_id' => $commentId],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateComment', $e, ['comment_id' => $commentId]);
        }
    }

    /**
     * Hapus komentar (#8). Idempotent — DELETE.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteComment(int $commentId): array
    {
        try {
            $response = $this->externalWrite()
                ->delete($this->apiBase."/global/dar/activity/delete-comment/{$commentId}");

            $body = (array) $response->json();

            return $this->result((bool) ($body['success'] ?? false), $body, $response->status(), [
                'name' => 'dar',
                'event' => 'deleted',
                'description' => "Menghapus komentar DAR #{$commentId}",
                'properties' => ['comment_id' => $commentId],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteComment', $e, ['comment_id' => $commentId]);
        }
    }

    /**
     * Hapus aktivitas DAR (#11). Idempotent — DELETE. 2xx ATAU body.success dianggap sukses.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteActivity(int $id): array
    {
        try {
            $response = $this->externalWrite()
                ->delete($this->apiBase."/global/dar/activity/{$id}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'dar',
                'event' => 'deleted',
                'description' => "Menghapus aktivitas DAR #{$id}",
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteActivity', $e, ['id' => $id]);
        }
    }

    /**
     * Bangun struktur hasil; flush cache DAR & catat activity saat sukses.
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
            Log::warning('DarWriter write non-success', ['status' => $status, 'body' => $body]);
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
        Log::error("DarWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
