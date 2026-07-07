<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Cache;

/**
 * Operasi READ (GET) + cache untuk domain Inventaris.
 *
 * Data inventaris berada di API eksternal (API_INVENTARIS) — tidak ada
 * tabel lokal. Semua read lewat rememberFlexible (stale-while-revalidate +
 * last good) sehingga UI tetap terisi saat API down.
 *
 * Path endpoint mengikuti konvensi awal /inventaris/* — sesuaikan saat
 * kontrak API final.
 */
class InventarisCache
{
    use CachesFlexibly;
    use MakesExternalRequests;

    private const TTL_LIST = 300;

    private const TTL_DETAIL = 300;

    private const TAG = 'inventaris';

    public function __construct(private string $apiBase) {}

    /**
     * Cache daftar inventaris terfilter/paginasi (endpoint /inventaris/list).
     * Key di-derive dari query agar tiap kombinasi filter punya cache sendiri.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $key = 'inventaris:list:'.md5((string) json_encode($query));

        return $this->rememberFlexible([self::TAG], $key, [self::TTL_LIST, self::TTL_LIST * 4], function () use ($query) {
            $response = $this->externalRead(timeout: 5)
                ->get($this->apiBase.'/inventaris/list', $query);

            if (! $response->successful()) {
                throw new \RuntimeException('inventaris list status '.$response->status());
            }

            return $response->json() ?? [];
        }, ['data' => []]);
    }

    /**
     * Cache detail satu item inventaris per-id.
     * Endpoint: /inventaris/detail/{id}
     *
     * @return array<string, mixed>
     */
    public function detail(int $id): array
    {
        return $this->rememberFlexible([self::TAG, "inventaris:detail:{$id}"], "inventaris:detail:{$id}", [self::TTL_DETAIL, self::TTL_DETAIL * 4], function () use ($id) {
            $response = $this->externalRead(timeout: 5)
                ->get($this->apiBase.'/inventaris/detail/'.$id);

            if (! $response->successful()) {
                throw new \RuntimeException('inventaris detail status '.$response->status());
            }

            return $response->json() ?? [];
        });
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }

    public function flushDetail(int $id): void
    {
        Cache::tags(["inventaris:detail:{$id}"])->flush();
    }
}
