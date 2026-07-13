<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Support\Facades\Cache;

class DarCache
{
    use CachesFlexibly;
    use MakesExternalRequests;

    private const TTL = 300;

    private const TTL_DETAIL = 60;

    private const TAG = 'dar';

    public function __construct(private string $apiBase) {}

    private const STATUS_OPEN = 1;

    private const BOARD_RECENT_DAYS = 30;

    /**
     * DAR yang start_date-nya jatuh dalam rentang [from, to]. CATATAN PENTING:
     * API DARBE memfilter start-in-range (bukan overlap), jadi task multi-hari
     * yang MULAI sebelum `from` TIDAK ikut — gunakan bersama listByStatus()
     * lewat timelineToday() untuk menangkap task yang masih berjalan.
     *
     * Scope: 'all' = seluruh DAR (permission dar.view.all), 'user' = filter team_user.
     *
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function listForRange(string $scope, ?int $userId = null, ?string $from = null, ?string $to = null): array
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

        $scopeKey = $scope === 'all' ? 'all' : "user:{$userId}";
        $key = "dar:range:{$scopeKey}:".md5((string) json_encode($params));

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], fn () => $this->fetchTasks($params), ['data' => []]);
    }

    /**
     * DAR untuk SATU status (API tidak menerima multi-status: koma/array/param
     * berulang semuanya gagal). Di-cache terpisah per status agar dipakai ulang
     * lintas widget. Set "open" terikat alami oleh WIP berjalan, bukan riwayat.
     *
     * @return array{data?: array<int, array<string, mixed>>}
     */
    public function listByStatus(string $scope, ?int $userId, int $status): array
    {
        $params = ['perPage' => 1000000, 'status' => $status];

        if ($scope !== 'all' && $userId) {
            $params['team_user'] = $userId;
        }

        $scopeKey = $scope === 'all' ? 'all' : "user:{$userId}";
        $key = "dar:status:{$scopeKey}:{$status}";

        return $this->rememberFlexible([self::TAG], $key, [self::TTL, self::TTL * 4], fn () => $this->fetchTasks($params), ['data' => []]);
    }

    /**
     * DAR relevan untuk timeline hari ini, dirakit dari dua query karena API
     * tidak punya filter overlap:
     *   1) start_date = hari ini → task yang MULAI hari ini (durasi berapa pun),
     *   2) status Open           → task yang MASIH berjalan, mulai kapan pun.
     * Digabung & dedupe by id; konsumen tetap memfilter overlap final di PHP.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function timelineToday(string $scope, ?int $userId = null): array
    {
        $today = now()->format('Y-m-d');

        $rows = collect($this->listForRange($scope, $userId, $today, $today)['data'] ?? [])
            ->concat($this->listByStatus($scope, $userId, self::STATUS_OPEN)['data'] ?? [])
            ->unique('id')
            ->values()
            ->all();

        return ['data' => $rows];
    }

    /**
     * DAR untuk board-overview, dirakit dari dua query (API filter start-in-range
     * saja, single-status):
     *   1) status Open           → todos (aktif, mulai kapan pun),
     *   2) start_date >= today-N  → schedules (date>=today-2) & Closed done-today.
     * Digabung & dedupe by id; komponen tetap menyaring todos/schedule di PHP.
     *
     * @return array{data: array<int, array<string, mixed>>}
     */
    public function board(string $scope, ?int $userId = null): array
    {
        $from = now()->subDays(self::BOARD_RECENT_DAYS)->format('Y-m-d');

        $rows = collect($this->listByStatus($scope, $userId, self::STATUS_OPEN)['data'] ?? [])
            ->concat($this->listForRange($scope, $userId, $from, null)['data'] ?? [])
            ->unique('id')
            ->values()
            ->all();

        return ['data' => $rows];
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
