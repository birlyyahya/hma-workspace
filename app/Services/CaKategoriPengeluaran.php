<?php

namespace App\Services;

/**
 * Kategori pengeluaran Cash Advance, dipakai bersama oleh dompet CA PL
 * (catat-pengeluaran) dan dompet kegiatan (catat-transaksi-kegiatan) supaya
 * kedua modul selalu menawarkan daftar yang sama.
 */
enum CaKategoriPengeluaran: string
{
    case Transport = 'Transport';
    case Akomodasi = 'Akomodasi';
    case Konsumsi = 'Konsumsi';
    case Atk = 'ATK';
    case Komunikasi = 'Komunikasi';
    case Pengiriman = 'Pengiriman';
    case Manpower = 'Manpower';
    case SewaAlat = 'Sewa Alat';
    case LainLain = 'Lain-lain';

    public function icon(): string
    {
        return match ($this) {
            self::Transport => 'truck',
            self::Akomodasi => 'building-office',
            self::Konsumsi => 'shopping-bag',
            self::Atk => 'pencil-square',
            self::Komunikasi => 'signal',
            self::Pengiriman => 'paper-airplane',
            self::Manpower => 'user-group',
            self::SewaAlat => 'wrench-screwdriver',
            self::LainLain => 'ellipsis-horizontal',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function iconForLabel(?string $label): string
    {
        return self::tryFrom((string) $label)?->icon() ?? 'ellipsis-horizontal';
    }
}
