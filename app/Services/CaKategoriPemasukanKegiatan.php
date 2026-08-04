<?php

namespace App\Services;

/**
 * Kategori pemasukan pada dompet kegiatan (arah "Masuk" di
 * catat-transaksi-kegiatan) — terpisah dari kategori pengeluaran karena
 * sumber dana bukan jenis belanja.
 */
enum CaKategoriPemasukanKegiatan: string
{
    case DanaAwal = 'Dana Awal';
    case TopUpTambahan = 'Top Up Tambahan';
    case Pengembalian = 'Pengembalian';
    case LainLain = 'Lain-lain';

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_column(self::cases(), 'value');
    }
}
