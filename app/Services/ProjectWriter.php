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

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'created',
                'description' => 'Membuat project baru project #'.($body['id'] ?? 'Unknown'),
                'properties' => ['name' => $payload['name'] ?? null, 'payload' => $payload],
            ]);
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

            return $this->result($this->statusSucceeded($response->status(), $body), $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Memperbarui project #{$id}",
                'properties' => ['id' => $id, 'payload' => $payload],
            ]);
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

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'deleted',
                'description' => "Menghapus project #{$id}",
                'properties' => ['id' => $id],
            ]);
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

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'created',
                'description' => 'Menambah timeline project di '.($payload['project_id'] ?? 'Unknown'),
                'properties' => ['project_id' => $payload['project_id'] ?? null, 'payload' => $payload],
            ]);
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

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Memperbarui timeline #{$id} di project #".($body['project_id'] ?? 'Unknown'),
                'properties' => ['id' => $id, 'payload' => $payload],
            ]);
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

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), fn () => $this->cache->flushProjects(), [
                'name' => 'project',
                'event' => 'deleted',
                'description' => "Menghapus timeline #{$id} di project #".($body['project_id'] ?? 'Unknown'),
                'properties' => ['id' => $id],
            ]);
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

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), function () use ($userId): void {
                $this->cache->flushUser($userId);
                $this->cache->flushProjects();
            }, [
                'name' => 'project',
                'event' => 'created',
                'description' => "Menambah anggota tim ke project #{$projectId}",
                'properties' => ['project_id' => $projectId, 'user_id' => $userId],
            ]);
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

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), function () use ($userId): void {
                $this->cache->flushUser($userId);
                $this->cache->flushProjects();
            }, [
                'name' => 'project',
                'event' => 'deleted',
                'description' => 'Menghapus anggota tim project pada project #'.($body['project_id'] ?? 'Unknown'),
                'properties' => ['team_id' => $teamId, 'user_id' => $userId],
            ]);
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

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushCompanies(), [
                'name' => 'perusahaan',
                'event' => 'deleted',
                'description' => "Menghapus perusahaan #{$id}",
                'properties' => ['id' => $id],
            ]);
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
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/spekteks/'.$id);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId), [
                'name' => 'project',
                'event' => 'deleted',
                'description' => "Menghapus kategori spektek #{$id} (project #{$projectId})",
                'properties' => ['id' => $id, 'project_id' => $projectId],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteSpectechCategory', $e, ['id' => $id, 'project_id' => $projectId]);
        }
    }

    /**
     * Tambah spektek (activity category). NON-idempotent. Sukses = body.status === 201.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createSpectechCategory(int $projectId, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->post($this->apiBase.'/spekteks', $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushSpectech($projectId), [
                'name' => 'project',
                'event' => 'created',
                'description' => "Menambah spektek project di project #{$projectId}",
                'properties' => ['project_id' => $projectId, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createSpectechCategory', $e, ['project_id' => $projectId]);
        }
    }

    /**
     * Perbarui spektek (POST ke /spekteks/{id}, meniru pemanggil asli).
     * Idempotent. Sukses = body.status === 200.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateSpectechCategory(int $id, int $projectId, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->patch($this->apiBase.'/spekteks/'.$id, $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), fn () => $this->cache->flushSpectech($projectId), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Memperbarui spektek #{$id} (project #{$projectId})",
                'properties' => ['id' => $id, 'project_id' => $projectId, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateSpectechCategory', $e, ['id' => $id, 'project_id' => $projectId]);
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
            $response = $this->externalWrite(timeout: 30)->post($this->apiBase.'/spekteks/bulk', $payload);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Menyimpan spektek project #{$projectId}",
                'properties' => ['project_id' => $projectId, 'payload' => $payload],
            ]);
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
                ->post($this->apiBase.'/spekteks/import', ['project_id' => $projectId]);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSpectech($projectId), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Mengimpor spektek project #{$projectId}",
                'properties' => ['project_id' => $projectId, 'file' => $file['name'] ?? null],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('importSpectech', $e, ['project_id' => $projectId]);
        }
    }

    // ------------------------------------------------------------- Sub Spektek

    /**
     * Tambah sub spektek pada satu spektek. NON-idempotent. Sukses = body.status === 201.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function createSubSpectech(int $spektekId, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->post($this->apiBase.'/sub-spekteks', $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), fn () => $this->cache->flushSubSpectech($spektekId), [
                'name' => 'project',
                'event' => 'created',
                'description' => "Menambah sub spektek pada spektek #{$spektekId}",
                'properties' => ['spektek_id' => $spektekId, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('createSubSpectech', $e, ['spektek_id' => $spektekId]);
        }
    }

    /**
     * Perbarui sub spektek. Idempotent. Sukses = body.status === 200.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateSubSpectech(int $id, int $spektekId, array $payload): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->patch($this->apiBase.'/sub-spekteks/'.$id, $payload);
            $body = (array) $response->json();

            return $this->result((int) ($body['status'] ?? 0) === 200, $body, $response->status(), fn () => $this->cache->flushSubSpectech($spektekId), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Memperbarui sub spektek #{$id} (spektek #{$spektekId})",
                'properties' => ['id' => $id, 'spektek_id' => $spektekId, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateSubSpectech', $e, ['id' => $id, 'spektek_id' => $spektekId]);
        }
    }

    /**
     * Perbarui jumlah diterima sub spektek. Idempotent. Sukses = HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateSubSpectechQty(int $id, int $spektekId, int $qtyReceived): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->patch($this->apiBase.'/sub-spekteks/'.$id.'/updateQtyReceived', [
                'qty_received' => $qtyReceived,
            ]);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSubSpectech($spektekId), [
                'name' => 'project',
                'event' => 'updated',
                'description' => "Memperbarui qty diterima sub spektek #{$id} (spektek #{$spektekId})",
                'properties' => ['id' => $id, 'spektek_id' => $spektekId, 'qty_received' => $qtyReceived],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('updateSubSpectechQty', $e, ['id' => $id, 'spektek_id' => $spektekId]);
        }
    }

    /**
     * Hapus sub spektek (soft delete di backend). Sukses = HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteSubSpectech(int $id, int $spektekId): array
    {
        try {
            $response = $this->externalWrite(timeout: 10)->delete($this->apiBase.'/sub-spekteks/'.$id);
            $body = (array) $response->json();

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushSubSpectech($spektekId), [
                'name' => 'project',
                'event' => 'deleted',
                'description' => "Menghapus sub spektek #{$id} (spektek #{$spektekId})",
                'properties' => ['id' => $id, 'spektek_id' => $spektekId],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteSubSpectech', $e, ['id' => $id, 'spektek_id' => $spektekId]);
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

            return $this->result((int) ($body['status'] ?? 0) === 201, $body, $response->status(), null, [
                'name' => 'project',
                'event' => 'created',
                'description' => 'Mengunggah dokumen admin project di project #'.($payload['project_id'] ?? 'Unknown'),
                'properties' => ['project_id' => $payload['project_id'] ?? null, 'payload' => $payload],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('uploadDoc', $e);
        }
    }

    /**
     * Hapus dokumen admin project. Sukses = body.status === 200 atau HTTP 2xx.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteDoc(int $id): array
    {
        try {
            $response = $this->externalWrite(timeout: 30)->delete($this->apiBase.'/admin-docs/'.$id);
            $body = (array) $response->json();
            $ok = (int) ($body['status'] ?? 0) === 200 || $response->successful();

            return $this->result($ok, $body, $response->status(), null, [
                'name' => 'project',
                'event' => 'deleted',
                'description' => "Menghapus dokumen admin project #{$id} di project #".($body['project_id'] ?? 'Unknown'),
                'properties' => ['id' => $id],
            ]);
        } catch (\Throwable $e) {
            return $this->fail('deleteDoc', $e, ['id' => $id]);
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

            return $this->result($response->successful(), $body, $response->status(), fn () => $this->cache->flushCompanies(), [
                'name' => 'perusahaan',
                'event' => $id !== null ? 'updated' : 'created',
                'description' => $id !== null ? "Memperbarui perusahaan #{$id}" : 'Membuat perusahaan baru',
                'properties' => [
                    'id' => $id,
                    'name' => $payload['name'] ?? null,
                    'payload' => $payload,
                    'letter_head' => $file['name'] ?? null,
                ],
            ]);
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
     * Bangun struktur hasil; jalankan invalidasi cache & catat activity saat sukses.
     *
     * @param  array<string, mixed>  $body
     * @param  array{name: string, event: string, description: string, properties?: array<string, mixed>}|null  $log
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function result(bool $ok, array $body, ?int $status, ?callable $onSuccess = null, ?array $log = null): array
    {
        if ($ok) {
            if ($onSuccess !== null) {
                $onSuccess();
            }

            if ($log !== null) {
                $this->logActivity($log);
            }
        } else {
            Log::warning('ProjectWriter write non-success', ['status' => $status, 'body' => $body]);
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
        Log::error("ProjectWriter::{$method} exception", [...$context, 'message' => $e->getMessage()]);

        return ['ok' => false, 'body' => [], 'status' => null, 'error' => $e->getMessage()];
    }
}
