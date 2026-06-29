<?php

namespace App\Services\Concerns;

use Illuminate\Support\Facades\Cache;

trait CachesFlexibly
{
    /**
     * Stale-while-revalidate cache untuk data dari API eksternal.
     *
     * Memakai Cache::flexible() (stampede protection + serve-stale saat refresh)
     * dan menyimpan salinan "last good" secara forever. Saat fetch gagal
     * (exception), kembalikan last good agar UI tidak kosong ketika API down.
     *
     * @param  array<int, string>  $tags
     * @param  array{0: int, 1: int}  $window  [fresh seconds, stale seconds]
     * @param  callable():mixed  $fetch
     */
    protected function rememberFlexible(array $tags, string $key, array $window, callable $fetch, mixed $default = []): mixed
    {
        $lastGoodKey = $key.':last_good';

        try {
            return Cache::tags($tags)->flexible($key, $window, function () use ($tags, $lastGoodKey, $fetch) {
                $data = $fetch();

                Cache::tags($tags)->forever($lastGoodKey, $data);

                return $data;
            });
        } catch (\Throwable $e) {
            return Cache::tags($tags)->get($lastGoodKey, $default);
        }
    }
}
