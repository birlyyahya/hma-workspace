<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory, LogsModelActivity;

    protected function activityLogName(): string
    {
        return 'role';
    }

    protected function activityLabel(): string
    {
        return 'Role';
    }

    protected $fillable = [
        'slug',
        'name',
        'description',
        'level',
        'department_id',
        'is_system',
        'can_approve',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'can_approve' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')->withTimestamps();
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissions()->where('name', $name)->exists();
    }
}
