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
     * SEBELUM trait apa pun (RefreshDatabase, dll) sempat menyentuh DB —
     * RefreshDatabase memigrasi DB di dalam parent::setUp() itu sendiri,
     * jadi guard WAJIB berjalan sebelum parent::setUp() dipanggil sama
     * sekali. Ini berarti aplikasi Laravel belum ter-boot di titik ini,
     * sehingga config() tidak tersedia — pakai file cache & env() mentah.
     */
    protected function setUp(): void
    {
        $this->guardAgainstNonTestDatabase();

        parent::setUp();
    }

    private function guardAgainstNonTestDatabase(): void
    {
        $configCache = __DIR__.'/../bootstrap/cache/config.php';

        if (file_exists($configCache)) {
            throw new RuntimeException(
                "Menolak menjalankan test: config cache ({$configCache}) masih ada. ".
                'Config yang di-cache mengabaikan environment testing dari phpunit.xml dan bisa '.
                'membuat RefreshDatabase menyentuh database asli, persis seperti insiden 2026-07-28. '.
                'Jalankan `php artisan config:clear` lalu ulangi, atau gunakan `composer test`.'
            );
        }

        $connection = env('DB_CONNECTION');
        $database = env('DB_DATABASE');

        $isIsolatedTestDatabase = $connection === 'sqlite'
            && in_array($database, [':memory:', null], true);

        if (! $isIsolatedTestDatabase) {
            throw new RuntimeException(
                "Menolak menjalankan test: environment DB_CONNECTION saat ini adalah '{$connection}' ".
                "dengan DB_DATABASE '{$database}', bukan sqlite in-memory yang diharapkan phpunit.xml. ".
                'Jalankan test lewat `php artisan test` atau `composer test` agar env testing termuat.'
            );
        }
    }
}
