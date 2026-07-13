<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait MakesExternalRequests
{
    /**
     * HTTP client untuk operasi READ ke API eksternal.
     * - Timeout default pendek (3s) — read seharusnya cepat.
     * - Retry 5xx + connection error (aman karena GET idempotent).
     *
     * Untuk endpoint dengan payload besar, panggil dengan timeout custom:
     * $this->externalRead(timeout: 15).
     */
    protected function externalRead(int $timeout = 3, int $connect = 2, ?string $token = null): PendingRequest
    {
        $client = Http::timeout($timeout)
            ->connectTimeout($connect)
            ->retry(2, 200, function ($e) {
                return $e instanceof ConnectionException
                    || (method_exists($e, 'response') && optional($e->response)->serverError());
            }, throw: false);

        return $token ? $client->withToken($token) : $client;
    }

    /**
     * HTTP client untuk operasi WRITE ke API eksternal (POST/PUT/DELETE).
     * - Timeout default lebih panjang (15s) — write biasanya butuh server processing.
     * - Retry HANYA connection error — JANGAN retry 5xx karena POST/PUT non-idempotent
     *   bisa menghasilkan side effect ganda (komentar duplikat, file upload double, dll).
     * - DELETE sebenarnya idempotent tapi tetap pakai policy yang sama untuk konsistensi;
     *   API DARBE harus mentolerir DELETE pada resource yang sudah dihapus (404 = success).
     */
    protected function externalWrite(int $timeout = 15, int $connect = 3, ?string $token = null): PendingRequest
    {
        $client = Http::timeout($timeout)
            ->connectTimeout($connect)
            ->retry(2, 200, function ($e) {
                return $e instanceof ConnectionException;
            }, throw: false);

        return $token ? $client->withToken($token) : $client;
    }
}
