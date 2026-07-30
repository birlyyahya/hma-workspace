<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Laporan CA Kegiatan'])]
class extends Component {
    //
}; ?>

<div class="sheet">
    <div class="report-head">
        <div>
            <h1>Laporan Pertanggungjawaban Kegiatan</h1>
            <p>&mdash;</p>
        </div>
        <div class="company">
            <strong>PT Hanatekindo Mulia Abadi</strong>
            <span>Cash Advance &mdash; Dompet Kegiatan</span>
        </div>
    </div>

    <table class="meta">
        <tr><td>Nama Kegiatan</td><td>:</td><td>&mdash;</td></tr>
        <tr><td>Status</td><td>:</td><td>&mdash;</td></tr>
        <tr><td>Dibuka</td><td>:</td><td>&mdash;</td></tr>
        <tr><td>Tanggal Cetak</td><td>:</td><td>{{ now()->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="summary">
        <div class="box"><span>Total Masuk</span><strong class="pos">Rp 0</strong></div>
        <div class="box"><span>Total Keluar</span><strong class="neg">Rp 0</strong></div>
        <div class="box"><span>Saldo Akhir</span><strong>Rp 0</strong></div>
    </div>

    <table class="ledger">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th class="text-right">Jumlah</th>
                <th class="text-right">Saldo Setelah</th>
                <th class="text-center">Bukti</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="8" class="text-center">Belum ada transaksi pada dompet ini</td></tr>
        </tbody>
    </table>

    <div class="signoff">
        <div><span>Dibuat oleh,</span><div class="line">Project Leader</div></div>
        <div><span>Diverifikasi,</span><div class="line">Finance</div></div>
    </div>
</div>
