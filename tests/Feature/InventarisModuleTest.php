<?php

use App\Models\User;
use App\Services\InventarisCache;
use App\Services\InventarisWriter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

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

test('halaman inventaris tampil untuk user terautentikasi', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris'))
        ->assertOk()
        ->assertSee('Inventaris')
        ->assertSeeLivewire('inventaris.inventaris-table');
});

test('inventaris-table menampilkan data dari API dan empty state', function () {
    config()->set('services.api_inventaris', 'http://api.test');
    Http::fake(['*/inventaris/list*' => Http::response(['data' => [
        ['id' => 1, 'kode' => 'INV-001', 'nama' => 'Laptop Dell', 'kategori' => 'Elektronik', 'lokasi' => 'Kantor Pusat', 'kondisi' => 'baik'],
    ], 'pagination' => ['last_page' => 1]], 200)]);

    Volt::test('inventaris.inventaris-table')
        ->assertSee('INV-001')
        ->assertSee('Laptop Dell');
});

test('inventaris-table menampilkan empty state saat data kosong', function () {
    config()->set('services.api_inventaris', 'http://api.test');
    Http::fake(['*/inventaris/list*' => Http::response(['data' => [], 'pagination' => []], 200)]);

    Volt::test('inventaris.inventaris-table')
        ->assertSee('Belum ada data inventaris');
});

test('halaman detail inventaris tampil dengan data dari API', function () {
    config()->set('services.api_inventaris', 'http://api.test');
    Http::fake(['*/inventaris/detail/12' => Http::response(['data' => ['id' => 12, 'kode' => 'INV-012', 'nama' => 'Kamera', 'kondisi' => 'baik']], 200)]);

    $this->actingAs(User::factory()->create());

    $this->get(route('inventaris.show', 12))
        ->assertOk()
        ->assertSee('INV-012')
        ->assertSee('Kamera');
});
