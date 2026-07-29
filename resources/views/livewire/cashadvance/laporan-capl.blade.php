<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Laporan CA PL'])]
class extends Component {

}; ?>

<div>
    <div class="report-head">
        <div>
            <h1>Laporan Pertanggungjawaban CA PL</h1>
            <p>Periode #1 (aktif)</p>
        </div>
        <div class="company">
            <strong>PT Hanatekindo Mulia Abadi</strong>
            <span>Cash Advance</span>
        </div>
    </div>

    <table class="meta">
        <tr><td>Nama</td><td>:</td><td>CA PL (imprest / dana tetap)</td></tr>
        <tr><td>Saldo Float (Imprest)</td><td>:</td><td>Rp 0</td></tr>
        <tr><td>Nama PT</td><td>:</td><td>&mdash;</td></tr>
        <tr><td>Tanggal</td><td>:</td><td>{{ now()->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="summary">
        <div class="box"><span>Saldo Awal (Opening)</span><strong>Rp {{ $balance['saldo_awal_priode'] }}</strong></div>
        <div class="box"><span>Total Pengeluaran</span><strong class="neg">Rp 0</strong></div>
        <div class="box"><span>Saldo Akhir (Closing)</span><strong>Rp 0</strong></div>
    </div>

    <table class="ledger">
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Deskripsi</th>
                <th class="text-right">Pengeluaran</th>
                <th class="text-right">Saldo Setelah</th>
                <th class="text-center">Bukti</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="7" class="text-center">Belum ada transaksi pada periode ini</td></tr>
        </tbody>
    </table>

    <div class="signoff">
        <div><span>Dibuat oleh,</span><div class="line">Project Leader</div></div>
        <div><span>Diverifikasi,</span><div class="line">Finance</div></div>
    </div>
</div>
