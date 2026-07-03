<?php

use App\Services\CaCache;
use App\Services\CaWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeCaWriter(): CaWriter
{
    return new CaWriter('http://api.test', new CaCache('http://api.test'));
}

test('createTransactionCaPl posts multipart with the attached bukti and succeeds on HTTP 2xx', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi' => Http::response(['data' => ['id' => 5]], 201)]);

    $result = makeCaWriter()->createTransactionCaPl('CA-PL-01', [
        'jumlah' => 50000,
        'kategori' => 'Transport',
        'bukti' => ['contents' => 'BIN', 'name' => 'nota.png'],
    ]);

    expect($result['ok'])->toBeTrue()
        ->and($result['error'])->toBeNull();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/ca/CA-PL-01/transaksi')
        && $request->isMultipart()
        && collect($request->data())->contains(fn ($f) => ($f['name'] ?? null) === 'bukti'));
});

test('createTransactionCaPl succeeds when body.success is true even on HTTP 200 masking', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi' => Http::response(['success' => true], 200)]);

    expect(makeCaWriter()->createTransactionCaPl('CA-PL-01', ['jumlah' => 1])['ok'])->toBeTrue();
});

test('createTransactionCaPl fails when body.success is false and status is 4xx', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi' => Http::response(['success' => false, 'message' => 'invalid'], 422)]);

    $result = makeCaWriter()->createTransactionCaPl('CA-PL-01', ['jumlah' => 1]);

    expect($result['ok'])->toBeFalse()
        ->and($result['body']['message'])->toBe('invalid');
});

test('updateTransactionCaPl posts to the transaksi id with _method=PUT and succeeds', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi/9' => Http::response(['success' => true], 200)]);

    $result = makeCaWriter()->updateTransactionCaPl('CA-PL-01', 9, ['jumlah' => 20000]);

    expect($result['ok'])->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/ca/CA-PL-01/transaksi/9')
        && collect($request->data())->contains(fn ($f) => ($f['name'] ?? null) === '_method' && ($f['contents'] ?? null) === 'PUT'));
});

test('a transport failure returns ok=false with the error message, never throwing', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $result = makeCaWriter()->createTransactionCaPl('CA-PL-01', ['jumlah' => 1]);

    expect($result['ok'])->toBeFalse()
        ->and($result['status'])->toBeNull()
        ->and($result['error'])->toContain('Connection timed out');
});
