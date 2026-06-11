<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class DarCache
{
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

        return Cache::tags([self::TAG])->remember($key, self::TTL, function () use ($scope, $userId) {
            $url = $this->apiBase.'/global/dar/list?limit=1000000';

            if ($scope !== 'all' && $userId) {
                $url .= '&team_user='.$userId;
            }

            return Http::timeout(30)->retry(3, 200)->get($url)->json() ?? ['data' => []];
        });
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
