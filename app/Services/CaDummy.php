<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Penyedia data dummy modul Cash Advance.
 *
 * Sementara backend dirombak, seluruh UI/UX CA mengambil data dari sini —
 * tidak ada query DB maupun panggilan API. Uang selalu integer rupiah utuh.
 * Struktur array sengaja dibuat mendekati skema target (wallet + period + transaksi)
 * agar mudah ditukar ke Service asli nanti tanpa mengubah komponen.
 */
class CaDummy
{
    /** Saldo float tetap (imprest) CA PL. */
    public const IMPREST = 2_000_000;

    /**
     * Kategori pengeluaran untuk dropdown form.
     *
     * @return array<int, string>
     */
    public function categories(): array
    {
        return ['Transport', 'Akomodasi', 'Konsumsi', 'ATK', 'Komunikasi', 'Lain-lain'];
    }

    /**
     * Dompet CA PL beserta periode aktifnya.
     *
     * @return array<string, mixed>
     */
    public function walletPl(): array
    {
        return [
            'id' => 1,
            'type' => 'ca_pl',
            'name' => 'CA PL',
            'status' => 'active', // active | needs_topup
            'imprest_amount' => self::IMPREST,
            'period' => [
                'id' => 3,
                'sequence_no' => 3,
                'opening_balance' => self::IMPREST,
                'status' => 'active',
                'opened_at' => '2026-06-01',
            ],
        ];
    }

    /**
     * Transaksi periode aktif CA PL (praktis hanya `keluar`).
     * `saldo_setelah` sengaja TIDAK disimpan — dihitung ulang di komponen dari opening balance.
     *
     * @return array<int, array<string, mixed>>
     */
    public function periodTransactions(): array
    {
        return [
            ['id' => 101, 'direction' => 'keluar', 'amount' => 250_000, 'category' => 'Transport', 'description' => 'Taksi ke lokasi proyek', 'transaction_date' => '2026-06-02', 'has_bukti' => true],
            ['id' => 102, 'direction' => 'keluar', 'amount' => 180_000, 'category' => 'Konsumsi', 'description' => 'Konsumsi rapat koordinasi', 'transaction_date' => '2026-06-03', 'has_bukti' => true],
            ['id' => 103, 'direction' => 'keluar', 'amount' => 75_000, 'category' => 'ATK', 'description' => 'Pembelian alat tulis', 'transaction_date' => '2026-06-04', 'has_bukti' => true],
            ['id' => 104, 'direction' => 'keluar', 'amount' => 145_000, 'category' => 'Komunikasi', 'description' => 'Pulsa & paket data', 'transaction_date' => '2026-06-05', 'has_bukti' => false],
        ];
    }

    /**
     * Riwayat periode CA PL yang sudah disegel (read-only), terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sealedPeriods(): array
    {
        return [
            ['sequence_no' => 2, 'opening_balance' => self::IMPREST, 'closing_balance' => -150_000, 'total_keluar' => 2_150_000, 'opened_at' => '2026-05-01', 'sealed_at' => '2026-05-31'],
            ['sequence_no' => 1, 'opening_balance' => self::IMPREST, 'closing_balance' => 320_000, 'total_keluar' => 1_680_000, 'opened_at' => '2026-04-01', 'sealed_at' => '2026-04-30'],
        ];
    }

    /**
     * Satu periode tersegel berdasarkan nomor urut.
     *
     * @return array<string, mixed>
     */
    public function sealedPeriodBySeq(int $seq): array
    {
        return collect($this->sealedPeriods())->firstWhere('sequence_no', $seq) ?? [];
    }

    /**
     * Transaksi sebuah periode CA PL yang sudah disegel.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sealedPeriodTransactions(int $seq): array
    {
        return match ($seq) {
            2 => [
                ['id' => 81, 'direction' => 'keluar', 'amount' => 900_000, 'category' => 'Akomodasi', 'description' => 'Hotel perjalanan dinas', 'transaction_date' => '2026-05-05', 'has_bukti' => true],
                ['id' => 82, 'direction' => 'keluar', 'amount' => 750_000, 'category' => 'Transport', 'description' => 'Tiket kereta PP', 'transaction_date' => '2026-05-06', 'has_bukti' => true],
                ['id' => 83, 'direction' => 'keluar', 'amount' => 500_000, 'category' => 'Konsumsi', 'description' => 'Konsumsi tim', 'transaction_date' => '2026-05-20', 'has_bukti' => true],
            ],
            1 => [
                ['id' => 71, 'direction' => 'keluar', 'amount' => 1_200_000, 'category' => 'Transport', 'description' => 'Sewa kendaraan', 'transaction_date' => '2026-04-10', 'has_bukti' => true],
                ['id' => 72, 'direction' => 'keluar', 'amount' => 480_000, 'category' => 'ATK', 'description' => 'Perlengkapan kantor proyek', 'transaction_date' => '2026-04-18', 'has_bukti' => true],
            ],
            default => [],
        };
    }

    /**
     * Daftar dompet kegiatan milik PL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dompetKegiatan(): array
    {
        return [
            ['id' => 11, 'type' => 'ca_kegiatan', 'kode' => 'KEG-001', 'name' => 'Pelatihan K3 Proyek Cikarang', 'status' => 'active', 'rab_amount' => 12_000_000, 'current_balance' => 4_300_000, 'opened_at' => '2026-05-20'],
            ['id' => 12, 'type' => 'ca_kegiatan', 'kode' => 'KEG-002', 'name' => 'Survei Lokasi Tangerang', 'status' => 'active', 'rab_amount' => 5_000_000, 'current_balance' => -650_000, 'opened_at' => '2026-05-28'],
            ['id' => 13, 'type' => 'ca_kegiatan', 'kode' => 'KEG-003', 'name' => 'Sosialisasi Vendor', 'status' => 'closed', 'rab_amount' => 3_000_000, 'current_balance' => 0, 'opened_at' => '2026-04-10'],
        ];
    }

    /**
     * Satu dompet kegiatan berdasarkan kode.
     *
     * @return array<string, mixed>
     */
    public function kegiatanByKode(string $kode): array
    {
        return $this->dompetKegiatanCollection()->firstWhere('kode', $kode) ?? [];
    }

    /**
     * Transaksi sebuah dompet kegiatan (sepanjang umur dompet — satu periode).
     *
     * @return array<int, array<string, mixed>>
     */
    public function kegiatanTransactions(string $kode): array
    {
        return match ($kode) {
            'KEG-001' => [
                ['id' => 201, 'direction' => 'masuk', 'amount' => 12_000_000, 'category' => 'Dana Cair', 'description' => 'Pencairan dana kegiatan', 'transaction_date' => '2026-05-20', 'has_bukti' => true],
                ['id' => 202, 'direction' => 'keluar', 'amount' => 5_000_000, 'category' => 'Akomodasi', 'description' => 'Sewa tempat pelatihan', 'transaction_date' => '2026-05-21', 'has_bukti' => true],
                ['id' => 203, 'direction' => 'keluar', 'amount' => 2_700_000, 'category' => 'Konsumsi', 'description' => 'Konsumsi peserta', 'transaction_date' => '2026-05-22', 'has_bukti' => true],
            ],
            'KEG-002' => [
                ['id' => 211, 'direction' => 'masuk', 'amount' => 5_000_000, 'category' => 'Dana Cair', 'description' => 'Pencairan dana survei', 'transaction_date' => '2026-05-28', 'has_bukti' => true],
                ['id' => 212, 'direction' => 'keluar', 'amount' => 4_200_000, 'category' => 'Transport', 'description' => 'Sewa kendaraan & BBM', 'transaction_date' => '2026-05-29', 'has_bukti' => true],
                ['id' => 213, 'direction' => 'keluar', 'amount' => 1_450_000, 'category' => 'Konsumsi', 'description' => 'Konsumsi tim survei', 'transaction_date' => '2026-05-30', 'has_bukti' => false],
            ],
            default => [],
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function dompetKegiatanCollection(): Collection
    {
        return collect($this->dompetKegiatan());
    }
}
