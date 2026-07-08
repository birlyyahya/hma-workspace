<?php

namespace App\Livewire\Forms;

use App\Services\DarWriter;
use Illuminate\Support\Facades\Auth;
use Livewire\Form;

class ActivityForm extends Form
{
    public $activity;

    public $date;

    public $start_date;

    public $end_date;

    public $status = 1;

    public $team;

    public $team_user;

    public $description;

    public $project_id;

    public $timelines_id;

    public $isproject = false;

    protected function rules()
    {
        $rules = [
            'activity' => ['required', 'min:3'],
            'date' => ['nullable', 'date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'integer'],
            'team' => ['nullable', 'array'],
            'team_user' => ['nullable', 'array'],
        ];

        if ($this->isproject) {
            $rules['project_id'] = ['required', 'integer'];
            $rules['timelines_id'] = ['nullable', 'integer'];
        }

        return $rules;
    }

    public function resetForm()
    {
        $now = now()->format('Y-m-d\\TH:i');

        $this->date = $now;
        $this->start_date = $now;
        $this->end_date = $now;
        $this->activity = '';
        $this->description = '';

        $this->status = 1;
        $this->project_id = null;
        $this->timelines_id = null;
        $this->team_user = [];
    }

    /**
     * @return array{ok: bool, body: array<string, mixed>, status: ?int, error: ?string}
     */
    public function store($id): array
    {
        if ($this->isproject) {
            $this->project_id = $id;
        } else {
            $this->project_id = null;
            $this->timelines_id = null;
        }

        $this->validate();

        $teamUser = ! empty($this->team_user) ? $this->team_user : ($this->team ?? []);
        $date = $this->date ?: $this->start_date;

        return app(DarWriter::class)->createActivity([
            'user_id' => Auth::user()->id,
            'activity' => $this->activity,
            'description' => $this->description,
            'date' => $date,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            // Keep backward compat: API historically used `team`, but newer payload uses `team_user`.
            'team' => $teamUser,
            'team_user' => $teamUser,
            'status' => $this->status,
            'project_id' => $this->project_id,
            'project_category_id' => $this->timelines_id,
        ]);
    }
}
