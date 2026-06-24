<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        if (is_array($cached = Cache::tags([self::TAG])->get($key))) {
            return $cached;
        }

        $url = $this->apiBase.'/global/dar/list?limit=1000000';

        if ($scope !== 'all' && $userId) {
            $url .= '&team_user='.$userId;
        }

        try {
            $data = Http::timeout(15)->retry(2, 200)->get($url)->json() ?? ['data' => []];
        } catch (\Throwable $e) {
            Log::warning('DarCache list gagal', ['scope' => $scope, 'user_id' => $userId, 'error' => $e->getMessage()]);

            return ['data' => []];
        }

        Cache::tags([self::TAG])->put($key, $data, self::TTL);

        return $data;
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
