<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionCache
{
    public static function key(int $userId): string
    {
        return "user:{$userId}:permissions";
    }

    public static function flushForUser(int $userId): void
    {
        Cache::forget(self::key($userId));
    }

    /**
     * Flush cache permission untuk semua user yang punya role ini.
     */
    public static function flushForRole(Role $role): void
    {
        $role->users()->pluck('id')->each(fn ($id) => self::flushForUser((int) $id));
    }

    /**
     * Flush cache permission untuk semua user yang punya permission ini
     * via role apapun.
     */
    public static function flushForPermission(Permission $permission): void
    {
        User::whereHas('role.permissions', fn ($q) => $q->where('permissions.id', $permission->id))
            ->pluck('id')
            ->each(fn ($id) => self::flushForUser((int) $id));
    }
}
