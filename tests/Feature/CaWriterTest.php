<?php

use App\Services\CaCache;
use App\Services\CaWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

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

test('createTransactionCaPl logs the submitted amount and category, not just the kode', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi' => Http::response(['success' => true], 200)]);

    makeCaWriter()->createTransactionCaPl('CA-PL-01', [
        'jumlah' => 75_000,
        'kategori' => 'Transport',
        'deskripsi' => 'Taksi bandara',
        'bukti' => ['contents' => 'BIN', 'name' => 'nota.png'],
    ]);

    $activity = Activity::query()->latest('id')->first();

    expect($activity->description)->toContain('Transport')->toContain('75.000')
        ->and($activity->properties->get('jumlah'))->toBe(75_000)
        ->and($activity->properties->get('kategori'))->toBe('Transport')
        ->and($activity->properties->has('bukti'))->toBeFalse();
});

test('updateTransactionCaPl only logs fields that actually changed, with old and new values', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi/9' => Http::response(['success' => true], 200)]);

    $original = ['jumlah' => 20_000, 'kategori' => 'Transport', 'deskripsi' => 'Taksi', 'tanggal' => '2026-07-01'];

    makeCaWriter()->updateTransactionCaPl('CA-PL-01', 9, [
        'jumlah' => 35_000,
        'kategori' => 'Transport',
        'deskripsi' => 'Taksi',
        'tanggal' => '2026-07-01',
    ], $original);

    $activity = Activity::query()->latest('id')->first();
    $changes = $activity->properties->get('changes');

    expect($activity->description)->toContain('jumlah')
        ->and($activity->description)->not->toContain('kategori')
        ->and($changes)->toHaveKey('jumlah')
        ->and($changes['jumlah'])->toBe(['from' => 20_000, 'to' => 35_000])
        ->and($changes)->not->toHaveKey('kategori')
        ->and($activity->properties->get('bukti_diganti'))->toBeFalse();
});

test('updateTransactionCaPl marks bukti as replaced when a new file is attached', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi/9' => Http::response(['success' => true], 200)]);

    makeCaWriter()->updateTransactionCaPl('CA-PL-01', 9, [
        'jumlah' => 20_000,
        'bukti' => ['contents' => 'BIN', 'name' => 'baru.png'],
    ], ['jumlah' => 20_000]);

    $activity = Activity::query()->latest('id')->first();

    expect($activity->description)->toContain('bukti')
        ->and($activity->properties->get('bukti_diganti'))->toBeTrue();
});

test('deleteTransactionCaPL logs the amount and category of what was deleted when given a snapshot', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi/9' => Http::response(['success' => true], 200)]);

    makeCaWriter()->deleteTransactionCaPL('CA-PL-01', 9, [
        'jumlah' => 75_000,
        'kategori' => 'Transport',
    ]);

    $activity = Activity::query()->latest('id')->first();

    expect($activity->description)->toContain('Transport')->toContain('75.000');
});

test('deleteTransactionCaPL still logs cleanly without a snapshot', function () {
    Http::fake(['*/ca/CA-PL-01/transaksi/9' => Http::response(['success' => true], 200)]);

    makeCaWriter()->deleteTransactionCaPL('CA-PL-01', 9);

    $activity = Activity::query()->latest('id')->first();

    expect($activity->description)->toContain('CA-PL-01')->toContain('#9');
});
