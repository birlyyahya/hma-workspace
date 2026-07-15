<?php

if (! function_exists('getErrorMessages')) {
    function getErrorMessages($errors)
    {
        return collect($errors)
            ->flatten()
            ->filter()
            ->implode(' ');
    }
}

if (! function_exists('formatFileSize')) {
    function formatFileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }

        if ($bytes >= 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
        }

        if ($bytes >= 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}

if (! function_exists('fileExtMeta')) {
    /**
     * @return array{bg:string, text:string, label:string, icon:string}
     */
    function fileExtMeta(string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => ['bg' => 'bg-red-50', 'text' => 'text-red-600', 'label' => 'PDF', 'icon' => 'document-text'],
            'doc', 'docx' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'label' => 'DOC', 'icon' => 'document'],
            'xls', 'xlsx', 'csv' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'label' => strtoupper($ext), 'icon' => 'table-cells'],
            'ppt', 'pptx' => ['bg' => 'bg-orange-50', 'text' => 'text-orange-600', 'label' => strtoupper($ext), 'icon' => 'presentation-chart-bar'],
            'zip', 'rar', '7z' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'label' => strtoupper($ext), 'icon' => 'archive-box'],
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' => ['bg' => 'bg-purple-50', 'text' => 'text-purple-600', 'label' => 'IMG', 'icon' => 'photo'],
            default => ['bg' => 'bg-zinc-100', 'text' => 'text-zinc-600', 'label' => strtoupper($ext) ?: 'FILE', 'icon' => 'document'],
        };
    }
}

if (! function_exists('isImageFile')) {
    function isImageFile(string $filename, ?string $mime = null): bool
    {
        if ($mime && str_starts_with($mime, 'image/')) {
            return true;
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true);
    }
}

if (! function_exists('project_storage_year')) {
    /**
     * Tahun project untuk layout penyimpanan MinIO
     * (projects_docs/{tahun}/{id}/...). Diambil dari start_date; fallback
     * berurutan ke contract_date lalu created_at. Satu sumber kebenaran dipakai
     * controller upload maupun komponen file-manager agar path selalu sama.
     *
     * @param  array<string, mixed>  $project
     */
    function project_storage_year(array $project): string
    {
        $date = $project['start_date'] ?? $project['contract_date'] ?? $project['created_at'] ?? null;

        return $date ? \Illuminate\Support\Carbon::parse($date)->format('Y') : 'tanpa-tahun';
    }
}

if (! function_exists('project_doc_keywords')) {
    /**
     * Kata kunci otomatis dokumen (parameter wajib BEPM): nama & kode project
     * bila tersedia, segmen folder tempat file disimpan, lalu nama file tanpa
     * ekstensi. Selalu berisi minimal satu elemen (nama file). Dipakai bersama
     * oleh endpoint upload dan command rekonsiliasi agar konsisten.
     *
     * @param  array<string, mixed>  $project
     * @return array<int, string>
     */
    function project_doc_keywords(array $project, int $projectId, string $key): array
    {
        $year = project_storage_year($project);
        $relative = \Illuminate\Support\Str::after($key, "projects_docs/{$year}/{$projectId}/");
        $segments = explode('/', $relative);
        $filename = array_pop($segments);

        return collect([
            data_get($project, 'name'),
            data_get($project, 'code'),
            ...$segments,
            pathinfo((string) $filename, PATHINFO_FILENAME),
        ])
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value) => (string) $value)
            ->unique()
            ->values()
            ->all();
    }
}

if (! function_exists('colorFromSeed')) {
    function colorFromSeed($seed)
    {
        $colors = [
            'bg-red-100 text-red-600 border border-red-300',
            'bg-blue-100 text-blue-600 border border-blue-300',
            'bg-purple-100 text-purple-600 border border-purple-300',
            'bg-yellow-100 text-yellow-600 border border-yellow-300',
            'bg-amber-100 text-amber-600 border border-amber-300',
            'bg-pink-100 text-pink-600 border border-pink-300',
        ];

        return $colors[crc32($seed) % count($colors)];
    }
}
