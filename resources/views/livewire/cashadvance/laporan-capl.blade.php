<?php

use App\Services\CaCache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Laporan CA PL'])]
class extends Component {
    /** @var array<string, mixed> */
    public array $balance = [];

    /** @var array<int, array<string, mixed>> */
    public array $transaksi = [];

    public function mount(?string $kodeCa = null): void
    {
        $dompets = collect(app(CaCache::class)->DompetCaActiveTransaksi(Auth::id()));

        $wallet = $kodeCa !== null
            ? $dompets->firstWhere('kode_ca', $kodeCa)
            : $dompets->firstWhere('is_active', 1);

        $this->balance = $wallet ?? [];

        if (! empty($this->balance['kode_ca'])) {
            $transaksi = app(CaCache::class)->transaksiCaPlActive($this->balance['kode_ca'])['data'] ?? [];

            $this->transaksi = collect($transaksi)
                ->filter(fn (array $trx): bool => ($trx['jenis'] ?? null) === 'pengeluaran')
                ->values()
                ->all();
        }
    }
}; ?>

<div>
    <div class="report-head">
        <div>
            <h1>Laporan Pertanggungjawaban CA PL</h1>
            <p>Periode #{{ $balance['kode_ca'] ?? '-' }} {{ ($balance['is_active'] ?? false) ? '(aktif)' : '' }}</p>
        </div>
        <div class="company">
            <strong>PT Hanatekindo Mulia Abadi</strong>
            <span>Cash Advance</span>
        </div>
    </div>

    <table class="meta">
        <tr><td>Nama</td><td>:</td><td>CA PL (imprest / dana tetap)</td></tr>
        <tr><td>Dibuka</td><td>:</td><td>{{ isset($balance['tanggal_mulai']) ? \Carbon\Carbon::parse($balance['tanggal_mulai'])->translatedFormat('d F Y') : '—' }}</td></tr>
        <tr><td>Total Penerimaan</td><td>:</td><td>Rp {{ number_format($balance['total_penerimaan'] ?? 0, 0, ',', '.') }}</td></tr>
        <tr><td>Tanggal Cetak</td><td>:</td><td>{{ now()->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="summary">
        <div class="box"><span>Saldo Awal (Opening)</span><strong>Rp {{ number_format($balance['saldo_awal_priode'] ?? 0, 0, ',', '.') }}</strong></div>
        <div class="box"><span>Total Pengeluaran</span><strong class="neg">Rp {{ number_format($balance['total_pengeluaran'] ?? 0, 0, ',', '.') }}</strong></div>
        <div class="box"><span>Saldo Akhir (Closing)</span><strong>Rp {{ number_format($balance['saldo_akhir'] ?? 0, 0, ',', '.') }}</strong></div>
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
            @forelse ($transaksi as $i => $trx)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d/m/Y') }}</td>
                <td>{{ $trx['kategori'] ?? '-' }}</td>
                <td>{{ $trx['deskripsi'] ?? '-' }}</td>
                <td class="text-right">Rp {{ number_format($trx['jumlah'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($trx['saldo_setelah'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-center">{{ ($trx['bukti'] ?? null) ? 'Ada' : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center">Belum ada transaksi pada periode ini</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="signoff">
        <div><span>Dibuat oleh,</span><div class="line">Project Leader</div></div>
        <div><span>Diverifikasi,</span><div class="line">Finance</div></div>
    </div>
</div>
