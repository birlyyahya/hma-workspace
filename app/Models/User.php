<?php

namespace App\Models;

use App\Models\Concerns\LogsModelActivity;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, LogsModelActivity, Notifiable;

    protected function activityLogName(): string
    {
        return 'user';
    }

    protected function activityLabel(): string
    {
        return 'User';
    }

    /**
     * @return array<int, string>
     */
    protected function activityLogExcept(): array
    {
        return ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'role_id',
        'no_telp',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'external_id' => 'array',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function getLevelAttribute()
    {
        return $this->role?->level;
    }

    public function isInDepartment(string $departmentCode): bool
    {
        $department = $this->role?->department;

        return $department?->code === $departmentCode
            || $department?->parent?->code === $departmentCode;
    }

    /**
     * Resolusi view scope untuk sebuah modul berdasarkan permission.
     * Urutan prioritas: all > department > own. Super-admin selalu 'all'.
     * Default 'own' jika role tidak punya permission view khusus untuk modul.
     */
    public function viewScopeFor(string $module): string
    {
        if ($this->hasRole('super-admin')) {
            return 'all';
        }

        foreach (['all', 'department', 'own'] as $scope) {
            if ($this->hasPermission("{$module}.view.{$scope}")) {
                return $scope;
            }
        }

        return 'own';
    }

    public function assigments()
    {
        return $this->hasMany(TaskAssignments::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions(): Collection
    {
        return $this->role?->permissions ?? collect();
    }

    public function hasPermission(string $name): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        return $this->permissions()->contains('name', $name);
    }

    public function hasAnyPermission(array $names): bool
    {
        if ($this->hasRole('super-admin')) {
            return true;
        }

        $permissionNames = $this->permissions()->pluck('name');

        return $permissionNames->intersect($names)->isNotEmpty();
    }

    public function hasRole(string|array $slug): bool
    {
        $current = $this->role?->slug;

        if ($current === null) {
            return false;
        }

        return \is_array($slug) ? \in_array($current, $slug, true) : $current === $slug;
    }
}
