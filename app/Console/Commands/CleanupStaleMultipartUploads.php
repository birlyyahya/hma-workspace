<?php

namespace App\Console\Commands;

use App\Services\ProjectFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupStaleMultipartUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'project-files:cleanup-multipart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Abort multipart upload MinIO yang menggantung lebih tua dari batas stale_multipart_hours';

    public function handle(ProjectFileStorage $storage): int
    {
        $olderThanHours = (int) config('uploads.project_files.stale_multipart_hours');

        try {
            $aborted = $storage->abortStaleMultipartUploads($olderThanHours);
        } catch (\Throwable $e) {
            $this->error("Gagal membersihkan multipart stale: {$e->getMessage()}");

            return self::FAILURE;
        }

        Log::info('Cleanup multipart stale selesai', [
            'aborted' => $aborted,
            'older_than_hours' => $olderThanHours,
        ]);

        $this->info("Multipart stale (> {$olderThanHours} jam) di-abort: {$aborted}.");

        return self::SUCCESS;
    }
}
