<?php

namespace App\Livewire\Forms;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Validate;
use Livewire\Form;

class ActivityForm extends Form
{
    public $activity;
    public $start_date;
    public $end_date;
    public $status = 1;
    public $team;
    public $project_id;
    public $spectech_id;
    public $isproject = false;

    protected function rules()
    {
        $rules = [
            'activity' => ['required', 'min:3'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'status' => ['required', 'integer'],
            'team' => ['nullable', 'array'],
        ];

        if ($this->isproject) {
            $rules['project_id'] = ['required', 'integer'];
            $rules['spectech_id'] = ['required', 'integer'];
        }

        return $rules;
    }

    public function store($id)
    {
        if ($this->isproject) {
            $this->project_id = $id;
        }

        $this->validate();

        $response = Http::post(env('API_IZIN') . '/global/dar/create', [
            'user_id' => Auth::user()->id,
            'activity' => $this->activity,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'team' => $this->team,
            'project_category_id' => $this->spectech_id,
            'project_id' => $this->project_id,
        ]);

        return $response;

    }
}
