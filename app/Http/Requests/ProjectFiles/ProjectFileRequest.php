<?php

namespace App\Http\Requests\ProjectFiles;

use App\Services\ProjectCache;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Dasar semua request flow upload project files: otorisasi mengikuti aturan
 * project-show (leader / tim internal / scope 'all'), plus rule bersama untuk
 * object key MinIO agar tidak bisa lintas project maupun path traversal.
 */
abstract class ProjectFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $project = app(ProjectCache::class)->projectFor($this->projectId());

        return $user->canAccessProject($project);
    }

    protected function projectId(): int
    {
        return (int) $this->route('project');
    }

    /**
     * Rules untuk object key yang dikirim balik oleh client: wajib berada di
     * bawah prefix project pada URL dan bebas komponen traversal.
     *
     * @return array<int, mixed>
     */
    protected function keyRules(): array
    {
        return [
            'required',
            'string',
            'max:1024',
            function (string $attribute, mixed $value, \Closure $fail) {
                $prefix = 'projects/'.$this->projectId().'/';

                if (! str_starts_with((string) $value, $prefix) || str_contains((string) $value, '..')) {
                    $fail('Object key tidak valid untuk project ini.');
                }
            },
        ];
    }
}
