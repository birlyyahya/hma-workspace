<?php

namespace App\Services;

/**
 * Penyedia data dummy modul Pengajuan Barang.
 *
 * Halaman masih static — seluruh UI/UX mengambil data dari sini, tidak ada
 * query DB maupun panggilan API. Struktur array sengaja dibuat mendekati
 * skema target (pengajuan + items + lampiran) agar mudah ditukar ke Service
 * asli nanti tanpa mengubah komponen. Daftar project mengikuti bentuk
 * ProjectCache::allProjects() (id, name) supaya tinggal swap.
 */
class PengajuanBarangDummy
{
    /**
     * Status pengajuan beserta label & warna badge Flux.
     *
     * @return array<string, array{label: string, color: string}>
     */
    public function statuses(): array
    {
        return [
            'diajukan' => ['label' => 'Diajukan', 'color' => 'yellow'],
            'disetujui' => ['label' => 'Disetujui', 'color' => 'green'],
            'ditolak' => ['label' => 'Ditolak', 'color' => 'red'],
            'selesai' => ['label' => 'Selesai', 'color' => 'blue'],
        ];
    }

    /**
     * Satuan barang untuk dropdown item.
     *
     * @return array<int, string>
     */
    public function satuan(): array
    {
        return ['pcs', 'unit', 'set', 'box', 'rim', 'roll', 'meter', 'lusin'];
    }

    /**
     * Daftar project untuk dropdown kategori project.
     * Bentuk menyamai ProjectCache::allProjects() — nanti tinggal diganti.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function projects(): array
    {
        return [
            ['id' => 101, 'name' => 'Instalasi Fire Alarm — Kawasan Industri Cikarang'],
            ['id' => 102, 'name' => 'Pemasangan CCTV — Gedung BPJS Bekasi'],
            ['id' => 103, 'name' => 'Jaringan Fiber Optik — Pabrik Karawang'],
            ['id' => 104, 'name' => 'Sistem Access Control — Kantor Pusat Jakarta'],
        ];
    }

    /**
     * Seluruh pengajuan barang, terbaru dulu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pengajuanList(): array
    {
        return [
            [
                'kode' => 'PB-2026-0007',
                'kategori' => 'project',
                'project_id' => 101,
                'project_name' => 'Instalasi Fire Alarm — Kawasan Industri Cikarang',
                'keperluan' => 'Kebutuhan material instalasi titik detektor lantai 2',
                'tanggal_dibutuhkan' => '2026-07-25',
                'status' => 'diajukan',
                'pemohon' => 'Andi Prasetyo',
                'department' => 'Engineering',
                'created_at' => '2026-07-13',
                'items' => [
                    ['nama_barang' => 'Smoke Detector Optical', 'spesifikasi' => 'Hochiki SLV-24N, 24VDC', 'qty' => 12, 'satuan' => 'pcs', 'estimasi_harga' => 450_000],
                    ['nama_barang' => 'Kabel NYA 1.5mm', 'spesifikasi' => 'Supreme, merah/hitam', 'qty' => 4, 'satuan' => 'roll', 'estimasi_harga' => 350_000],
                    ['nama_barang' => 'Pipa Conduit 20mm', 'spesifikasi' => 'Clipsal, putih, 2.9m', 'qty' => 30, 'satuan' => 'pcs', 'estimasi_harga' => 25_000],
                ],
                'lampiran' => [
                    ['name' => 'RAB-material-lantai2.pdf', 'size' => '245 KB', 'type' => 'pdf'],
                    ['name' => 'denah-titik-detektor.jpg', 'size' => '1.2 MB', 'type' => 'image'],
                ],
                'history' => [
                    ['status' => 'diajukan', 'by' => 'Andi Prasetyo', 'at' => '2026-07-13 09:15', 'catatan' => null],
                ],
            ],
            [
                'kode' => 'PB-2026-0006',
                'kategori' => 'non_project',
                'project_id' => null,
                'project_name' => null,
                'keperluan' => 'Restock ATK bulanan divisi Finance',
                'tanggal_dibutuhkan' => '2026-07-20',
                'status' => 'disetujui',
                'pemohon' => 'Siti Rahma',
                'department' => 'Finance',
                'created_at' => '2026-07-10',
                'items' => [
                    ['nama_barang' => 'Kertas A4 80gsm', 'spesifikasi' => 'Sinar Dunia', 'qty' => 10, 'satuan' => 'rim', 'estimasi_harga' => 52_000],
                    ['nama_barang' => 'Tinta Printer Epson 003', 'spesifikasi' => 'Hitam & warna, original', 'qty' => 2, 'satuan' => 'set', 'estimasi_harga' => 380_000],
                ],
                'lampiran' => [],
                'history' => [
                    ['status' => 'diajukan', 'by' => 'Siti Rahma', 'at' => '2026-07-10 08:40', 'catatan' => null],
                    ['status' => 'disetujui', 'by' => 'Rudi Hartono', 'at' => '2026-07-11 14:20', 'catatan' => 'Silakan proses pembelian lewat vendor langganan.'],
                ],
            ],
            [
                'kode' => 'PB-2026-0005',
                'kategori' => 'project',
                'project_id' => 103,
                'project_name' => 'Jaringan Fiber Optik — Pabrik Karawang',
                'keperluan' => 'Penggantian splicer rusak & kebutuhan patch cord',
                'tanggal_dibutuhkan' => '2026-07-15',
                'status' => 'ditolak',
                'pemohon' => 'Budi Santoso',
                'department' => 'Engineering',
                'created_at' => '2026-07-05',
                'items' => [
                    ['nama_barang' => 'Patch Cord SC-LC 3m', 'spesifikasi' => 'Single mode, duplex', 'qty' => 24, 'satuan' => 'pcs', 'estimasi_harga' => 65_000],
                ],
                'lampiran' => [
                    ['name' => 'foto-splicer-rusak.jpg', 'size' => '850 KB', 'type' => 'image'],
                ],
                'history' => [
                    ['status' => 'diajukan', 'by' => 'Budi Santoso', 'at' => '2026-07-05 10:05', 'catatan' => null],
                    ['status' => 'ditolak', 'by' => 'Rudi Hartono', 'at' => '2026-07-07 09:30', 'catatan' => 'Anggaran project belum tersedia, ajukan ulang bulan depan.'],
                ],
            ],
            [
                'kode' => 'PB-2026-0004',
                'kategori' => 'non_project',
                'project_id' => null,
                'project_name' => null,
                'keperluan' => 'Pengadaan dispenser & galon untuk pantry kantor',
                'tanggal_dibutuhkan' => '2026-07-01',
                'status' => 'selesai',
                'pemohon' => 'Dewi Lestari',
                'department' => 'General Affair',
                'created_at' => '2026-06-24',
                'items' => [
                    ['nama_barang' => 'Dispenser Galon Bawah', 'spesifikasi' => 'Sharp SWD-72EHL-BK', 'qty' => 1, 'satuan' => 'unit', 'estimasi_harga' => 1_850_000],
                    ['nama_barang' => 'Galon Aqua 19L', 'spesifikasi' => 'Isi + galon baru', 'qty' => 4, 'satuan' => 'unit', 'estimasi_harga' => 65_000],
                ],
                'lampiran' => [
                    ['name' => 'penawaran-toko-elektronik.pdf', 'size' => '180 KB', 'type' => 'pdf'],
                ],
                'history' => [
                    ['status' => 'diajukan', 'by' => 'Dewi Lestari', 'at' => '2026-06-24 11:00', 'catatan' => null],
                    ['status' => 'disetujui', 'by' => 'Rudi Hartono', 'at' => '2026-06-25 08:50', 'catatan' => null],
                    ['status' => 'selesai', 'by' => 'Dewi Lestari', 'at' => '2026-06-30 15:45', 'catatan' => 'Barang sudah diterima dan dipasang di pantry.'],
                ],
            ],
        ];
    }

    /**
     * Detail satu pengajuan berdasarkan kode.
     *
     * @return array<string, mixed>|null
     */
    public function pengajuanByKode(string $kode): ?array
    {
        return collect($this->pengajuanList())->firstWhere('kode', $kode);
    }

    /**
     * Total estimasi harga sebuah pengajuan (qty × estimasi per item).
     *
     * @param  array<string, mixed>  $pengajuan
     */
    public function totalEstimasi(array $pengajuan): int
    {
        return (int) collect($pengajuan['items'] ?? [])
            ->sum(fn (array $item): int => (int) $item['qty'] * (int) $item['estimasi_harga']);
    }
}
