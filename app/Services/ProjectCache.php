<?php

namespace App\Services;

use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProjectCache
{
    use CachesFlexibly;
    use MakesExternalRequests;

    private const TTL_GLOBAL = 600;

    private const TTL_USER = 300;

    private const TAG_PROJECTS = 'projects';

    private const TAG_COMPANIES = 'companies';

    private const TAG_SPECTECH = 'spectech';

    public function __construct(private string $apiBase) {}

    /**
     * Semua company — dipakai sebagai dropdown di project-create & project-edit.
     */
    public function allCompanies(): array
    {
        return $this->rememberFlexible([self::TAG_COMPANIES], 'companies:all', [self::TTL_GLOBAL, self::TTL_GLOBAL * 4], function () {
            try {
                $response = $this->externalGet()->get($this->apiBase.'/companies?limit=999999999');

                if ($response->failed()) {
                    throw new \RuntimeException('companies status '.$response->status());
                }

                return $response->json()['data'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('ProjectCache allCompanies gagal', ['error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    /**
     * Semua project (id, name, leader_id, status) — dipakai filter searchable di card-task-dar.
     */
    public function allProjects(): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS], 'projects:all', [self::TTL_GLOBAL, self::TTL_GLOBAL * 4], function () {
            try {
                $response = $this->externalGet()->get($this->apiBase.'/projects/search?limit=99999');

                if ($response->failed()) {
                    throw new \RuntimeException('projects status '.$response->status());
                }

                return $response->json()['data'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('ProjectCache allProjects gagal', ['error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    /**
     * Listing project default (tanpa filter, page 1) — dipakai sebagai landing /projects.
     */
    public function defaultProjectsList(int $limit = 12): array
    {
        $key = "projects:list:default:limit-{$limit}";

        if (is_array($cached = Cache::tags([self::TAG_PROJECTS])->get($key))) {
            return $cached;
        }

        try {
            $data = Http::timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($this->apiBase.'/projects/search', [
                    'limit' => $limit,
                    'page' => 1,
                ])->json() ?? [];
        } catch (ConnectionException $e) {
            Log::warning('ProjectCache defaultProjectsList gagal', ['error' => $e->getMessage()]);

            return [];
        }

        Cache::tags([self::TAG_PROJECTS])->put($key, $data, self::TTL_USER);

        return $data;
    }

    /**
     * Project yang di-lead user tertentu — dipakai di create-task modal & dar widgets.
     */
    public function leaderProjects(int $userId): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS, "projects:user:{$userId}"], "projects:leader:{$userId}", [self::TTL_USER, self::TTL_USER * 4], function () use ($userId) {
            try {
                $response = $this->externalGet()->get($this->apiBase.'/projects/search?project_leader_id='.$userId);

                if ($response->failed()) {
                    throw new \RuntimeException('leader projects status '.$response->status());
                }

                return $response->json()['data'] ?? [];
            } catch (\Throwable $e) {
                Log::warning('ProjectCache leaderProjects gagal', ['user_id' => $userId, 'error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    /**
     * Project di mana user tergabung sebagai anggota tim (bukan leader).
     * Endpoint hanya mengembalikan project_id & project_name, tanpa code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function teamProjects(int $userId): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS, "projects:user:{$userId}"], "projects:team:{$userId}", [self::TTL_USER, self::TTL_USER * 4], function () use ($userId) {
            try {
                $response = $this->externalGet()->get($this->apiBase.'/project-teams/search?user_id='.$userId);

                if ($response->failed()) {
                    throw new \RuntimeException('team projects status '.$response->status());
                }

                return $response->json('data') ?? [];
            } catch (\Throwable $e) {
                Log::warning('ProjectCache teamProjects gagal', ['user_id' => $userId, 'error' => $e->getMessage()]);

                throw $e;
            }
        });
    }

    /**
     * Semua project yang melibatkan user — sebagai leader maupun anggota tim —
     * terdeduplikasi per id. Dipakai untuk daftar project di sidebar.
     *
     * @return array<int, array{id: int, code: ?string, name: ?string}>
     */
    public function involvedProjects(int $userId): array
    {
        $byId = [];

        foreach ($this->leaderProjects($userId) as $project) {
            $byId[$project['id']] = [
                'id' => $project['id'],
                'code' => $project['code'] ?? null,
                'name' => $project['name'] ?? null,
            ];
        }

        $teams = $this->teamProjects($userId);

        if (! empty($teams)) {
            $catalog = collect($this->allProjects())->keyBy('id');

            foreach ($teams as $team) {
                $projectId = $team['project_id'] ?? null;

                if ($projectId === null || isset($byId[$projectId])) {
                    continue;
                }

                $full = $catalog->get($projectId);

                $byId[$projectId] = [
                    'id' => $projectId,
                    'code' => $full['code'] ?? null,
                    'name' => $team['project_name'] ?? $full['name'] ?? null,
                ];
            }
        }

        return array_values($byId);
    }

    /**
     * Daftar spektek (activity categories) milik satu project — sumber kanonik
     * untuk tab Spektek & ringkasan Overview. Endpoint terpaginasi, jadi ambil
     * semua sekaligus dengan limit besar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function spectechFor(int $projectId): array
    {
        $tags = [self::TAG_SPECTECH, "spectech:project:{$projectId}"];
        $key = "spectech:project:{$projectId}";

        if (is_array($cached = Cache::tags($tags)->get($key))) {
            return $cached;
        }

        try {
            $data = Http::timeout(15)
                ->retry(2, 200, function ($e) {
                    return $e instanceof ConnectionException
                        || (method_exists($e, 'response') && optional($e->response)->serverError());
                }, throw: false)
                ->get($this->apiBase.'/activity-categories/search', [
                    'project_id' => $projectId,
                    'limit' => 99999,
                ])->json('data') ?? [];
        } catch (ConnectionException $e) {
            Log::warning('ProjectCache spectechFor gagal', ['project_id' => $projectId, 'error' => $e->getMessage()]);

            return [];
        }

        Cache::tags($tags)->put($key, $data, self::TTL_USER);

        return $data;
    }

    public function flushProjects(): void
    {
        Cache::tags([self::TAG_PROJECTS])->flush();
    }

    /**
     * Buang cache project milik satu user (leader & team) — dipanggil saat
     * keanggotaan tim berubah agar sidebar user tersebut langsung diperbarui.
     */
    public function flushUser(int $userId): void
    {
        Cache::tags(["projects:user:{$userId}"])->flush();
    }

    public function flushSpectech(int $projectId): void
    {
        Cache::tags(["spectech:project:{$projectId}"])->flush();
    }

    public function flushCompanies(): void
    {
        Cache::tags([self::TAG_COMPANIES])->flush();
    }
}
