<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Membungkus Spatie LogsActivity dengan konvensi aplikasi:
 * log_name per-domain, deskripsi berbahasa Indonesia, dan hanya
 * mencatat atribut yang berubah. Model yang memakai trait ini cukup
 * meng-override method kecil di bawah untuk menyesuaikan domainnya.
 */
trait LogsModelActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName($this->activityLogName())
            ->logOnly($this->activityLogAttributes())
            ->logExcept($this->activityLogExcept())
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $label = $this->activityLabel();

        return match ($eventName) {
            'created' => "{$label} dibuat",
            'updated' => "{$label} diperbarui",
            'deleted' => "{$label} dihapus",
            'restored' => "{$label} dipulihkan",
            default => "{$label} {$eventName}",
        };
    }

    protected function activityLogName(): string
    {
        return 'default';
    }

    protected function activityLabel(): string
    {
        return class_basename($this);
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogAttributes(): array
    {
        return ['*'];
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogExcept(): array
    {
        return [];
    }
}
