<?php

namespace App\Http\Requests\ProjectFiles;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InitiateUploadRequest extends ProjectFileRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.(int) config('uploads.project_files.max_file_size')],
            'mime' => ['required', 'string', 'max:255'],
            'folder_id' => [
                'nullable',
                'integer',
                Rule::exists('project_folders', 'id')->where('project_id', $this->projectId()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filename.required' => 'Nama file wajib diisi.',
            'size.max' => 'Ukuran file melebihi batas maksimum.',
            'folder_id.exists' => 'Folder tidak ditemukan di project ini.',
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $filename = (string) $this->input('filename');
                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                $allowed = (array) config('uploads.project_files.allowed_extensions');

                if (! in_array($extension, $allowed, true)) {
                    $validator->errors()->add('filename', "Ekstensi .{$extension} tidak diizinkan.");
                }

                $partSize = (int) config('uploads.project_files.part_size');
                $maxParts = (int) config('uploads.project_files.max_parts');

                if ((int) ceil((int) $this->input('size') / $partSize) > $maxParts) {
                    $validator->errors()->add('size', 'File terlalu besar untuk jumlah part maksimum.');
                }
            },
        ];
    }
}
