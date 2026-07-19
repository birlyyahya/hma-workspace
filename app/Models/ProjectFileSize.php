<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Ukuran objek MinIO (bytes) milik sebuah dokumen BEPM. Dicatat sekali saat
 * upload selesai (atau lewat command projectfiles:backfill-sizes untuk data
 * lama) supaya file-manager tidak perlu ListObjectsV2 ke MinIO tiap render.
 */
class ProjectFileSize extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFileSizeFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'doc_id',
        'size_bytes',
    ];

    /**
     * Catat/perbarui ukuran sebuah dokumen. Idempotent.
     */
    public static function record(int $projectId, int $docId, int $sizeBytes): void
    {
        static::query()->updateOrCreate(
            ['doc_id' => $docId],
            ['project_id' => $projectId, 'size_bytes' => $sizeBytes],
        );
    }
}
