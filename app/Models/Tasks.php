<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasks extends Model
{
    protected $fillable = [
        'name',
        'description',
        'due_date',
        'priority',
        'status',
        'assigned_by',
        'updated_by',
    ];

    protected $casts = [
    'due_date' => 'datetime',
];

    public function assignments()
    {
        return $this->hasMany(TaskAssignments::class, 'task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
