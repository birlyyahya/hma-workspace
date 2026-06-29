<?php

namespace App\Services\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

trait MakesExternalRequests
{
    /**
     * Standar HTTP client untuk read dari API eksternal.
     * Default: timeout 3s, connect timeout 2s, retry 2x untuk 5xx/connection.
     */
    protected function externalGet(?string $token = null): PendingRequest
    {
        $client = Http::timeout(3)
            ->connectTimeout(2)
            ->retry(2, 200, function ($e) {
                return $e instanceof ConnectionException
                    || (method_exists($e, 'response') && optional($e->response)->serverError());
            }, throw: false);

        return $token ? $client->withToken($token) : $client;
    }
}
