<?php

namespace App\Console\Commands;

use App\Services\ProjectCache;
use App\Services\ProjectFileStorage;
use App\Services\ProjectWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Selaraskan path `file` dokumen BEPM dengan lokasi objek sebenarnya di MinIO.
 *
 * Jaring pengaman untuk kasus "objek sudah pindah di MinIO tetapi BEPM belum
 * ter-update" (proses terputus, job gagal permanen). MinIO adalah sumber
 * kebenaran: untuk tiap dokumen yang object key BEPM-nya tidak ditemukan,
 * dicari objek dengan nama berkas sama di bawah prefix project; bila cocok
 * unik, path BEPM di-PATCH ke lokasi objek yang benar.
 */
class ReconcileProjectFilesCommand extends Command
{
    protected $signature = 'projectfiles:reconcile {project : ID project} {--dry-run : Tampilkan rencana tanpa mengubah BEPM}';

    protected $description = 'Selaraskan path dokumen BEPM dengan lokasi objek di MinIO';

    public function handle(ProjectCache $cache, ProjectWriter $writer, ProjectFileStorage $storage): int
    {
        $projectId = (int) $this->argument('project');
        $project = $cache->projectFor($projectId);

        if ($project === []) {
            $this->error("Project #{$projectId} tidak ditemukan di BEPM.");

            return self::FAILURE;
        }

        $year = project_storage_year($project);
        $prefix = "projects_docs/{$year}/{$projectId}/";

        $objects = collect($storage->listUnder($prefix));
        $byBasename = $objects->groupBy(fn (string $key) => basename($key));
        $dryRun = (bool) $this->option('dry-run');

        $registered = collect($cache->documentsFor($projectId))
            ->map(fn (array $doc) => $this->keyFromUrl((string) data_get($doc, 'files.url', '')))
            ->filter()
            ->values();

        $ok = $fixed = $ambiguous = $missing = 0;

        foreach ($cache->documentsFor($projectId) as $doc) {
            $key = $this->keyFromUrl((string) data_get($doc, 'files.url', ''));

            if ($objects->contains($key)) {
                $ok++;

                continue;
            }

            $candidates = $byBasename->get(basename($key), collect());

            if ($candidates->count() === 1) {
                $newKey = (string) $candidates->first();
                $this->line("FIX dok #{$doc['id']}: {$key} -> {$newKey}");

                if ($dryRun || $writer->updateDoc((int) $doc['id'], ['file' => $newKey], $projectId)['ok']) {
                    $fixed++;
                    $registered->push($newKey);
                } else {
                    $this->warn("  gagal PATCH dok #{$doc['id']}");
                }
            } elseif ($candidates->count() > 1) {
                $ambiguous++;
                $this->warn("AMBIGU dok #{$doc['id']}: '{$key}' punya ".$candidates->count().' kandidat objek dengan nama sama.');
            } else {
                $missing++;
                $this->warn("HILANG dok #{$doc['id']}: objek '{$key}' tidak ada di MinIO.");
            }
        }

        [$registeredCount, $unregisteredFailed] = $this->registerOrphanObjects(
            $objects, $registered, $project, $projectId, $cache, $writer, $dryRun
        );

        $this->info(($dryRun ? '[dry-run] ' : '')."Selesai — ok={$ok} diperbaiki={$fixed} didaftarkan={$registeredCount} ambigu={$ambiguous} hilang={$missing} gagal-daftar={$unregisteredFailed}");

        return self::SUCCESS;
    }

    /**
     * Daftarkan objek MinIO yang belum tertaut ke dokumen BEPM mana pun —
     * menutup kasus upload yang objeknya sudah masuk tapi registrasi tak pernah
     * selesai (mis. retry habis). Metadata diturunkan seperti alur upload.
     *
     * @param  \Illuminate\Support\Collection<int, string>  $objects
     * @param  \Illuminate\Support\Collection<int, string>  $registered
     * @param  array<string, mixed>  $project
     * @return array{0: int, 1: int}
     */
    private function registerOrphanObjects(
        \Illuminate\Support\Collection $objects,
        \Illuminate\Support\Collection $registered,
        array $project,
        int $projectId,
        ProjectCache $cache,
        ProjectWriter $writer,
        bool $dryRun,
    ): array {
        $orphans = $objects->reject(fn (string $key) => $registered->contains($key))->values();

        if ($orphans->isEmpty()) {
            return [0, 0];
        }

        $first = collect($cache->docCategories())->first();
        $categoryId = isset($first['id']) ? (int) $first['id'] : null;

        if ($categoryId === null) {
            foreach ($orphans as $key) {
                $this->warn("TAK TERDAFTAR '{$key}': tidak ada kategori dokumen BEPM untuk registrasi.");
            }

            return [0, $orphans->count()];
        }

        $registeredCount = $failed = 0;

        foreach ($orphans as $key) {
            $name = basename($key);
            $this->line("DAFTAR objek yatim: {$key}");

            if ($dryRun) {
                $registeredCount++;

                continue;
            }

            $result = $writer->registerDocument($projectId, [
                'title' => pathinfo($name, PATHINFO_FILENAME),
                'admin_doc_category_id' => $categoryId,
                'filename' => $key,
                'original_name' => $name,
                'keyword' => project_doc_keywords($project, $projectId, $key),
            ]);

            $result['ok'] ? $registeredCount++ : $failed++;
        }

        return [$registeredCount, $failed];
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
