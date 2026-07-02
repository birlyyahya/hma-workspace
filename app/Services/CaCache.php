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

    public function dashboard(int $id){
        $key = 'ca:dashboard:'.$id;

        $url = $this->apiBase."/cashadvance?user_id=".$id;

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
                Log::warning('CaCache dashboard gagal', ['error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
