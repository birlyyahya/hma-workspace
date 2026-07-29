<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaCache
{
    use CachesFlexibly;
    use MakesExternalRequests;

    private const TTL = 300;

    private const TTL_DETAIL = 60;

    private const TAG = 'ca';

    public function __construct(private string $apiBase) {}

    public function DompetCaActiveTransaksi(int $id)
    {
        $key = 'ca:dompetca:'.$id;

        $url = $this->apiBase.'/list-wallet-pl?user_id='.$id.'&is_active=1';

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], function () use ($url) {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 200, function ($e) {
                        return $e instanceof \Illuminate\Http\Client\ConnectionException
                            || (method_exists($e, 'response') && optional($e->response)->serverError());
                    }, throw: false)
                    ->get($url);
                if ($response->failed()) {
                    throw new \RuntimeException('status '.$response->status());
                }

                return $response->json()['data'] ?? ['data' => []];
            } catch (\Throwable $e) {
                Log::warning('CaCache dashboard gagal', ['error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    public function transaksiCaPlActive(string $kode, array $param = []): array
    {
        $url = $this->apiBase.'/transaksi-wallet-pl/'.$kode;

        try {
            $response = Http::timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($url, array_filter($param));

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('CaCache transaksiCaPlActive gagal', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Daftar dompet kegiatan milik user (kartu di sidebar ca-index).
     *
     * @param  array<string, mixed>  $param
     */
    public function listWalletKegiatan(int $userId, array $param = []): array
    {
        $url = $this->apiBase.'/list-wallet-kegiatan';

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($url, array_filter([...$param, 'user_id' => $userId, 'tr_ca_transaction' => 'true']));

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('CaCache listWalletKegiatan gagal', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Detail satu dompet kegiatan berdasarkan kode CA.
     *
     * @param  array<string, mixed>  $param
     */
    public function walletKegiatanByKode(string $kode, array $param = []): array
    {
        $url = $this->apiBase.'/wallet-kegiatan/'.$kode;

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($url, array_filter($param));

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('CaCache walletKegiatanByKode gagal', ['kode' => $kode, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Transaksi pada satu dompet kegiatan.
     *
     * @param  array<string, mixed>  $param
     */
    public function transaksiWalletKegiatan(string $kode, array $param = []): array
    {
        $url = $this->apiBase.'/transaksi-wallet-kegiatan/'.$kode;

        try {
            $response = Http::acceptJson()
                ->timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof \Illuminate\Http\Client\ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($url, array_filter($param));

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('CaCache transaksiWalletKegiatan gagal', ['kode' => $kode, 'error' => $e->getMessage()]);

            throw $e;
        }
    }

    public function riwayatDompetCaPl(int $id)
    {
        $key = 'ca:riwayat-dompet-ca-pl:'.$id;

        $url = $this->apiBase.'/riwayat-wallet-kegiatan?user_id='.$id.'&is_active=0';

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], function () use ($url) {
            try {
                $response = Http::timeout(15)
                    ->retry(2, 200, function ($e) {
                        return $e instanceof \Illuminate\Http\Client\ConnectionException
                            || (method_exists($e, 'response') && optional($e->response)->serverError());
                    }, throw: false)
                    ->get($url);

                if ($response->failed()) {
                    throw new \RuntimeException('status '.$response->status());
                }

                return $response->json() ?? ['data' => []];
            } catch (\Throwable $e) {
                Log::warning('CaCache riwayat Dompket Ca Pl gagal', ['error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
