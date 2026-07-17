<?php

namespace App\Console\Commands;

use App\Models\ProjectFolder;
use App\Models\ProjectFolderFile;
use App\Services\ProjectCache;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Backfill mapping folder virtual (project_folder_files) dari object key lama.
 *
 * Sebelum konsep folder virtual, lokasi file dikodekan di object key
 * (projects_docs/{tahun}/{project}/{path folder}/{nama}). Command ini membaca
 * segmen folder dari key tiap dokumen BEPM lalu mencocokkannya dengan pohon
 * folder yang SUDAH ADA di DB workspace — TANPA memindahkan objek MinIO dan
 * TANPA menulis ke BEPM. Dokumen yang path-nya tidak cocok dengan folder mana
 * pun dilaporkan dan dibiarkan di root (buat foldernya lalu jalankan ulang).
 * Mapping yang sudah ada tidak ditimpa (aman diulang; menghormati pemindahan
 * manual setelah backfill).
 */
class BackfillProjectFolderFilesCommand extends Command
{
    protected $signature = 'projectfiles:backfill-folders {project : ID project} {--dry-run : Tampilkan rencana tanpa menulis mapping}';

    protected $description = 'Isi mapping folder virtual dari path object key lama (tanpa menyentuh MinIO/BEPM)';

    public function handle(ProjectCache $cache): int
    {
        $projectId = (int) $this->argument('project');
        $project = $cache->projectFor($projectId);

        if ($project === []) {
            $this->error("Project #{$projectId} tidak ditemukan di BEPM.");

            return self::FAILURE;
        }

        $prefix = 'projects_docs/'.project_storage_year($project)."/{$projectId}/";
        $folderIdByPath = $this->folderIdsByPath($projectId);
        $dryRun = (bool) $this->option('dry-run');

        $alreadyMapped = ProjectFolderFile::query()
            ->where('project_id', $projectId)
            ->pluck('doc_id')
            ->flip();

        $mapped = $skipped = $root = $unmatched = 0;

        foreach ($cache->documentsFor($projectId) as $doc) {
            $docId = (int) ($doc['id'] ?? 0);
            $key = $this->keyFromUrl((string) data_get($doc, 'files.url', ''));

            if ($docId <= 0 || ! str_starts_with($key, $prefix)) {
                continue;
            }

            if (isset($alreadyMapped[$docId])) {
                $skipped++;

                continue;
            }

            $relative = substr($key, strlen($prefix));

            if (! str_contains($relative, '/')) {
                $root++;

                continue;
            }

            $dirPath = Str::beforeLast($relative, '/');
            $folderId = $folderIdByPath[$dirPath] ?? null;

            if ($folderId === null) {
                $unmatched++;
                $this->warn("TAK COCOK dok #{$docId}: path '{$dirPath}' tidak punya folder di DB — dibiarkan di root.");

                continue;
            }

            $this->line("MAP dok #{$docId}: '{$dirPath}' -> folder #{$folderId}");

            if (! $dryRun) {
                ProjectFolderFile::place($projectId, $docId, $folderId);
            }

            $mapped++;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Selesai — dipetakan={$mapped} sudah-ada={$skipped} root={$root} tak-cocok={$unmatched}");

        return self::SUCCESS;
    }

    /**
     * Map path folder ("Kontrak/Addendum") => folder id untuk satu project.
     *
     * @return array<string, int>
     */
    private function folderIdsByPath(int $projectId): array
    {
        $byParent = ProjectFolder::query()
            ->where('project_id', $projectId)
            ->get(['id', 'parent_id', 'name'])
            ->groupBy('parent_id');

        $paths = [];

        $walk = function (?int $parentId, string $prefix) use (&$walk, &$paths, $byParent): void {
            foreach ($byParent->get($parentId, collect()) as $folder) {
                $paths[$prefix.$folder->name] = (int) $folder->id;
                $walk($folder->id, $prefix.$folder->name.'/');
            }
        };
        $walk(null, '');

        return $paths;
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
