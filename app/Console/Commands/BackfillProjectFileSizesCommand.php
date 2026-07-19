<?php

namespace App\Console\Commands;

use App\Models\ProjectFileSize;
use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfill ukuran file (project_file_sizes) untuk dokumen lama.
 *
 * Upload baru mencatat ukurannya sendiri saat complete; dokumen yang sudah ada
 * belum punya baris ukuran. Command ini melakukan SATU ListObjectsV2 per
 * project ke MinIO, mencocokkan object key dengan dokumen BEPM, lalu menulis
 * ukurannya ke DB workspace. Read-only terhadap MinIO & BEPM, idempotent
 * (ukuran ditimpa dengan angka terbaru dari MinIO — aman diulang).
 */
class BackfillProjectFileSizesCommand extends Command
{
    protected $signature = 'projectfiles:backfill-sizes {project? : ID project} {--all : Proses semua project BEPM} {--dry-run : Tampilkan rencana tanpa menulis}';

    protected $description = 'Isi ukuran file dari MinIO untuk dokumen lama (satu listing per project)';

    public function handle(ProjectCache $cache, ProjectFileStorage $storage): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('all')) {
            $projectIds = collect($cache->allProjects())
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values();

            $this->info("Memproses {$projectIds->count()} project…");

            $failures = 0;

            foreach ($projectIds as $projectId) {
                if ($this->backfillProject($cache, $storage, $projectId, $dryRun) !== self::SUCCESS) {
                    $failures++;
                }
            }

            if ($failures > 0) {
                $this->error("{$failures} project gagal diproses — lihat peringatan di atas.");

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        if ($this->argument('project') === null) {
            $this->error('Sebutkan ID project atau pakai --all.');

            return self::FAILURE;
        }

        return $this->backfillProject($cache, $storage, (int) $this->argument('project'), $dryRun);
    }

    private function backfillProject(ProjectCache $cache, ProjectFileStorage $storage, int $projectId, bool $dryRun): int
    {
        $project = $cache->projectFor($projectId);

        if ($project === []) {
            $this->error("Project #{$projectId} tidak ditemukan di BEPM.");

            return self::FAILURE;
        }

        $prefix = 'projects_docs/'.project_storage_year($project)."/{$projectId}/";

        try {
            $sizes = $storage->sizesUnder($prefix);
        } catch (\Throwable $e) {
            $this->error("Project #{$projectId}: gagal listing MinIO — {$e->getMessage()}");

            return self::FAILURE;
        }

        $recorded = $missing = 0;

        foreach ($cache->documentsFor($projectId) as $doc) {
            $docId = (int) ($doc['id'] ?? 0);
            $key = $this->keyFromUrl((string) data_get($doc, 'files.url', ''));

            if ($docId <= 0 || ! str_starts_with($key, $prefix)) {
                continue;
            }

            if (! isset($sizes[$key])) {
                $missing++;
                $this->warn("HILANG dok #{$docId}: objek '{$key}' tidak ada di MinIO.");

                continue;
            }

            if (! $dryRun) {
                ProjectFileSize::record($projectId, $docId, (int) $sizes[$key]);
            }

            $recorded++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Project #{$projectId} selesai — dicatat={$recorded} hilang={$missing}");

        return self::SUCCESS;
    }

    private function keyFromUrl(string $url): string
    {
        $decoded = rawurldecode($url);

        if (str_contains($decoded, '/storage/')) {
            $decoded = Str::after($decoded, '/storage/');
        }

        return ltrim($decoded, '/');
    }
}
