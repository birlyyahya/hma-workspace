<?php

namespace App\Services;

use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
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
            $response = $this->sendTransaction($this->apiBase.'/esekusi-topup-wallet-pl', $payload, fileField: 'bukti_setor');

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Topup pertama CA PL oleh user_id={$payload['user_id']} sebesar Rp".number_format((float) ($payload['total_penerimaan'] ?? 0), 0, ',', '.'),
                'properties' => Arr::except($payload, ['bukti_setor']),
            ], scope: 'pl');
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
                'description' => "Mencatat pengeluaran CA PL {$kode}: {$payload['kategori']} Rp".number_format((float) ($payload['jumlah'] ?? 0), 0, ',', '.'),
                'properties' => ['kode' => $kode, ...Arr::except($payload, ['bukti'])],
            ], scope: 'pl');
        } catch (\Throwable $e) {
            return $this->fail('createTransactionCaPl', $e, ['kode' => $kode]);
        }
    }

    /**
     * Perbarui transaksi pengeluaran CA PL.
     * NON-idempotent — POST + _method=PUT (bukti opsional, hanya dikirim bila diganti).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $original  Snapshot transaksi sebelum diedit (dari caller,
     *                                          sudah ada di memori saat startEdit) — dipakai untuk
     *                                          membangun diff before/after pada activity log, tanpa
     *                                          perlu fetch ulang ke API.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateTransactionCaPl(string $kode, int $id, array $payload, array $original = []): array
    {
        try {
            $response = $this->sendTransaction(
                $this->apiBase."/ca/{$kode}/transaksi/{$id}",
                [...$payload, '_method' => 'PUT'],
            );

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            $changes = $this->diffFields($original, $payload, ['jumlah', 'kategori', 'deskripsi', 'tanggal']);
            $buktiDiganti = isset($payload['bukti']);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => $this->describeUpdate("Memperbarui transaksi CA PL {$kode} #{$id}", $changes, $buktiDiganti),
                'properties' => ['kode' => $kode, 'id' => $id, 'changes' => $changes, 'bukti_diganti' => $buktiDiganti],
            ], scope: 'pl');
        } catch (\Throwable $e) {
            return $this->fail('updateTransactionCaPl', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * @param  array<string, mixed>  $original  Snapshot transaksi yang dihapus (dari caller) —
     *                                          dicatat di log supaya jejak audit tahu apa yang hilang,
     *                                          bukan cuma id-nya.
     */
    public function deleteTransactionCaPL(string $kode, int $id, array $original = [])
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase."/ca/{$kode}/transaksi/{$id}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => $this->describeDelete("Menghapus transaksi CA PL {$kode} #{$id}", $original),
                'properties' => ['kode' => $kode, 'id' => $id, ...Arr::only($original, ['jumlah', 'kategori', 'deskripsi', 'tanggal'])],
            ], scope: 'pl');
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
                'description' => "Buat dompet kegiatan {$kodeCa}: {$payload['judul_kegiatan']} dana cair Rp".number_format((float) ($payload['total_penerimaan'] ?? 0), 0, ',', '.'),
                'properties' => Arr::except($payload, ['bukti']),
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('createDompetKegiatan', $e, ['user_id' => $payload['user_id'] ?? null]);
        }
    }

    /**
     * Perbarui detail dompet kegiatan (judul, tahun anggaran, tanggal mulai,
     * nominal dana awal & bukti pencairan) via /wallet-kegiatan/{kode}.
     * NON-idempotent — POST + _method=PUT multipart (bukti hanya dikirim bila
     * diganti), timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $original  Snapshot dompet sebelum diedit (dari caller) —
     *                                          dipakai untuk diff before/after pada activity log.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateDompetKegiatan(string $kode, array $payload, array $original = []): array
    {
        try {
            $response = $this->sendTransaction(
                $this->apiBase."/wallet-kegiatan/{$kode}",
                [...$payload],
            );

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            $changes = $this->diffFields($original, $payload, ['judul_kegiatan', 'tahun_anggaran', 'tanggal_mulai', 'total_penerimaan', 'saldo_awal_priode']);
            $buktiDiganti = isset($payload['bukti']);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => $this->describeUpdate("Memperbarui dompet kegiatan {$kode}", $changes, $buktiDiganti),
                'properties' => ['kode' => $kode, 'changes' => $changes, 'bukti_diganti' => $buktiDiganti],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('updateDompetKegiatan', $e, ['kode' => $kode]);
        }
    }

    /**
     * Hapus dompet kegiatan beserta seluruh transaksinya via /wallet-kegiatan/{kode}.
     *
     * @param  array<string, mixed>  $original  Snapshot dompet yang dihapus (dari caller) —
     *                                          dicatat di log supaya jejak audit tahu apa yang hilang.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteDompetKegiatan(string $kode, array $original = []): array
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase."/wallet-kegiatan/{$kode}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            $judul = $original['judul_kegiatan'] ?? null;

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => 'Menghapus dompet kegiatan '.$kode.($judul ? ": {$judul}" : ''),
                'properties' => ['kode' => $kode, ...Arr::only($original, ['judul_kegiatan', 'user_id', 'saldo_akhir'])],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('deleteDompetKegiatan', $e, ['kode' => $kode]);
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
                'description' => "Mencatat transaksi dompet kegiatan {$kode}: {$payload['jenis']} {$payload['kategori']} Rp".number_format((float) ($payload['jumlah'] ?? 0), 0, ',', '.'),
                'properties' => ['kode' => $kode, ...Arr::except($payload, ['bukti'])],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('createTransaksiKegiatan', $e, ['kode' => $kode]);
        }
    }

    /**
     * Perbarui transaksi dompet kegiatan.
     * NON-idempotent — POST + _method=PUT (bukti opsional, hanya dikirim bila diganti).
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $original  Snapshot transaksi sebelum diedit (dari caller) —
     *                                          dipakai untuk diff before/after pada activity log.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function updateTransaksiKegiatan(string $kode, int $id, array $payload, array $original = []): array
    {
        try {
            $response = $this->sendTransaction(
                $this->apiBase."/kegiatan/{$kode}/transaksi/{$id}",
                [...$payload, '_method' => 'PUT'],
            );

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            $changes = $this->diffFields($original, $payload, ['jenis', 'jumlah', 'kategori', 'deskripsi', 'tanggal']);
            $buktiDiganti = isset($payload['bukti']);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => $this->describeUpdate("Memperbarui transaksi dompet kegiatan {$kode} #{$id}", $changes, $buktiDiganti),
                'properties' => ['kode' => $kode, 'id' => $id, 'changes' => $changes, 'bukti_diganti' => $buktiDiganti],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('updateTransaksiKegiatan', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * @param  array<string, mixed>  $original  Snapshot transaksi yang dihapus (dari caller).
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteTransaksiKegiatan(string $kode, int $id, array $original = []): array
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase."/kegiatan/{$kode}/transaksi/{$id}");

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => $this->describeDelete("Menghapus transaksi dompet kegiatan {$kode} #{$id}", $original),
                'properties' => ['kode' => $kode, 'id' => $id, ...Arr::only($original, ['jenis', 'jumlah', 'kategori', 'deskripsi', 'tanggal'])],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('deleteTransaksiKegiatan', $e, ['kode' => $kode, 'id' => $id]);
        }
    }

    /**
     * Tutup dompet kegiatan (settlement). Bukti setor balik opsional.
     * NON-idempotent — multipart upload bukti, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context  Info dompet untuk log saja (kode_ca, judul_kegiatan,
     *                                         saldo_akhir) — tidak ikut dikirim ke API, sudah ada di
     *                                         memori caller lewat prop $wallet.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function closeKegiatan(array $payload, array $context = []): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase.'/close-kegiatan', $payload, fileField: 'bukti_setor');

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);
            $id = $payload['id'] ?? null;
            $kodeCa = $context['kode_ca'] ?? null;

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'updated',
                'description' => 'Tutup dompet kegiatan '.($kodeCa ?? "id={$id}").' (saldo akhir Rp'.number_format((float) ($context['saldo_akhir'] ?? 0), 0, ',', '.').')',
                'properties' => ['id' => $id, ...Arr::only($context, ['kode_ca', 'judul_kegiatan', 'saldo_akhir']), 'bukti_setor_diunggah' => isset($payload['bukti_setor'])],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('closeKegiatan', $e, ['id' => $payload['id'] ?? null]);
        }
    }

    /**
     * Tambah kolaborator ke dompet kegiatan.
     *
     * @param  array{dompet_id: int, user_id: int, username: string}  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function addCollaborator(array $payload): array
    {
        try {
            $response = $this->externalWrite()->post($this->apiBase.'/tambah-collaborator', $payload);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Tambah kolaborator {$payload['username']} (user_id={$payload['user_id']}) ke dompet_id={$payload['dompet_id']}",
                'properties' => $payload,
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('addCollaborator', $e, $payload);
        }
    }

    /**
     * Hapus kolaborator dari dompet kegiatan.
     *
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function deleteCollaborator(int $id, ?string $name = null): array
    {
        try {
            $response = $this->externalWrite()->delete($this->apiBase.'/delete-collaborator/'.$id);

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'deleted',
                'description' => 'Hapus kolaborator '.($name ?? "id={$id}"),
                'properties' => ['id' => $id, 'name' => $name],
            ], scope: 'kegiatan');
        } catch (\Throwable $e) {
            return $this->fail('deleteCollaborator', $e, ['id' => $id]);
        }
    }

    /**
     * Eksekusi top up CA PL (restore ke float). Wajib menyertakan bukti_setor:
     * bukti TF penyelesaian dokumen resmi, atau bukti TF reimbursement dari
     * management bila saldo akhir periode berjalan minus.
     * NON-idempotent — multipart upload bukti_setor, timeout diperpanjang.
     *
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function topup(array $payload): array
    {
        try {
            $response = $this->sendTransaction($this->apiBase.'/esekusi-topup-wallet-pl', $payload, fileField: 'bukti_setor');

            $body = (array) $response->json();
            $ok = $response->successful() || (bool) ($body['success'] ?? false);

            return $this->result($ok, $body, $response->status(), [
                'name' => 'ca',
                'event' => 'created',
                'description' => "Topup CA PL oleh user_id={$payload['user_id']} sebesar Rp".number_format((float) ($payload['total_penerimaan'] ?? 0), 0, ',', '.'),
                'properties' => Arr::except($payload, ['bukti_setor']),
            ], scope: 'pl');
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
     * Bangun struktur hasil; flush cache CA (di-scope per domain) & catat
     * activity saat sukses.
     *
     * @param  array<string, mixed>  $body
     * @param  array{name: string, event: string, description: string, properties?: array<string, mixed>}|null  $log
     * @param  'pl'|'kegiatan'|'all'  $scope  Domain cache yang perlu di-invalidate.
     *                                        'pl' hanya membuang cache CA PL (saldo master, transaksi, riwayat
     *                                        periode); 'kegiatan' hanya membuang cache daftar dompet kegiatan;
     *                                        keduanya independen sehingga menulis di satu domain tidak memaksa
     *                                        refetch domain lain yang tidak berubah.
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    private function result(bool $ok, array $body, ?int $status, ?array $log = null, string $scope = 'all'): array
    {
        if ($ok) {
            match ($scope) {
                'pl' => $this->cache->flushPl(),
                'kegiatan' => $this->cache->flushKegiatan(),
                default => $this->cache->flush(),
            };

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
     * Bandingkan snapshot lama vs payload baru per field, hanya field yang
     * benar-benar berubah yang masuk hasil — dipakai activity log update
     * supaya jelas apa yang diubah user, bukan cuma "berhasil diperbarui".
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diffFields(array $original, array $payload, array $fields): array
    {
        $changes = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }

            $from = $original[$field] ?? null;
            $to = $payload[$field];

            if ((string) $from !== (string) $to) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * Susun deskripsi activity log update: sebutkan field yang berubah bila
     * ada, supaya deskripsi di daftar log langsung informatif tanpa perlu
     * membuka detail properties.
     *
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    private function describeUpdate(string $base, array $changes, bool $buktiDiganti): string
    {
        $parts = array_keys($changes);

        if ($buktiDiganti) {
            $parts[] = 'bukti';
        }

        return $parts === [] ? "{$base} (tidak ada perubahan data)" : "{$base}: ".implode(', ', $parts);
    }

    /**
     * Susun deskripsi activity log delete dengan ringkasan transaksi yang
     * hilang (nominal & kategori), bila snapshot-nya tersedia dari caller.
     *
     * @param  array<string, mixed>  $original
     */
    private function describeDelete(string $base, array $original): string
    {
        if (! isset($original['jumlah'])) {
            return $base;
        }

        $kategori = $original['kategori'] ?? null;
        $jumlah = 'Rp'.number_format((float) $original['jumlah'], 0, ',', '.');

        return $kategori ? "{$base}: {$kategori} {$jumlah}" : "{$base}: {$jumlah}";
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
