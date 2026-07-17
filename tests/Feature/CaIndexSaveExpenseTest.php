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
        '*/transaksi-wallet-pl/*' => Http::response(['data' => [[
            'id_transaksi' => 9,
            'dompet_id' => 1,
            'deskripsi' => 'Taksi bandara',
            'kategori' => 'Transport',
            'created_at' => '2026-07-01 10:00:00',
            'jenis' => 'pengeluaran',
            'jumlah' => 75_000,
            'saldo_setelah' => 925_000,
            'bukti' => null,
        ]]], 200),
        '*/riwayat-wallet-kegiatan*' => Http::response(['data' => []], 200),
        '*/ca/CA-PL-01/transaksi/*' => Http::response(['success' => true], 200),
        '*/ca/CA-PL-01/transaksi' => Http::response(['success' => true], 200),
    ]);
});

function catatPengeluaran()
{
    return Volt::test('cashadvance.modals.catat-pengeluaran', [
        'kodeCa' => 'CA-PL-01',
        'categories' => ['Transport', 'Konsumsi'],
    ]);
}

test('saveExpense validates that a bukti is required when creating a new expense', function () {
    $this->actingAs(User::factory()->create());

    catatPengeluaran()
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
            && $payload['jenis'] === 'pengeluaran'
            && isset($payload['bukti'])))
        ->andReturn(['ok' => true, 'body' => [], 'status' => 201, 'error' => null]);

    $bukti = UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf');

    catatPengeluaran()
        ->set('jumlah', 50_000)
        ->set('kategori', 'Transport')
        ->set('deskripsi', 'Taksi')
        ->set('tanggal', '2026-07-01')
        ->set('bukti', $bukti)
        ->call('saveExpense')
        ->assertHasNoErrors()
        ->assertSet('jumlah', null)
        ->assertSet('editingId', null)
        ->assertDispatched('ca-updated');
});

test('startEdit fills the form from the selected transaction', function () {
    $this->actingAs(User::factory()->create());

    catatPengeluaran()
        ->call('startEdit', 9)
        ->assertSet('editingId', 9)
        ->assertSet('jumlah', 75_000)
        ->assertSet('kategori', 'Transport')
        ->assertSet('deskripsi', 'Taksi bandara')
        ->assertSet('tanggal', '2026-07-01')
        ->assertSet('bukti', null);
});

test('startEdit ignores an unknown transaction id', function () {
    $this->actingAs(User::factory()->create());

    catatPengeluaran()
        ->call('startEdit', 999)
        ->assertSet('editingId', null);
});

test('saveExpense allows editing without re-uploading a bukti and calls the update endpoint', function () {
    $this->actingAs(User::factory()->create());

    catatPengeluaran()
        ->set('editingId', 9)
        ->set('jumlah', 20_000)
        ->set('kategori', 'Konsumsi')
        ->set('tanggal', '2026-07-02')
        ->call('saveExpense')
        ->assertHasNoErrors();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/ca/CA-PL-01/transaksi/9'));
});
