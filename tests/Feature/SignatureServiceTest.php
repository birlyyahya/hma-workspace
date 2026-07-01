<?php

use App\Services\IzinCache;
use App\Services\IzinWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

test('userSignature returns the stored signature string', function () {
    Http::fake(['*/global/user/get-user/*' => Http::response(['success' => true, 'data' => ['signature' => 'sig.png']], 200)]);

    expect((new IzinCache('http://izin.test'))->userSignature('budi'))->toBe('sig.png');
});

test('userSignature returns null when the API reports failure', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect((new IzinCache('http://izin.test'))->userSignature('budi'))->toBeNull();
});

test('updateSignature succeeds on body success and posts the base64 payload', function () {
    Http::fake(['*/global/user/update-signature/*' => Http::response(['success' => true], 200)]);

    $writer = new IzinWriter('http://izin.test', new IzinCache('http://izin.test'));
    $result = $writer->updateSignature('budi', 'data:image/png;base64,AAA');

    expect($result['ok'])->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/global/user/update-signature/budi')
        && $request['base64'] === 'data:image/png;base64,AAA');
});

test('updateSignature returns ok=false when body success is falsey', function () {
    Http::fake(['*/global/user/update-signature/*' => Http::response(['success' => false, 'message' => 'nope'], 200)]);

    $writer = new IzinWriter('http://izin.test', new IzinCache('http://izin.test'));

    expect($writer->updateSignature('budi', 'x')['ok'])->toBeFalse();
});
