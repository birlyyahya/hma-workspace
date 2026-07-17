<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penempatan dokumen BEPM (`doc_id`) di folder virtual workspace. Folder murni
 * konsep frontend — object key MinIO tidak memuat path folder. Tidak ada baris
 * berarti file berada di root project. Baris ikut terhapus saat foldernya
 * dihapus (FK cascade).
 */
class ProjectFolderFile extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFolderFileFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'doc_id',
        'project_folder_id',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(ProjectFolder::class, 'project_folder_id');
    }

    /**
     * Tempatkan dokumen di sebuah folder (null = root). Idempotent — dipakai
     * alur upload, pindah file, dan backfill.
     */
    public static function place(int $projectId, int $docId, ?int $folderId): void
    {
        if ($folderId === null) {
            static::query()->where('doc_id', $docId)->delete();

            return;
        }

        static::query()->updateOrCreate(
            ['doc_id' => $docId],
            ['project_id' => $projectId, 'project_folder_id' => $folderId],
        );
    }
}
