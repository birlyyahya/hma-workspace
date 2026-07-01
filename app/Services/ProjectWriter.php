<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Log;

/**
 * Operasi WRITE (POST/PATCH/DELETE) ke BEPM untuk domain Project.
 *
 * Setiap method mengembalikan struktur konsisten dan TIDAK pernah throw:
 *   ['ok' => bool, 'body' => array, 'status' => ?int, 'error' => ?string]
 * - ok     : write berhasil secara logis (caller tampilkan UI sukses).
 * - body   : payload JSON dari respons (atau []) — caller pakai untuk pesan error/data.
 * - status : HTTP status code (atau null bila exception).
 * - error  : pesan exception (atau null) — membedakan kegagalan transport dari kegagalan logis.
 *
 * BEPM TIDAK memakai token (mengikuti ProjectCache & seluruh pemanggil view existing).
 * Deteksi sukses berbeda per-endpoint: sebagian BEPM mengembalikan HTTP 200 dengan
 * field `status` di body yang mencerminkan hasil sebenarnya (lihat apiSucceeded di
 * project-edit), sebagian lain cukup HTTP 2xx. Tiap method meniru pengecekan aslinya.
 *
 * Cache di-invalidate lewat ProjectCache (flush* sesuai domain) saat ok=true.
 */
class ProjectWriter
{
    use MakesExternalRequests;

    public function __construct(
        private readonly string $apiBase,
        private readonly ProjectCache $cache,
    ) {}

    // ---------------------------------------------------------------- Projects

    /**
     * Buat project baru. NON-idempotent. Sukses = body.status === 201.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createProject(array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->post($this->apiBase.'/projects', $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('createProject', $e);
        }
    }

    /**
     * Perbarui project. Idempotent. Sukses = body.status in [200,201] atau HTTP 2xx.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateProject(int $id, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->patch($this->apiBase.'/projects/'.$id, $payload);
            $body = (array) $response->json();

            return $this->result($this->statusSucceeded($response->status(), $body), $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('updateProject', $e, ['id' => $id]);
        }
    }

    /**
     * Hapus project. Idempotent. Sukses = HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteProject(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/projects/'.$id);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('deleteProject', $e, ['id' => $id]);
        }
    }

    // --------------------------------------------------------------- Timelines

    /**
     * Buat timeline. NON-idempotent. Sukses = body.status === 201.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createTimeline(array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->post($this->apiBase.'/timelines', $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('createTimeline', $e);
        }
    }

    /**
     * Perbarui timeline. Idempotent. Sukses = HTTP 2xx.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateTimeline(int $id, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->patch($this->apiBase.'/timelines/'.$id, $payload);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('updateTimeline', $e, ['id' => $id]);
        }
    }

    /**
     * Hapus timeline. Idempotent. Sukses = body.status === 200.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteTimeline(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/timelines/'.$id);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), fn () => $this->cache->flushProjects());
        } catch (\Throwable $e) {
            return $this->fail('deleteTimeline', $e, ['id' => $id]);
        }
    }

    // ------------------------------------------------------------------- Teams

    /**
     * Tambah anggota tim internal. NON-idempotent. Sukses = body.status === 201.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createTeam(int $projectId, int $userId): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->post($this->apiBase.'/project-teams', [
                'project_id' => $projectId,
                'user_id' => $userId,
            ]);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushUser($userId));
        } catch (\Throwable $e) {
            return $this->fail('createTeam', $e, ['project_id' => $projectId, 'user_id' => $userId]);
        }
    }

    /**
     * Hapus anggota tim internal. Idempotent. Sukses = body.status === 200.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteTeam(int $teamId, int $userId): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/project-teams/'.$teamId);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), fn () => $this->cache->flushUser($userId));
        } catch (\Throwable $e) {
            return $this->fail('deleteTeam', $e, ['team_id' => $teamId, 'user_id' => $userId]);
        }
    }

    // --------------------------------------------------------------- Companies

    /**
     * Buat perusahaan (multipart, letter_head opsional). Sukses = HTTP 2xx.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{contents: string, name: string}|null  $file
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createCompany(array $payload, ?array $file = null): array
    {
        return $this->saveCompany(null, $payload, $file);
    }

    /**
     * Perbarui perusahaan (multipart POST ke /companies/{id}, meniru pemanggil asli).
     *
     * @param  array<string, mixed>  $payload
     * @param  array{contents: string, name: string}|null  $file
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateCompany(int $id, array $payload, ?array $file = null): array
    {
        return $this->saveCompany($id, $payload, $file);
    }

    /**
     * Hapus perusahaan. Idempotent. Sukses = HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteCompany(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/companies/'.$id);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushCompanies());
        } catch (\Throwable $e) {
            return $this->fail('deleteCompany', $e, ['id' => $id]);
        }
    }

    // ---------------------------------------------------------------- Spectech

    /**
     * Hapus spektek (activity category). Sukses = HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteSpectechCategory(int $id, int $projectId): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/activity-categories/'.$id);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId));
        } catch (\Throwable $e) {
            return $this->fail('deleteSpectechCategory', $e, ['id' => $id, 'project_id' => $projectId]);
        }
    }

    /**
     * Simpan spektek massal. Sukses = HTTP 2xx.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function bulkSpectech(int $projectId, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 30)->post($this->apiBase.'/activity-categories/bulk', $payload);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId));
        } catch (\Throwable $e) {
            return $this->fail('bulkSpectech', $e, ['project_id' => $projectId]);
        }
    }

    /**
     * Impor spektek dari file excel (multipart). Sukses = HTTP 2xx.
     *
     * @param  array{contents: string, name: string}  $file
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function importSpectech(int $projectId, array $file): array
    {
        try {
            $response = $this->externalWrite(timeout: 30)->asMultipart()
                ->attach('file', $file['contents'], $file['name'])
                ->post($this->apiBase.'/activity-categories/import', ['project_id' => $projectId]);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId));
        } catch (\Throwable $e) {
            return $this->fail('importSpectech', $e, ['project_id' => $projectId]);
        }
    }

    // -------------------------------------------------------------------- Docs

    /**
     * Finalisasi unggahan dokumen admin (chunk sudah tersimpan di server).
     * Timeout diperpanjang. Sukses = body.status === 201.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function uploadDoc(array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 120)->post($this->apiBase.'/admin-docs', $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status());
        } catch (\Throwable $e) {
            return $this->fail('uploadDoc', $e);
        }
    }

    // ----------------------------------------------------------------- Helpers

    /**
     * Simpan/perbarui perusahaan lewat multipart POST (create tanpa id, edit dengan id).
     *
     * @param  array<string, mixed>  $payload
     * @param  array{contents: string, name: string}|null  $file
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function saveCompany(?int $id, array $payload, ?array $file): array
    {
        try {
            $request = $this->externalWrite(timeout: 30)->asMultipart();

            if ($file !== null) {
                $request = $request->attach('letter_head', $file['contents'], $file['name']);
            }

            $url = $this->apiBase.'/companies'.($id !== null ? '/'.$id : '');
            $response = $request->post($url, $payload);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushCompanies());
        } catch (\Throwable $e) {
            return $this->fail('saveCompany', $e, ['id' => $id]);
        }
    }

    /**
     * BEPM kadang membalas HTTP 200 walau gagal secara logis, menandai hasil lewat
     * field `status` di body (meniru apiSucceeded di project-edit).
     *
     * @param  array<string, mixed>  $body
     */
    private function statusSucceeded(?int $httpStatus, array $body): bool
    {
        $status = $body['status'] ?? null;

        if ($status === null) {
            return $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
        }

        return in_array((int) $status, [200, 201], true);
    }

    /**
     * Bangun struktur hasil; jalankan invalidasi cache saat sukses.
     *
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function result(bool $ok, array $body, ?int $status, ?callable $onSuccess = null): array
    {
        if ($ok) {
            if ($onSuccess !== null) {
                $onSuccess();
            }
        } else {
            Log::warning('ProjectWriter write non-success', ['status' => $status, 'body' => $body]);
        }

        return ['ok' => $ok, 'body' => $body, 'status' => $status, 'error' => null];
    }

    /**
     * Struktur hasil untuk kegagalan transport (exception).
     *
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function fail(string $method, \Throwable $e, array $context = []): array
    {
        Log::error("ProjectWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
