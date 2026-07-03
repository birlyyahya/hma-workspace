<?php

use App\Models\User;
use App\Services\CaWriter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

use function Pest\Laravel\mock;

beforeEach(function () {
    config(['services.api_ca' => 'http://api.test']);

    Http::fake([
        '*/cashadvance*' => Http::response([
            'card_left' => ['kode_ca' => 'CA-PL-01', 'sisa_saldo' => 1_000_000],
            'card_right' => ['data' => []],
            'chart' => ['series' => []],
            'categories' => ['Transport', 'Konsumsi'],
        ], 200),
        '*/transaksi-wallet-pl/*' => Http::response(['data' => []], 200),
        '*/ca/CA-PL-01/transaksi/*' => Http::response(['success' => true], 200),
        '*/ca/CA-PL-01/transaksi' => Http::response(['success' => true], 200),
    ]);
});

test('saveExpense validates that a bukti is required when creating a new expense', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->set('jumlah', 50_000)
        ->set('kategori', 'Transport')
        ->set('tanggal', '2026-07-01')
        ->call('saveExpense')
        ->assertHasErrors(['bukti' => 'required']);
});

test('saveExpense delegates a valid new expense to the writer and resets the form', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('createTransactionCaPl')
        ->once()
        ->with('CA-PL-01', Mockery::on(fn ($payload) => $payload['jumlah'] === 50_000
            && $payload['kategori'] === 'Transport'
            && $payload['direction'] === 'keluar'
            && isset($payload['bukti'])))
        ->andReturn(['ok' => true, 'body' => [], 'status' => 201, 'error' => null]);

    $bukti = UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf');

    Volt::test('cashadvance.ca-index')
        ->set('jumlah', 50_000)
        ->set('kategori', 'Transport')
        ->set('deskripsi', 'Taksi')
        ->set('tanggal', '2026-07-01')
        ->set('bukti', $bukti)
        ->call('saveExpense')
        ->assertHasNoErrors()
        ->assertSet('jumlah', null)
        ->assertSet('editingId', null);
});

test('saveExpense allows editing without re-uploading a bukti and calls the update endpoint', function () {
    $this->actingAs(User::factory()->create());

    Volt::test('cashadvance.ca-index')
        ->set('editingId', 9)
        ->set('jumlah', 20_000)
        ->set('kategori', 'Konsumsi')
        ->set('tanggal', '2026-07-02')
        ->call('saveExpense')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/ca/CA-PL-01/transaksi/9'));
});
