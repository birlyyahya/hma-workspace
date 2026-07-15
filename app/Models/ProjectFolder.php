<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Folder logis untuk project files. `project_id` merujuk project di BEPM
 * (eksternal). Path fisik object MinIO diturunkan dari rangkaian folder ini,
 * jadi struktur pohon harus selalu bebas siklus (dijaga di hook saving).
 */
class ProjectFolder extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFolderFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'parent_id',
        'name',
        'status',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (ProjectFolder $folder) {
            if ($folder->wouldCreateCycle()) {
                throw new \LogicException("Parent folder membentuk siklus untuk folder [{$folder->name}].");
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Path logis folder dari root, tanpa leading/trailing slash.
     * Contoh: "Dokumen Kontrak/Addendum".
     */
    public function path(): string
    {
        $segments = [$this->name];
        $seen = [$this->id];
        $current = $this->parent;

        while ($current !== null) {
            if (in_array($current->id, $seen, true)) {
                throw new \LogicException("Struktur folder #{$this->id} mengandung siklus.");
            }

            $seen[] = $current->id;
            array_unshift($segments, $current->name);
            $current = $current->parent;
        }

        return implode('/', $segments);
    }

    /**
     * True bila parent_id yang akan disimpan membuat folder menjadi
     * ancestor dari dirinya sendiri (termasuk parent = diri sendiri).
     */
    public function wouldCreateCycle(): bool
    {
        if ($this->parent_id === null) {
            return false;
        }

        if ($this->exists && (int) $this->parent_id === (int) $this->id) {
            return true;
        }

        $ancestorId = $this->parent_id;
        $seen = [];

        while ($ancestorId !== null) {
            if ($this->exists && (int) $ancestorId === (int) $this->id) {
                return true;
            }

            if (in_array((int) $ancestorId, $seen, true)) {
                return true;
            }

            $seen[] = (int) $ancestorId;
            $ancestorId = self::query()->whereKey($ancestorId)->value('parent_id');
        }

        return false;
    }
}
