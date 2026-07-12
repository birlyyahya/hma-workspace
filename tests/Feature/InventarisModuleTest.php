<?php

use App\Models\User;
use App\Services\InventarisCache;
use App\Services\InventarisWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeInventarisWriter(): InventarisWriter
{
    return new InventarisWriter('http://api.test', new InventarisCache('http://api.test'));
}

test('list returns the full payload (data + pagination)', function () {
    Http::fake(['*/inventaris/list*' => Http::response(['data' => [['id' => 1, 'nama' => 'Laptop']], 'pagination' => ['last_page' => 1]], 200)]);

    $result = (new InventarisCache('http://api.test'))->list(['search' => 'laptop']);

    expect($result['data'])->toBe([['id' => 1, 'nama' => 'Laptop']])
        ->and($result['pagination'])->toBe(['last_page' => 1]);
});

test('list returns empty default when connection fails, without throwing', function () {
    Http::fake(fn () => throw new ConnectionException('down'));

    expect((new InventarisCache('http://api.test'))->list())->toBe(['data' => []]);
});

test('detail returns the payload per id', function () {
    Http::fake(['*/inventaris/detail/7' => Http::response(['data' => ['id' => 7, 'nama' => 'Proyektor']], 200)]);

    expect((new InventarisCache('http://api.test'))->detail(7)['data']['nama'])->toBe('Proyektor');
});

test('createItem succeeds only when body success is true', function () {
    Http::fake(['*/inventaris/create' => Http::response(['success' => true, 'data' => ['id' => 9]], 200)]);

    $result = makeInventarisWriter()->createItem(['nama' => 'Printer']);

    expect($result['ok'])->toBeTrue()
        ->and($result['body']['data']['id'])->toBe(9)
        ->and($result['error'])->toBeNull();
});

test('createItem fails when body success is false', function () {
    Http::fake(['*/inventaris/create' => Http::response(['success' => false, 'message' => 'invalid'], 200)]);

    expect(makeInventarisWriter()->createItem([])['ok'])->toBeFalse();
});

test('updateItem sends PUT to the update endpoint', function () {
    Http::fake(['*/inventaris/update/5' => Http::response(['success' => true], 200)]);

    expect(makeInventarisWriter()->updateItem(5, ['nama' => 'Scanner'])['ok'])->toBeTrue();
    Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_ends_with($request->url(), '/inventaris/update/5'));
});

test('deleteItem succeeds on HTTP 2xx and never throws on transport failure', function () {
    Http::fake(['*/inventaris/delete/3' => Http::response([], 204)]);

    expect(makeInventarisWriter()->deleteItem(3)['ok'])->toBeTrue();

    Http::fake(fn () => throw new ConnectionException('down'));

    $result = makeInventarisWriter()->deleteItem(3);

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->not->toBeNull();
});

test('halaman pilih project tampil untuk user terautentikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris'))
        ->assertOk()
        ->assertSee('Inventaris')
        ->assertSee('Pembangunan Jaringan FO Cikarang');
});

test('halaman daftar spektek tampil dengan progress', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.project', 101))
        ->assertOk()
        ->assertSee('Perangkat OLT + Aksesoris')
        ->assertSee('SN terpasang');
});

test('halaman detail spektek tampil dengan modal generate dan riwayat batch', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.spektek', [101, 12]))
        ->assertOk()
        ->assertSee('Generate Unit')
        ->assertSee('GR-2026-0007');
});

test('halaman list unit tampil dengan tabel dan import preview', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.units', 501))
        ->assertOk()
        ->assertSee('INV-0007-001')
        ->assertSee('Import SN');
});

test('halaman detail unit tampil dengan SN dan timeline', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.unit-detail', 9002))
        ->assertOk()
        ->assertSee('ZTE10G-88342')
        ->assertSee('Riwayat Pergerakan');
});

test('halaman scan tampil dengan tab kamera dan input manual', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.scan'))
        ->assertOk()
        ->assertSee('Scan Unit')
        ->assertSee('Ketik SN');
});

test('route inventaris statis tidak tertelan wildcard projectId', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/inventaris/scan')->assertOk()->assertSee('Scan Unit');
    $this->get('/inventaris/abc')->assertNotFound();
});
