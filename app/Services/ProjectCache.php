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

    public function flushProjects(): void
    {
        Cache::tags([self::TAG_PROJECTS])->flush();
    }

    public function flushCompanies(): void
    {
        Cache::tags([self::TAG_COMPANIES])->flush();
    }
}
