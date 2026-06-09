<?php

use App\Services\CaDummy;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Laporan CA Kegiatan'])]
class extends Component {
    public string $kodeCa = '';

    /** @var array<string, mixed> */
    public array $dompet = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function mount(string $kodeCa, CaDummy $dummy): void
    {
        $this->kodeCa = $kodeCa;
        $this->dompet = $dummy->kegiatanByKode($kodeCa);

        $running = 0;
        $this->rows = collect($dummy->kegiatanTransactions($kodeCa))
            ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
            ->map(function (array $trx) use (&$running): array {
                $running += ($trx['direction'] === 'masuk' ? (int) $trx['amount'] : -(int) $trx['amount']);
                $trx['saldo_setelah'] = $running;

                return $trx;
            })
            ->values()
            ->all();
    }

    public function totalMasuk(): int
    {
        return (int) collect($this->rows)->where('direction', 'masuk')->sum('amount');
    }

    public function totalKeluar(): int
    {
        return (int) collect($this->rows)->where('direction', 'keluar')->sum('amount');
    }

    public function saldoAkhir(): int
    {
        return $this->totalMasuk() - $this->totalKeluar();
    }
}; ?>

@php $rp = fn (int $v): string => 'Rp '.number_format($v, 0, ',', '.'); @endphp

<div>
    <div class="report-head">
        <div>
            <h1>Laporan Pertanggungjawaban Kegiatan</h1>
            <p>{{ $dompet['name'] ?? $kodeCa }} — {{ $kodeCa }}</p>
        </div>
        <div class="company">
            <strong>PT Hanatekindo Mulia Abadi</strong>
            <span>Cash Advance — Dompet Kegiatan</span>
        </div>
    </div>

    <table class="meta">
        <tr><td>Nama Kegiatan</td><td>:</td><td>{{ $dompet['name'] ?? '-' }}</td></tr>
        <tr><td>Status</td><td>:</td><td>{{ ucfirst($dompet['status'] ?? '-') }}</td></tr>
        @if (! empty($dompet['rab_amount']))
            <tr><td>RAB</td><td>:</td><td>{{ $rp((int) $dompet['rab_amount']) }}</td></tr>
        @endif
        <tr><td>Dibuka</td><td>:</td><td>{{ \Carbon\Carbon::parse($dompet['opened_at'] ?? now())->translatedFormat('d F Y') }}</td></tr>
        <tr><td>Tanggal Cetak</td><td>:</td><td>{{ now()->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="summary">
        <div class="box"><span>Total Masuk</span><strong class="pos">{{ $rp($this->totalMasuk()) }}</strong></div>
        <div class="box"><span>Total Keluar</span><strong class="neg">{{ $rp($this->totalKeluar()) }}</strong></div>
        <div class="box"><span>Saldo Akhir</span><strong class="{{ $this->saldoAkhir() < 0 ? 'neg' : '' }}">{{ $rp($this->saldoAkhir()) }}</strong></div>
    </div>

    @if ($this->saldoAkhir() < 0)
        <div class="callout">
            <strong>Reimbursement:</strong> {{ $rp(abs($this->saldoAkhir())) }} —
            saldo negatif yang ditalangi PL, diselesaikan di luar sistem.
        </div>
    @endif

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
            @forelse ($rows as $i => $trx)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($trx['transaction_date'])->translatedFormat('d M Y') }}</td>
                    <td>{{ ucfirst($trx['direction']) }}</td>
                    <td>{{ $trx['category'] }}</td>
                    <td>{{ $trx['description'] }}</td>
                    <td class="text-right {{ $trx['direction'] === 'masuk' ? 'pos' : 'neg' }}">
                        {{ $trx['direction'] === 'masuk' ? '+' : '-' }} {{ $rp((int) $trx['amount']) }}
                    </td>
                    <td class="text-right {{ $trx['saldo_setelah'] < 0 ? 'neg' : '' }}">{{ $rp((int) $trx['saldo_setelah']) }}</td>
                    <td class="text-center">{{ $trx['has_bukti'] ? '✓' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center">Belum ada transaksi pada dompet ini</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="signoff">
        <div><span>Dibuat oleh,</span><div class="line">Project Leader</div></div>
        <div><span>Diverifikasi,</span><div class="line">Finance</div></div>
    </div>
</div>
