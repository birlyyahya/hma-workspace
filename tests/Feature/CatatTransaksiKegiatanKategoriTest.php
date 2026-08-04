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
        '*/transaksi-wallet-kegiatan/*' => Http::response(['data' => [[
            'id_transaksi' => 5,
            'deskripsi' => 'Top up awal',
            'kategori' => 'Dana Awal',
            'jenis' => 'penerimaan',
            'jumlah' => 500_000,
            'tanggal' => '2026-07-01 10:00:00',
        ]]], 200),
    ]);
});

function catatTransaksiKegiatan()
{
    return Volt::test('cashadvance.modals.catat-transaksi-kegiatan', [
        'kodeCa' => 'CA-KEG-01',
        'categories' => ['Transport', 'Konsumsi'],
        'categoriesPemasukan' => ['Dana Awal', 'Top Up Tambahan'],
    ]);
}

test('kategori options default to the expense list when jenis is pengeluaran', function () {
    $this->actingAs(User::factory()->create());

    catatTransaksiKegiatan()
        ->assertSet('jenis', 'pengeluaran')
        ->assertSee('Transport')
        ->assertDontSee('Dana Awal');
});

test('switching jenis to penerimaan swaps in the income categories and clears an incompatible selection', function () {
    $this->actingAs(User::factory()->create());

    catatTransaksiKegiatan()
        ->set('kategori', 'Transport')
        ->set('jenis', 'penerimaan')
        ->assertSet('kategori', null)
        ->assertSee('Dana Awal')
        ->assertDontSee('Transport');
});

test('an expense category is rejected when saving an income transaction', function () {
    $this->actingAs(User::factory()->create());

    $bukti = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    catatTransaksiKegiatan()
        ->set('jenis', 'penerimaan')
        ->set('jumlah', 200_000)
        ->set('tanggal', '2026-07-01')
        ->set('bukti', $bukti)
        ->set('kategori', 'Transport')
        ->call('saveExpense')
        ->assertHasErrors(['kategori']);
});

test('saveExpense delegates a valid income transaction to the writer', function () {
    $this->actingAs(User::factory()->create());

    mock(CaWriter::class)
        ->shouldReceive('createTransaksiKegiatan')
        ->once()
        ->with('CA-KEG-01', Mockery::on(fn ($payload) => $payload['jenis'] === 'penerimaan'
            && $payload['kategori'] === 'Dana Awal'))
        ->andReturn(['ok' => true, 'body' => [], 'status' => 201, 'error' => null]);

    $bukti = UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf');

    catatTransaksiKegiatan()
        ->set('jenis', 'penerimaan')
        ->set('jumlah', 200_000)
        ->set('tanggal', '2026-07-01')
        ->set('bukti', $bukti)
        ->set('kategori', 'Dana Awal')
        ->call('saveExpense')
        ->assertHasNoErrors();
});

test('startEdit fills jenis and kategori from an income transaction', function () {
    $this->actingAs(User::factory()->create());

    catatTransaksiKegiatan()
        ->call('startEdit', 5)
        ->assertSet('jenis', 'penerimaan')
        ->assertSet('kategori', 'Dana Awal');
});
