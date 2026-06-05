<?php

use App\Services\CaCache;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;

function fakeCaApi(): void
{
    Http::fake([
        '*ca-pl*' => Http::response([
            'status' => 'success',
            'message' => 'Data berhasil diambil',
            'data' => [
                [
                    'data_category' => ['id' => 2, 'name' => 'Dompet Kegiatan'],
                    'id' => 6,
                    'kode_ca' => 'CA-2026-005',
                    'judul_kegiatan' => 'CA Pelatihan',
                    'tanggal_mulai' => '2025-05-26',
                    'tahun_anggaran' => '2026',
                    'total_penerimaan' => '9000000.00',
                    'total_pengeluaran' => '0.00',
                    'status' => 'draft',
                    'saldo_akhir' => '9000000.00',
                ],
                [
                    'data_category' => ['id' => 1, 'name' => 'Dompet PL'],
                    'id' => 7,
                    'kode_ca' => 'CA-2026-006',
                    'judul_kegiatan' => 'CA PL',
                    'tanggal_mulai' => '2025-05-26',
                    'tahun_anggaran' => '2026',
                    'total_penerimaan' => '9000000.00',
                    'total_pengeluaran' => '600000.00',
                    'status' => 'approved',
                    'saldo_akhir' => '8400000.00',
                ],
            ],
            'meta' => null,
        ]),
        '*ca/*/transaksi' => Http::response([
            'success' => true,
            'message' => 'Transaksi berhasil ditambahkan',
            'data' => ['id' => 15, 'saldo_setelah' => 8280000],
        ]),
        '*ca/transaksi/*' => Http::response(['success' => true, 'message' => 'Transaksi dihapus']),
        '*transaksi*' => Http::response([
            'success' => true,
            'data' => [
                [
                    'id' => 6,
                    'tr_ca_id' => 7,
                    'tanggal' => '2025-10-29',
                    'jenis' => 'pengeluaran',
                    'deskripsi' => 'testing',
                    'jumlah' => '120000.00',
                    'saldo_setelah' => '8880000.00',
                    'bukti_url' => 'http://localhost/storage/bukti/x.png',
                ],
            ],
        ]),
    ]);
}

it('memisahkan dompet PL (kategori 1 approved) dari dompet kegiatan (kategori 2)', function () {
    fakeCaApi();

    $ca = app(CaCache::class);

    expect($ca->dompetPl(25)['kode_ca'])->toBe('CA-2026-006')
        ->and($ca->dompetKegiatan(25))->toHaveCount(1)
        ->and($ca->dompetKegiatan(25)[0]['kode_ca'])->toBe('CA-2026-005');
});

it('mengembalikan transaksi berdasarkan kode CA', function () {
    fakeCaApi();

    $transaksi = app(CaCache::class)->transaksi('CA-2026-006');

    expect($transaksi)->toHaveCount(1)
        ->and($transaksi[0]['deskripsi'])->toBe('testing');
});

it('men-cache /ca-pl sehingga hanya satu HTTP call per user', function () {
    fakeCaApi();

    $ca = app(CaCache::class);
    $ca->dompetList(25);
    $ca->dompetList(25);

    Http::assertSentCount(1);
});

it('mengirim transaksi multipart dan mem-flush cache', function () {
    fakeCaApi();

    $ca = app(CaCache::class);
    $ca->dompetList(25); // hangatkan cache

    $response = $ca->addTransaksi('CA-2026-006', [
        'tanggal' => '2025-10-29',
        'jenis' => 'pengeluaran',
        'jumlah' => 120000,
        'deskripsi' => 'testing',
    ], UploadedFile::fake()->image('bukti.png'));

    expect($response['success'])->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/ca/CA-2026-006/transaksi')
        && $request->method() === 'POST'
        && $request->isMultipart());

    // cache di-flush: pemanggilan berikutnya memicu HTTP baru
    Http::fake(['*ca-pl*' => Http::response(['status' => 'success', 'data' => []])]);
    $ca->dompetList(25);
    Http::assertSentCount(1);
});

it('menghapus transaksi via service', function () {
    fakeCaApi();

    $response = app(CaCache::class)->deleteTransaksi(6);

    expect($response['success'])->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), '/ca/transaksi/6')
        && $request->method() === 'DELETE');
});

it('komponen transaksi-modal mengirim send (pengeluaran) lalu dispatch event', function () {
    fakeCaApi();

    Volt::test('cashadvance.transaksi-modal', ['kodeCa' => 'CA-2026-006'])
        ->set('jumlah', '120000')
        ->set('deskripsi', 'testing')
        ->call('simpan', 'pengeluaran')
        ->assertHasNoErrors()
        ->assertDispatched('transaksi-added');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/ca/CA-2026-006/transaksi'));
});

it('komponen transaksi-modal memvalidasi jumlah & deskripsi wajib', function () {
    fakeCaApi();

    Volt::test('cashadvance.transaksi-modal', ['kodeCa' => 'CA-2026-006'])
        ->set('jumlah', '')
        ->set('deskripsi', '')
        ->call('simpan', 'pengeluaran')
        ->assertHasErrors(['jumlah', 'deskripsi']);
});

it('widget memisahkan dompet PL dan dompet kegiatan saat render', function () {
    fakeCaApi();

    Volt::test('cashadvance.widget.ca-widget')
        ->assertSet('dompetPl.kode_ca', 'CA-2026-006')
        ->assertSet('dompetKegiatan.0.kode_ca', 'CA-2026-005')
        ->assertSee('CA PL')
        ->assertSee('CA Pelatihan');
});

it('ca-index menampilkan & memfilter transaksi dompet PL', function () {
    fakeCaApi();

    Volt::test('cashadvance.ca-index')
        ->assertSee('testing')
        ->set('search', 'tidak-ada')
        ->assertSee('Belum ada transaksi pada dompet PL');
});
