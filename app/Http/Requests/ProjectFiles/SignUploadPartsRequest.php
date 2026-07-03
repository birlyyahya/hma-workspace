<?php

namespace App\Http\Requests\ProjectFiles;

class SignUploadPartsRequest extends ProjectFileRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxParts = (int) config('uploads.project_files.max_parts');

        return [
            'key' => $this->keyRules(),
            'part_numbers' => ['required', 'array', 'min:1', 'max:'.(int) config('uploads.project_files.sign_batch_limit')],
            'part_numbers.*' => ['required', 'integer', 'min:1', "max:{$maxParts}"],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'part_numbers.max' => 'Jumlah part per batch melebihi batas.',
            'part_numbers.*.max' => 'Nomor part melebihi batas maksimum.',
        ];
    }
}
