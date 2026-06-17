<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;

class FilesForm extends Form
{
    use WithFileUploads;

    #[Validate('required|min:5')]
    public $title = '';

    // When uploading via browser-driven chunking proxy, `uploadedFilename` is set and `file` stays null.
    #[Validate('nullable|file')]
    public $file;

    #[Validate('required|int')]
    public $category = '';

    #[Validate('nullable|string')]
    public $uploadedFilename = null;

    #[Validate('nullable|string')]
    public $originalName = null;

    public function store($id)
    {
        $this->validate();

        if (! $this->file && ! $this->uploadedFilename) {
            throw ValidationException::withMessages([
                'form.file' => 'File wajib diisi.',
            ]);
        }

        $base = rtrim((string) config('services.api_project'), '/').'/';
        $adminDocsEndpoint = $base.'admin-docs';

        // If the file already got uploaded (chunked) elsewhere, only finalize document creation here.
        if ($this->uploadedFilename) {
            $originalName = (string) ($this->originalName ?: $this->uploadedFilename);

            return Http::timeout(120)->post($adminDocsEndpoint, [
                'title' => $this->title,
                'admin_doc_category_id' => $this->category,
                'project_id' => $id,
                'filename' => $this->uploadedFilename,
                'file' => $this->uploadedFilename,
                'original_name' => $originalName,
            ]);
        }

        return $this->uploadedFilename ?: Http::timeout(30)->post($adminDocsEndpoint, []);
    }

    public function delete($id)
    {
        $response = Http::delete(config('services.api_project').'admin-docs/'.$id);

        return $response;
    }
}
