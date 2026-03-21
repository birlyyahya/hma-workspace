<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SpectechForm extends Form
{
    #[Validate(['required', 'min:3'])]
    public string $name;


    #[Validate(['required', 'int'])]
    public string $quantity;


    #[Validate(['required', 'int'])]
    public string $price;

    #[Validate(['nullable'])]
    public string $notes;


    public function store($id){
        $this->validate();

        $response = Http::post(env('API_PROJECT') . 'activity-categories', [
            'project_id' => $id,
            'name' => $this->name,
            'qty_total' => $this->quantity,
            'total_nominal' => $this->price,
            'notes' => $this->notes ?? ''
        ]);

        return $response;

    }
}
