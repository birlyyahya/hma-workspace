<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory, LogsModelActivity;

    protected function activityLogName(): string
    {
        return 'permission';
    }

    protected function activityLabel(): string
    {
        return 'Permission';
    }

    protected $fillable = [
        'name',
        'module',
        'action',
        'label',
        'description',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')->withTimestamps();
    }
}
