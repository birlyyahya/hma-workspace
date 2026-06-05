<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CaCache
{
    private const TTL_DOMPET = 300;

    private const TTL_TRANSAKSI = 300;

    private const TAG = 'ca';

    public function __construct(private string $apiBase) {}

    /**
     * Cache daftar dompet (CA) per-user.
     * Endpoint: /ca-pl?user_id={userId}
     *
     * @return array<int, array<string, mixed>>
     */
    public function dompetList(int $userId): array
    {
        return Cache::tags([self::TAG, "ca:user:{$userId}"])
            ->remember("ca:dompet:{$userId}", self::TTL_DOMPET, function () use ($userId) {
                $response = Http::timeout(30)->retry(2, 200)
                    ->get($this->apiBase.'/ca-pl', ['user_id' => $userId]);

                if (! $response->successful()) {
                    return [];
                }

                $json = $response->json() ?? [];

                if (($json['status'] ?? null) !== 'success') {
                    return [];
                }

                return $json['data'] ?? [];
            });
    }

    /**
     * Dompet PL utama (data_category id 1, status approved) milik user.
     *
     * @return array<string, mixed>
     */
    public function dompetPl(int $userId): array
    {
        return collect($this->dompetList($userId))->first(
            fn (array $item): bool => ($item['data_category']['id'] ?? null) === 1
                && ($item['status'] ?? null) === 'approved'
        ) ?? [];
    }

    /**
     * Daftar dompet kegiatan (data_category id 2) milik user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dompetKegiatan(int $userId): array
    {
        return collect($this->dompetList($userId))
            ->filter(fn (array $item): bool => ($item['data_category']['id'] ?? null) === 2)
            ->values()
            ->all();
    }

    /**
     * Satu dompet berdasarkan kode CA milik user.
     *
     * @return array<string, mixed>
     */
    public function dompetByKode(int $userId, string $kodeCa): array
    {
        return collect($this->dompetList($userId))
            ->first(fn (array $item): bool => ($item['kode_ca'] ?? null) === $kodeCa) ?? [];
    }

    /**
     * Cache transaksi per kode CA.
     * Endpoint: /transaksi/{kodeCa}
     *
     * @return array<int, array<string, mixed>>
     */
    public function transaksi(string $kodeCa): array
    {
        return Cache::tags([self::TAG, "ca:transaksi:{$kodeCa}"])
            ->remember("ca:transaksi:{$kodeCa}", self::TTL_TRANSAKSI, function () use ($kodeCa) {
                $response = Http::timeout(30)->retry(2, 200)
                    ->get($this->apiBase.'/transaksi/'.$kodeCa);

                if (! $response->successful()) {
                    return [];
                }

                $json = $response->json() ?? [];

                if (! ($json['success'] ?? false)) {
                    return [];
                }

                return $json['data'] ?? [];
            });
    }

    /**
     * Tambah transaksi (send/receive) pada sebuah dompet.
     * Endpoint: POST /ca/{kodeCa}/transaksi (multipart, ada file bukti).
     *
     * @param  array{tanggal: string, jenis: string, jumlah: numeric, deskripsi: string}  $payload
     * @return array<string, mixed>
     */
    public function addTransaksi(string $kodeCa, array $payload, ?UploadedFile $bukti = null): array
    {
        $request = Http::timeout(60);

        if ($bukti !== null) {
            $request = $request->attach(
                'bukti',
                file_get_contents($bukti->getRealPath()),
                $bukti->getClientOriginalName(),
            );
        }

        $response = $request->post($this->apiBase.'/ca/'.$kodeCa.'/transaksi', $payload);

        if ($response->successful()) {
            $this->flush();
        }

        return $response->json() ?? [];
    }

    /**
     * Hapus transaksi berdasarkan id.
     * Endpoint: DELETE /ca/transaksi/{id}.
     *
     * @return array<string, mixed>
     */
    public function deleteTransaksi(int $id): array
    {
        $response = Http::timeout(30)->delete($this->apiBase.'/ca/transaksi/'.$id);

        if ($response->successful()) {
            $this->flush();
        }

        return $response->json() ?? [];
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }

    public function flushUser(int $userId): void
    {
        Cache::tags(["ca:user:{$userId}"])->flush();
    }

    public function flushTransaksi(string $kodeCa): void
    {
        Cache::tags(["ca:transaksi:{$kodeCa}"])->flush();
    }
}
