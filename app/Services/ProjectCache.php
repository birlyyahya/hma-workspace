<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ProjectCache
{
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
        return Cache::tags([self::TAG_COMPANIES])->remember('companies:all', self::TTL_GLOBAL, function () {
            return Http::get($this->apiBase.'/companies?limit=999999999')->json()['data'] ?? [];
        });
    }

    /**
     * Semua project (id, name, leader_id, status) — dipakai filter searchable di card-task-dar.
     */
    public function allProjects(): array
    {
        return Cache::tags([self::TAG_PROJECTS])->remember('projects:all', self::TTL_GLOBAL, function () {
            return Http::get($this->apiBase.'/projects/search?limit=99999')->json()['data'] ?? [];
        });
    }

    /**
     * Listing project default (tanpa filter, page 1) — dipakai sebagai landing /projects.
     */
    public function defaultProjectsList(int $limit = 12): array
    {
        return Cache::tags([self::TAG_PROJECTS])->remember(
            "projects:list:default:limit-{$limit}",
            self::TTL_USER,
            function () use ($limit) {
                return Http::timeout(120)->retry(3, 200)
                    ->get($this->apiBase.'/projects/search', [
                        'limit' => $limit,
                        'page' => 1,
                    ])->json() ?? [];
            }
        );
    }

    /**
     * Project yang di-lead user tertentu — dipakai di create-task modal & dar widgets.
     */
    public function leaderProjects(int $userId): array
    {
        return Cache::tags([self::TAG_PROJECTS, "projects:user:{$userId}"])
            ->remember("projects:leader:{$userId}", self::TTL_USER, function () use ($userId) {
                return Http::get($this->apiBase.'/projects/search?project_leader_id='.$userId)->json()['data'] ?? [];
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
        return Cache::tags([self::TAG_PROJECTS, "projects:user:{$userId}"])
            ->remember("projects:team:{$userId}", self::TTL_USER, function () use ($userId) {
                return Http::get($this->apiBase.'/project-teams/search?user_id='.$userId)->json('data') ?? [];
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
        return Cache::tags([self::TAG_SPECTECH, "spectech:project:{$projectId}"])
            ->remember("spectech:project:{$projectId}", self::TTL_USER, function () use ($projectId) {
                return Http::timeout(120)->retry(3, 200)
                    ->get($this->apiBase.'/activity-categories/search', [
                        'project_id' => $projectId,
                        'limit' => 99999,
                    ])->json()['data'] ?? [];
            });
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
