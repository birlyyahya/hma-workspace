<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Form;
use Livewire\WithFileUploads;

class FilesForm extends Form
{
    use WithFileUploads;

    #[Validate('required|min:5')]
    public $title = '';

    #[Validate('required|file')]
    public $file;

    #[Validate('required|int')]
    public $category = '';

    public function store($id)
    {
        $this->validate();

        $response = Http::attach(
            'file',
            file_get_contents($this->file->getRealPath()),
            $this->file->getClientOriginalName()
        )->post(env('API_PROJECT') . 'admin-docs', [
            'title' => $this->title,
            'admin_doc_category_id' => $this->category,
            'project_id' => $id
        ]);

        return $response;
    }
}
