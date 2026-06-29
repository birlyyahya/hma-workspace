<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IzinCache
{
    private const TTL_DASHBOARD = 300;

    private const TTL_DETAIL = 300;

    private const TTL_SPD = 300;

    private const TAG = 'izin';

    public function __construct(private string $apiBase) {}

    /**
     * Cache response dashboard izin per-user.
     * Endpoint: /global/izin/dashboard/{username}
     *
     * Side-effect: setiap fetch sekalian seed group cache global,
     * karena `data.group` identik untuk semua user.
     *
     * @return array<string, mixed>
     */
    public function dashboard(string $username, int $id): array
    {
        return Cache::tags([self::TAG, "izin:user:{$username}"])
            ->remember("izin:dashboard:{$username}", self::TTL_DASHBOARD, function () use ($username, $id) {
                $response = Http::timeout(5)->retry(2, 200)
                    ->get($this->apiBase.'/global/izin/dashboard/'.$username.'/'.$id);

                if (! $response->successful()) {
                    return [];
                }

                $json = $response->json() ?? [];

                if (isset($json['data']['group'])) {
                    Cache::tags([self::TAG])->put(
                        'izin:dashboard:group',
                        $json['data']['group'],
                        self::TTL_DASHBOARD,
                    );
                }

                return $json;
            });
    }

    /**
     * Ambil group dashboard dari cache global. Kalau cache kosong, trigger
     * dashboard() (yang sekalian seed group cache sebagai side-effect).
     *
     * @return array<string, mixed>
     */
    public function groupDashboard(): array
    {
        $cached = Cache::tags([self::TAG])->get('izin:dashboard:group');

        if ($cached !== null) {
            return $cached;
        }

        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $json = $this->dashboard($user->username, (int) $user->id);

        return $json['data']['group'] ?? [];
    }

    /**
     * Cache SPD list global (limit besar) — dipakai widget level > 90 di report-izin.
     *
     * @return array<string, mixed>
     */
    public function spdListAll(): array
    {
        return Cache::tags([self::TAG])
            ->remember('izin:spd:list:all', self::TTL_SPD, function () {
                $response = Http::timeout(5)->retry(2, 200)
                    ->get($this->apiBase.'/global/dar/activity/list-spd');

                if (! $response->successful()) {
                    return [];
                }

                return $response->json() ?? [];
            });
    }

    /**
     * Cache SPD list milik satu user (limit besar) — dipakai widget report-izin
     * untuk user tanpa permission spd.view.all.
     *
     * @return array<string, mixed>
     */
    public function spdListForUser(int $userId, string $username): array
    {
        return Cache::tags([self::TAG, "izin:user:{$username}"])
            ->remember("izin:spd:list:user:{$username}", self::TTL_SPD, function () use ($userId) {
                $response = Http::timeout(5)->retry(2, 200, throw: false)
                    ->get($this->apiBase.'/global/dar/activity/list-spd', [
                        'user_id' => $userId,
                        'limit' => 1000,
                    ]);

                if (! $response->successful()) {
                    return [];
                }

                return $response->json() ?? [];
            });
    }

    /**
     * Cache detail izin per-id.
     * Endpoint: /global/izin/detail/{id}
     *
     * @return array<string, mixed>
     */
    public function detail(int $id): array
    {
        return Cache::tags([self::TAG, "izin:detail:{$id}"])
            ->remember("izin:detail:{$id}", self::TTL_DETAIL, function () use ($id) {
                $response = Http::timeout(5)->retry(2, 200)
                    ->get($this->apiBase.'/global/izin/detail/'.$id);

                if (! $response->successful()) {
                    return [];
                }

                return $response->json() ?? [];
            });
    }

    public function flush(): void
    {
        Cache::tags([self::TAG])->flush();
    }

    public function flushUser(string $username): void
    {
        Cache::tags(["izin:user:{$username}"])->flush();
    }

    public function flushGroup(): void
    {
        Cache::forget('izin:dashboard:group');
    }

    public function flushDetail(int $id): void
    {
        Cache::tags(["izin:detail:{$id}"])->flush();
    }
}
