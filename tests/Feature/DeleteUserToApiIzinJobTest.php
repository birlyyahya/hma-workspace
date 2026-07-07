<?php

use App\Jobs\DeleteUserToApiIzinJob;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('it sends a DELETE request to the API Izin delete endpoint with the username', function () {
    config(['services.api_izin' => 'http://api-izin.test/']);

    Http::fake([
        'api-izin.test/*' => Http::response(['success' => true], 200),
    ]);

    (new DeleteUserToApiIzinJob('yahya'))->handle();

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && $request->url() === 'http://api-izin.test/global/user/delete/yahya');
});

test('a failed response is logged without throwing so the job is not retried', function () {
    config(['services.api_izin' => 'http://api-izin.test/']);

    Http::fake([
        'api-izin.test/*' => Http::response(['message' => 'User tidak ditemukan'], 404),
    ]);

    (new DeleteUserToApiIzinJob('ghost'))->handle();

    Http::assertSentCount(1);
});

test('a connection failure rethrows so the queue can retry', function () {
    config(['services.api_izin' => 'http://api-izin.test/']);

    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    (new DeleteUserToApiIzinJob('yahya'))->handle();
})->throws(ConnectionException::class);
