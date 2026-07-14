<?php

use App\Models\User;
use App\Services\PengajuanBarangDummy;
use Livewire\Volt\Volt;

it('PengajuanBarangDummy menyediakan daftar pengajuan, project, dan total estimasi', function () {
    $dummy = new PengajuanBarangDummy;

    $pertama = $dummy->pengajuanList()[0];

    expect($dummy->pengajuanList())->not->toBeEmpty()
        ->and($dummy->projects())->not->toBeEmpty()
        ->and($dummy->statuses())->toHaveKeys(['diajukan', 'disetujui', 'ditolak', 'selesai'])
        ->and($dummy->pengajuanByKode($pertama['kode']))->toBe($pertama)
        ->and($dummy->pengajuanByKode('PB-TIDAK-ADA'))->toBeNull()
        ->and($dummy->totalEstimasi($pertama))->toBeGreaterThan(0);
});

it('halaman index pengajuan barang bisa diakses user login', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pengajuan-barang'))
        ->assertOk()
        ->assertSeeLivewire('pengajuan-barang.pengajuan-index');
});

it('pengajuan-index menampilkan daftar & memfilter status, kategori, dan pencarian', function () {
    Volt::test('pengajuan-barang.pengajuan-index')
        ->assertSee('PB-2026-0007')
        ->assertSee('PB-2026-0006')
        ->set('status', 'disetujui')
        ->assertSee('PB-2026-0006')
        ->assertDontSee('PB-2026-0007')
        ->set('status', '')
        ->set('kategori', 'project')
        ->assertSee('PB-2026-0007')
        ->assertDontSee('PB-2026-0006')
        ->set('kategori', '')
        ->set('search', 'tidak-ada-hasil')
        ->assertSee('Tidak ada pengajuan yang cocok dengan filter.');
});

it('pengajuan-create memvalidasi field wajib', function () {
    Volt::test('pengajuan-barang.pengajuan-create')
        ->set('keperluan', '')
        ->set('items.0.nama_barang', '')
        ->call('submit')
        ->assertHasErrors(['keperluan', 'items.0.nama_barang']);
});

it('pengajuan-create mewajibkan project saat kategori project', function () {
    Volt::test('pengajuan-barang.pengajuan-create')
        ->set('kategori', 'project')
        ->set('project_id', null)
        ->call('submit')
        ->assertHasErrors(['project_id']);
});

it('pengajuan-create bisa menambah & menghapus item barang', function () {
    Volt::test('pengajuan-barang.pengajuan-create')
        ->call('addItem')
        ->call('addItem')
        ->assertCount('items', 3)
        ->call('removeItem', 1)
        ->assertCount('items', 2);
});

it('pengajuan-create submit valid lalu redirect ke index', function () {
    Volt::test('pengajuan-barang.pengajuan-create')
        ->set('kategori', 'project')
        ->set('project_id', 101)
        ->set('keperluan', 'Kebutuhan material tambahan')
        ->set('tanggal_dibutuhkan', now()->addWeek()->toDateString())
        ->set('items.0.nama_barang', 'Kabel NYA 1.5mm')
        ->set('items.0.qty', '4')
        ->set('items.0.satuan', 'roll')
        ->set('items.0.estimasi_harga', '350000')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('pengajuan-barang'));
});

it('pengajuan-show menampilkan detail, item, dan lampiran', function () {
    Volt::test('pengajuan-barang.pengajuan-show', ['kode' => 'PB-2026-0007'])
        ->assertSee('PB-2026-0007')
        ->assertSee('Smoke Detector Optical')
        ->assertSee('RAB-material-lantai2.pdf')
        ->assertSee('Diajukan');
});

it('pengajuan-show menampilkan riwayat status beserta catatan', function () {
    Volt::test('pengajuan-barang.pengajuan-show', ['kode' => 'PB-2026-0005'])
        ->assertSee('Riwayat Status')
        ->assertSee('oleh Budi Santoso')
        ->assertSee('oleh Rudi Hartono')
        ->assertSee('Anggaran project belum tersedia, ajukan ulang bulan depan.');
});

it('pengajuan-show 404 untuk kode yang tidak dikenal', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('pengajuan-barang.show', 'PB-TIDAK-ADA'))
        ->assertNotFound();
});
