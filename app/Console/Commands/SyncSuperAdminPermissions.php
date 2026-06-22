<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Services\PermissionCache;
use Illuminate\Console\Command;

class SyncSuperAdminPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync-super-admin {--slug=super-admin : Slug role super-admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Memberikan seluruh permission yang ada ke role super-admin (idempoten, aman dijalankan berulang).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $slug = (string) $this->option('slug');

        $role = Role::where('slug', $slug)->first();

        if (! $role) {
            $this->error("Role dengan slug '{$slug}' tidak ditemukan.");

            return self::FAILURE;
        }

        $permissionIds = Permission::query()->pluck('id');

        if ($permissionIds->isEmpty()) {
            $this->warn('Belum ada permission sama sekali di tabel permissions.');

            return self::SUCCESS;
        }

        $changes = $role->permissions()->syncWithoutDetaching($permissionIds);

        PermissionCache::flushForRole($role);

        $this->info(sprintf(
            'Super-admin (%s): %d permission total, %d baru ditambahkan.',
            $role->slug,
            $permissionIds->count(),
            count($changes['attached']),
        ));

        return self::SUCCESS;
    }
}
