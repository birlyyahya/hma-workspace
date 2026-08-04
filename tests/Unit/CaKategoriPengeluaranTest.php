<?php

use App\Services\CaKategoriPemasukanKegiatan;
use App\Services\CaKategoriPengeluaran;

test('labels lists every expense category as a plain string', function () {
    expect(CaKategoriPengeluaran::labels())->toBe([
        'Transport', 'Akomodasi', 'Konsumsi', 'ATK', 'Komunikasi', 'Pengiriman', 'Manpower', 'Sewa Alat', 'Lain-lain',
    ]);
});

test('every expense category has an icon', function () {
    foreach (CaKategoriPengeluaran::cases() as $kategori) {
        expect($kategori->icon())->toBeString()->not->toBeEmpty();
    }
});

test('iconForLabel falls back to the default icon for an unknown or null label', function () {
    expect(CaKategoriPengeluaran::iconForLabel('Tidak Dikenal'))->toBe('ellipsis-horizontal')
        ->and(CaKategoriPengeluaran::iconForLabel(null))->toBe('ellipsis-horizontal')
        ->and(CaKategoriPengeluaran::iconForLabel('Manpower'))->toBe('user-group');
});

test('income categories are separate from expense categories', function () {
    expect(CaKategoriPemasukanKegiatan::labels())
        ->toBe(['Dana Awal', 'Top Up Tambahan', 'Pengembalian', 'Lain-lain'])
        ->and(array_intersect(CaKategoriPemasukanKegiatan::labels(), CaKategoriPengeluaran::labels()))
        ->toBe([3 => 'Lain-lain']);
});
