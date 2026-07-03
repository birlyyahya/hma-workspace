<?php

namespace App\Http\Requests\ProjectFiles;

class CompleteUploadRequest extends ProjectFileRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxParts = (int) config('uploads.project_files.max_parts');

        return [
            'key' => $this->keyRules(),
            'parts' => ['required', 'array', 'min:1', "max:{$maxParts}"],
            'parts.*.part_number' => ['required', 'integer', 'min:1', "max:{$maxParts}"],
            'parts.*.etag' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'min:5', 'max:255'],
            'admin_doc_category_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parts.required' => 'Daftar part hasil upload wajib dikirim.',
            'parts.*.etag.required' => 'ETag tiap part wajib dikirim.',
            'title.min' => 'Judul dokumen minimal 5 karakter.',
        ];
    }
}
