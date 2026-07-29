<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard tegas: pastikan test TIDAK PERNAH bisa menyentuh database asli.
     *
     * Insiden 2026-07-28: cache config yang basi membuat RefreshDatabase
     * menjalankan migrate:fresh ke database MySQL `hma_workspace` yang
     * asli (bukan sqlite :memory: yang seharusnya dipakai untuk testing),
     * menghapus seluruh data tanpa backup. Guard ini menghentikan proses
     * SEBELUM trait apa pun (RefreshDatabase, dll) sempat menyentuh DB,
     * terlepas dari cara test dijalankan atau apakah config sempat di-cache.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonTestDatabase();

        parent::setUp();
    }

    private function guardAgainstNonTestDatabase(): void
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        $isIsolatedTestDatabase = $connection === 'sqlite'
            && in_array($database, [':memory:', null], true);

        if (! $isIsolatedTestDatabase) {
            throw new RuntimeException(
                "Menolak menjalankan test: koneksi database saat ini adalah '{$connection}' ".
                "dengan database '{$database}', bukan sqlite in-memory yang diharapkan phpunit.xml. ".
                'Ini kemungkinan besar disebabkan config cache yang basi (bootstrap/cache/config.php). '.
                'Jalankan `php artisan config:clear` lalu ulangi, atau gunakan `composer test`.'
            );
        }
    }
}
