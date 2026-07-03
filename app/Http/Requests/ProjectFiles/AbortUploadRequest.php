<?php

namespace App\Http\Requests\ProjectFiles;

class AbortUploadRequest extends ProjectFileRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'key' => $this->keyRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.required' => 'Object key wajib dikirim.',
        ];
    }
}
