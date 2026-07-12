<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DarCache
{
    use CachesFlexibly;
    use MakesExternalRequests;

    private const TTL = 300;

    private const TTL_DETAIL = 60;

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

    /**
     * DAR list yang dibatasi rentang tanggal dan/atau status — irisan ringan
     * untuk widget (timeline hari ini, board aktif, kalender bulan). Filter
     * dikerjakan di sisi API (start_date/end_date/status) agar payload kecil,
     * alih-alih menarik seluruh riwayat lewat list().
     *
     * Scope: 'all' = seluruh DAR (permission dar.view.all), 'user' = filter team_user.
     * Konsumen tetap WAJIB memfilter final di PHP sebagai jaring pengaman —
     * endpoint yang mengabaikan param tak boleh membocorkan data ke UI.
     *
     * @param  array<int, int>  $status
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function listForRange(string $scope, ?int $userId = null, ?string $from = null, ?string $to = null, array $status = []): array
    {
        $params = ['perPage' => 1000000];

        if ($from !== null) {
            $params['start_date'] = $from;
        }

        if ($to !== null) {
            $params['end_date'] = $to;
        }

        if ($scope !== 'all' && $userId) {
            $params['team_user'] = $userId;
        }

        if ($status !== []) {
            $params['status'] = implode(',', $status);
        }

        $scopeKey = $scope === 'all' ? 'all' : "user:{$userId}";
        $key = "dar:range:{$scopeKey}:".md5((string) json_encode($params));

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], function () use ($params) {
            $response = $this->externalRead(timeout: 15)
                ->get($this->apiBase.'/global/dar/list', $params);

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        }, ['data' => []]);
    }

    /**
     * Detail satu aktivitas DAR + komentar (#2 dar-show loadTask).
     * Cache TTL pendek; write apa pun lewat DarWriter akan flush tag 'dar'.
     *
     * @return array{data?: array<string, mixed>}
     */
    public function activity(int $id): array
    {
        return $this->rememberFlexible([self::TAG], "dar:activity:{$id}", [self::TTL_DETAIL, self::TTL_DETAIL * 4], function () use ($id) {
            $response = $this->externalRead(timeout: 15)
                ->get($this->apiBase."/global/dar/activity?id={$id}");

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        }, ['data' => []]);
    }

    /**
     * Log aktivitas DAR (#3 dar-show loadLogs).
     *
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function logs(int $id): array
    {
        return $this->rememberFlexible([self::TAG], "dar:logs:{$id}", [self::TTL_DETAIL, self::TTL_DETAIL * 4], function () use ($id) {
            $response = $this->externalRead(timeout: 15)
                ->get($this->apiBase.'/global/dar/log-activity', [
                    'activity_id' => $id,
                    'perPage' => 99999,
                ]);

            if ($response->failed()) {
                throw new \RuntimeException('status '.$response->status());
            }

            return $response->json() ?? ['data' => []];
        }, ['data' => []]);
    }

    /**
     * Daftar task DAR dengan filter (#10 card-task-dar fetchTasks).
     * Saat ada 'search' → bypass cache (query transien) agar tak meledakkan cache key.
     *
     * @param  array<string, mixed>  $params
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function tasks(array $params): array
    {
        if (! empty($params['search'])) {
            return $this->fetchTasks($params);
        }

        $key = 'dar:tasks:'.md5((string) json_encode($params));

        return $this->rememberFlexible([self::TAG], $key, [self::TTL_DETAIL, self::TTL_DETAIL * 4], fn () => $this->fetchTasks($params), ['data' => []]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{data?: array<int, array<string, mixed>>}
     */
    private function fetchTasks(array $params): array
    {
        $response = $this->externalRead(timeout: 15)
            ->get($this->apiBase.'/global/dar/list', $params);

        if ($response->failed()) {
            throw new \RuntimeException('status '.$response->status());
        }

        return $response->json() ?? ['data' => []];
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }
}
