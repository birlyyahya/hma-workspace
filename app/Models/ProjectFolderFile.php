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
}
