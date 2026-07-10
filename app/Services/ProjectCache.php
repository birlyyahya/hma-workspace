<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\CachesFlexibly;
use App\Services\Concerns\MakesExternalRequests;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                $response = $this->externalRead()->get($this->apiBase.'/companies?limit=999999999');

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
                $response = $this->externalRead()->get($this->apiBase.'/projects/search?limit=99999');

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
                $response = $this->externalRead()->get($this->apiBase.'/projects/search?project_leader_id='.$userId);

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
                $response = $this->externalRead()->get($this->apiBase.'/project-teams/search?user_id='.$userId);

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
     * Timeline/kategori sebuah project — dipakai di dropdown create/edit task DAR.
     *
     * @return array<int, array<string, mixed>>
     */
    public function timelines(int $projectId): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS], "timelines:project:{$projectId}", [self::TTL_USER, self::TTL_USER * 4], function () use ($projectId) {
            try {
                $response = $this->externalRead()->get($this->apiBase.'/timelines/search?project_id='.$projectId);

                if ($response->failed()) {
                    throw new \RuntimeException('timelines status '.$response->status());
                }

                return $response->json('data') ?? [];
            } catch (\Throwable $e) {
                Log::warning('ProjectCache timelines gagal', ['project_id' => $projectId, 'error' => $e->getMessage()]);

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

    /**
     * Detail satu project by id. Endpoint mengembalikan `data` sebagai list;
     * project berada di elemen pertama. Dipakai di project-show/-edit/-preview.
     *
     * @return array<string, mixed>
     */
    public function projectFor(int $id): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS], "project:{$id}", [self::TTL_USER, self::TTL_USER * 4], function () use ($id) {
            $response = $this->externalRead(timeout: 15)->get($this->apiBase.'/projects/'.$id);

            if ($response->failed()) {
                throw new \RuntimeException('project status '.$response->status());
            }

            return collect($response->json('data') ?? [])->first() ?? [];
        });
    }

    /**
     * Pencarian perusahaan terpaginasi (live, tanpa cache — driven user input).
     * Mengembalikan payload penuh (data + pagination).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function searchCompanies(array $params): array
    {
        try {
            $response = $this->externalRead(timeout: 15)->get($this->apiBase.'/companies/search', $params);

            if ($response->failed()) {
                throw new \RuntimeException('companies search status '.$response->status());
            }

            return (array) $response->json();
        } catch (\Throwable $e) {
            Log::warning('ProjectCache searchCompanies gagal', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Pencarian dokumen admin sebuah project (live, tanpa cache).
     * Mengembalikan payload penuh (caller cek field `status` & baca `data`).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function searchDocs(array $params): array
    {
        try {
            $response = $this->externalRead(timeout: 15)->get($this->apiBase.'/admin-docs/search', $params);

            if ($response->failed()) {
                throw new \RuntimeException('admin-docs search status '.$response->status());
            }

            return (array) $response->json();
        } catch (\Throwable $e) {
            Log::warning('ProjectCache searchDocs gagal', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Daftar kategori dokumen admin (statis, di-cache global).
     *
     * @return array<int, array<string, mixed>>
     */
    public function docCategories(): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS], 'admin-doc-categories', [self::TTL_GLOBAL, self::TTL_GLOBAL * 4], function () {
            $response = $this->externalRead(timeout: 15)->get($this->apiBase.'/admin-doc-categories', ['limit' => 1000]);

            if ($response->failed()) {
                throw new \RuntimeException('admin-doc-categories status '.$response->status());
            }

            return $response->json('data') ?? [];
        });
    }

    private function timelineMeta(string $title): array
    {
        return match (Str::lower($title)) {
            'tanda tangan kontrak' => [
                'key' => 'kontrak',
                'icon' => 'document-check',
            ],

            'barang tiba' => [
                'key' => 'barang-tiba',
                'icon' => 'truck',
            ],

            default => [
                'key' => Str::slug($title),
                'icon' => 'calendar',
            ],
        };
    }

    /**
     * Gabungkan timeline (BEPM), aktivitas DAR, dan dokumen admin sebuah project
     * menjadi daftar tahapan progress siap-render. Dipakai di tab Progress dan
     * ringkasan Overview. Di-cache karena `searchDocs` bersifat live (tanpa cache),
     * sehingga pemanggilan berulang tidak menembak API admin-docs tiap kali.
     *
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     icon: string,
     *     status: string,
     *     date: ?string,
     *     range: string,
     *     signals: array<int, string>,
     *     activities: array<int, array{title: string, user: string, date: string, status: string}>,
     *     documents: array<int, array{name: string, size: ?string}>,
     *     notes: ?string,
     * }>
     */
    public function progressStages(int $id): array
    {
        return $this->rememberFlexible([self::TAG_PROJECTS], "progress-stages:project:{$id}", [self::TTL_USER, self::TTL_USER * 4], function () use ($id) {
            $timelines = collect($this->timelines($id));

            if ($timelines->isEmpty()) {
                return [];
            }

            $activities = collect(app(DarCache::class)->tasks(['project_id' => $id])['data'] ?? []);
            $activitiesByCategory = $activities->groupBy('project_category_id');
            $documents = collect($this->searchDocs(['project_id' => $id, 'limit' => 999])['data'] ?? []);

            $userNames = User::whereIn('id', $activities->pluck('user_id')->filter()->unique())
                ->pluck('name', 'id');

            $today = Carbon::today();

            return $timelines
                ->sortBy(fn (array $timeline): int => Carbon::parse($timeline['start_date'])->timestamp)
                ->map(function (array $timeline) use ($activitiesByCategory, $documents, $userNames, $today): array {
                    $start = Carbon::parse($timeline['start_date'])->startOfDay();
                    $end = Carbon::parse($timeline['end_date'])->endOfDay();

                    $stageActivities = $this->buildStageActivities($activitiesByCategory->get($timeline['id'], collect()), $userNames);
                    $stageDocuments = $this->buildStageDocuments($documents, $stageActivities, $timeline['title'], $start, $end) ?? [];
                    $status = $this->resolveStageStatus($today, $start, $end, $stageActivities, $stageDocuments);

                    return [
                        'key' => Str::slug($timeline['title']),
                        'title' => $timeline['title'],
                        'icon' => $this->timelineMeta($timeline['title'])['icon'],
                        'status' => $status,
                        'date' => $status === 'done' ? $end->translatedFormat('j M Y') : null,
                        'range' => sprintf(
                            '%s – %s',
                            $start->translatedFormat('j M'),
                            $end->translatedFormat('j M Y'),
                        ),
                        'signals' => $this->buildStageSignals($stageActivities, $stageDocuments),
                        'activities' => $stageActivities,
                        'documents' => $stageDocuments,
                        'notes' => $timeline['notes'] ?? null,
                    ];
                })
                ->values()
                ->toArray();
        });
    }

    /**
     * Ringkasan progress project — jumlah tahap per status, persentase selesai,
     * dan judul tahap yang sedang berjalan. Dipakai di header tab Progress dan
     * kartu ringkasan Overview. Membaca hasil {@see self::progressStages()} yang
     * sudah di-cache, jadi tidak menembak API ulang.
     *
     * @return array{total: int, done: int, current: int, upcoming: int, pending: int, percent: int, current_title: ?string}
     */
    public function progressSummary(int $id): array
    {
        $stages = collect($this->progressStages($id));

        $total = $stages->count();
        $done = $stages->where('status', 'done')->count();

        return [
            'total' => $total,
            'done' => $done,
            'current' => $stages->where('status', 'current')->count(),
            'upcoming' => $stages->where('status', 'upcoming')->count(),
            'pending' => $stages->where('status', 'pending')->count(),
            'percent' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'current_title' => $stages->firstWhere('status', 'current')['title'] ?? null,
        ];
    }

    /**
     * Aktivitas DAR satu tahap, terurut tanggal terlama → terbaru.
     *
     * @param  Collection<int, array<string, mixed>>  $activities
     * @param  Collection<int, string>  $userNames
     * @return array<int, array{title: string, user: string, date: string, status: string}>
     */
    private function buildStageActivities(Collection $activities, Collection $userNames): array
    {
        return $activities
            ->sortBy(fn (array $activity): int => Carbon::parse($activity['date'])->timestamp)
            ->map(fn (array $activity): array => [
                'title' => $activity['activity'],
                'user' => $userNames[$activity['user_id']] ?? 'Pengguna',
                'date' => Carbon::parse($activity['date'])->translatedFormat('j M Y'),
                'status' => (int) $activity['status'] === 4 ? 'CLOSED' : 'OPEN',
            ])
            ->values()
            ->toArray();
    }

    /**
     * Dokumen admin yang `created_at`-nya jatuh di dalam rentang tahap. Dokumen
     * tidak terikat timeline di BEPM, jadi dipetakan berdasarkan tanggal unggah.
     *
     * @param  Collection<int, array<string, mixed>>  $documents
     * @return array<int, array{name: string, size: ?string}>
     */
    private function buildStageDocuments(Collection $documents,Array $stageActivities, String $title, Carbon $start, Carbon $end): array
    {
        $keywords = collect([$title])
        ->merge(
            collect($stageActivities)
                ->flatMap(fn ($activity) => [
                    $activity['title'] ?? null,
                    $activity['description'] ?? null,
                ])
        )
        ->filter()
        ->flatMap(fn ($text) => preg_split('/\s+/', Str::lower($text)))
        ->filter(fn ($word) => strlen($word) >= 4)
        ->unique()
        ->values()
        ->all();

        return $documents
            ->filter(function (array $doc) use ($start, $end, $keywords): bool {

            if (empty($doc['created_at'])) {
                return false;
            }

            $inDate = Carbon::parse($doc['created_at'])
                ->between($start, $end->copy()->endOfDay());

            $matchKeyword = Str::contains(
                Str::lower($doc['title'] ?? ''),
                $keywords
            );

            return $inDate && $matchKeyword;
             })
            ->map(fn (array $doc): array => [
                'name' => $doc['title'] ?? 'Dokumen',
                'size' => $doc['files']['size'] ?? null,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Status tahap: upcoming (belum mulai), current (berlangsung), done (lewat &
     * syarat selesai terpenuhi), pending (lewat tapi belum tuntas). Tahap dianggap
     * selesai bila semua aktivitas DAR-nya CLOSED, atau — bila tanpa aktivitas —
     * sudah punya minimal satu dokumen.
     *
     * @param  array<int, array{status: string}>  $activities
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function resolveStageStatus(Carbon $today, Carbon $start, Carbon $end, array $activities, array $documents): string
    {
        if ($today->lt($start)) {
            return 'upcoming';
        }

        if ($today->between($start, $end)) {
            return 'current';
        }

        $closedCount = collect($activities)->where('status', 'CLOSED')->count();

        $isCompleted = count($activities) > 0
            ? $closedCount === count($activities)
            : count($documents) > 0;

        return $isCompleted ? 'done' : 'pending';
    }

    /**
     * Chip ringkasan tahap (jumlah dokumen & progres task DAR).
     *
     * @param  array<int, array{status: string}>  $activities
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, string>
     */
    private function buildStageSignals(array $activities, array $documents): array
    {
        $signals = [];

        if (count($documents) > 0) {
            $signals[] = count($documents).' Dokumen';
        }

        if (count($activities) > 0) {
            $closedCount = collect($activities)->where('status', 'CLOSED')->count();
            $signals[] = $closedCount.'/'.count($activities).' Task DAR selesai';
        }

        return $signals;
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
