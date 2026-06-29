<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarCache
{
    use CachesFlexibly;

    private const TTL = 300;

    private const TAG = 'dar';

    public function __construct(private string $apiBase) {}

    /**
     * Full DAR list (limit=1000000) — dipakai di board-overview & timeline-today widget.
     * Scope: 'all' = seluruh DAR (user dengan permission dar.view.all), 'user' = filter by team_user.
     *
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function list(string $scope, ?int $userId = null): array
    {
        $key = $scope === 'all'
            ? 'dar:list:all'
            : "dar:list:user:{$userId}";

        $url = $this->apiBase.'/global/dar/list?perPage=1000000';

        if ($scope !== 'all' && $userId) {
            $url .= '&team_user='.$userId;
        }

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], function () use ($url, $scope, $userId) {
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
                Log::warning('DarCache list gagal', ['scope' => $scope, 'user_id' => $userId, 'error' => $e->getMessage()]);

                throw $e;
            }
        }, ['data' => []]);
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
