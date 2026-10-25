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
        'signature',
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
        $this->loadMissing('role.department.parent');

        $department = $this->role?->department;

        while ($department !== null) {
            if ($department->code === $departmentCode) {
                return true;
            }

            $department = $department->parent;
        }

        return false;
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

    /**
     * User boleh mengakses sebuah project BEPM bila ia leader-nya, tergabung
     * di tim internal, atau punya view scope 'all' untuk modul project —
     * aturan yang sama dengan canViewProject di halaman project-show.
     *
     * @param  array<string, mixed>  $project  payload project dari ProjectCache::projectFor()
     */
    public function canAccessProject(array $project): bool
    {
        if ($this->viewScopeFor('project') === 'all') {
            return true;
        }

        if (empty($project)) {
            return false;
        }

        $isLeader = (int) ($project['project_leader_id'] ?? 0) === (int) $this->id;

        $isMember = collect($project['support_team_internals'] ?? [])
            ->contains(fn ($member) => (int) ($member['user_id'] ?? 0) === (int) $this->id);

        return $isLeader || $isMember;
    }

    public function assigments()
    {
        return $this->hasMany(TaskAssignments::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get supervisors from the nearest higher role level to the highest level.
     * Roles in the user's department hierarchy are included, along with
     * organization-wide roles that do not belong to a department.
     *
     * @return Collection<int, User>
     */
    public function supervisorChain(): Collection
    {
        $this->loadMissing('role.department.parent');

        $currentLevel = $this->role?->level;

        if ($currentLevel === null) {
            return collect();
        }

        $roles = Role::query()
            ->with(['department', 'users.role.department.parent'])
            ->where('level', '>', $currentLevel)
            ->where(function ($query) {
                $query->whereBetween('level', [60, 99])
                    ->orWhereIn('slug', ['direktur', 'director']);
            })
            ->orderBy('level')
            ->get();

        $staffDepartmentCode = str_starts_with((string) $this->role?->slug, 'staff-')
            ? Str::after((string) $this->role?->slug, 'staff-')
            : null;

        return $roles
            ->filter(fn (Role $role) => $role->department === null
                || $this->isInDepartment($role->department->code)
                || ($staffDepartmentCode !== null && $role->slug === 'spv-'.$staffDepartmentCode))
            ->flatMap(fn (Role $role) => $role->users)
            ->reject(fn (User $user) => $user->is($this))
            ->unique('id')
            ->values();
    }

    /**
     * Resolve the approvers used by an izin PDF according to the organizational hierarchy.
     *
     * @return Collection<int, User>
     */
    public function izinApprovers(): Collection
    {
        $chain = $this->supervisorChain();
        $roleSlug = $this->role?->slug;

        if ($roleSlug === 'manager') {
            $director = $chain->first(fn (User $user) => $user->hasRole(['direktur', 'director', 'gm']));

            return $director ? collect([$director]) : collect();
        }

        if ($roleSlug === 'asmen') {
            $manager = $chain->first(fn (User $user) => $user->hasRole('manager'));

            return $manager ? collect([$manager]) : collect();
        }

        if (str_starts_with((string) $roleSlug, 'spv-')) {
            return collect([
                $chain->first(fn (User $user) => $user->hasRole('asmen')),
                $chain->first(fn (User $user) => $user->hasRole('manager')),
            ])->filter()->unique('id')->values();
        }

        if (str_starts_with((string) $roleSlug, 'staff-')) {
            $departmentCode = $this->role?->department?->code
                ?? Str::after((string) $roleSlug, 'staff-');
            $spvSlug = $departmentCode ? 'spv-'.$departmentCode : null;
            $spv = $spvSlug
                ? $chain->first(fn (User $user) => $user->hasRole($spvSlug))
                : null;
            $firstApprover = $spv ?? $chain->first(fn (User $user) => $user->hasRole('asmen'));

            return collect([
                $firstApprover,
                $chain->first(fn (User $user) => $user->hasRole('manager')),
            ])->filter()->unique('id')->values();
        }

        return $chain->take(2)->values();
    }

    public function directSupervisor(): ?User
    {
        return $this->supervisorChain()->first();
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
