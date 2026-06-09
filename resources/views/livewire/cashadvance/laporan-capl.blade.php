<?php

use App\Services\CaDummy;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Laporan CA PL'])]
class extends Component {
    public ?int $seq = null;

    /** @var array<string, mixed> */
    public array $period = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $isSealed = false;

    public function mount(CaDummy $dummy, ?int $seq = null): void
    {
        $wallet = $dummy->walletPl();
        $activeSeq = (int) $wallet['period']['sequence_no'];

        if ($seq !== null && $seq !== $activeSeq) {
            $this->isSealed = true;
            $this->seq = $seq;
            $this->period = $dummy->sealedPeriodBySeq($seq);
            $transactions = $dummy->sealedPeriodTransactions($seq);
            $opening = (int) ($this->period['opening_balance'] ?? CaDummy::IMPREST);
        } else {
            $this->seq = $activeSeq;
            $this->period = $wallet['period'];
            $transactions = $dummy->periodTransactions();
            $opening = (int) $wallet['period']['opening_balance'];
        }

        $running = $opening;
        $this->rows = collect($transactions)
            ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
            ->map(function (array $trx) use (&$running): array {
                $running += ($trx['direction'] === 'masuk' ? (int) $trx['amount'] : -(int) $trx['amount']);
                $trx['saldo_setelah'] = $running;

                return $trx;
            })
            ->values()
            ->all();
    }

    public function totalKeluar(): int
    {
        return (int) collect($this->rows)->where('direction', 'keluar')->sum('amount');
    }

    public function closingBalance(): int
    {
        return empty($this->rows)
            ? (int) ($this->period['opening_balance'] ?? CaDummy::IMPREST)
            : (int) end($this->rows)['saldo_setelah'];
    }
}; ?>


@php $rp = fn (int $v): string => 'Rp '.number_format($v, 0, ',', '.'); @endphp

<div>
    <div class="report-head">
        <div>
            <h1>Laporan Pertanggungjawaban CA PL</h1>
            <p>Periode #{{ $seq }} {{ $isSealed ? '(tersegel)' : '(aktif)' }}</p>
        </div>
        <div class="company">
            <strong>PT Hanatekindo Mulia Abadi</strong>
            <span>Cash Advance — Dompet PL</span>
        </div>
    </div>

    <table class="meta">
        <tr><td>Jenis Dompet</td><td>:</td><td>CA PL (imprest / dana tetap)</td></tr>
        <tr><td>Saldo Float (Imprest)</td><td>:</td><td>{{ $rp(\App\Services\CaDummy::IMPREST) }}</td></tr>
        <tr><td>Periode Dibuka</td><td>:</td><td>{{ \Carbon\Carbon::parse($period['opened_at'])->translatedFormat('d F Y') }}</td></tr>
        @if ($isSealed && ! empty($period['sealed_at']))
            <tr><td>Periode Disegel</td><td>:</td><td>{{ \Carbon\Carbon::parse($period['sealed_at'])->translatedFormat('d F Y') }}</td></tr>
        @endif
        <tr><td>Tanggal Cetak</td><td>:</td><td>{{ now()->translatedFormat('d F Y') }}</td></tr>
    </table>

    <div class="summary">
        <div class="box"><span>Saldo Awal (Opening)</span><strong>{{ $rp((int) $period['opening_balance']) }}</strong></div>
        <div class="box"><span>Total Pengeluaran</span><strong class="neg">{{ $rp($this->totalKeluar()) }}</strong></div>
        <div class="box"><span>Saldo Akhir (Closing)</span><strong class="{{ $this->closingBalance() < 0 ? 'neg' : '' }}">{{ $rp($this->closingBalance()) }}</strong></div>
    </div>

    @if ($this->closingBalance() < 0)
        <div class="callout">
            <strong>Reimbursement / Talangan PL:</strong> {{ $rp(abs($this->closingBalance())) }} —
            saldo negatif yang ditalangi PL dan diserap pada saat top up periode berikutnya.
        </div>
    @endif

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
            @forelse ($rows as $i => $trx)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ \Carbon\Carbon::parse($trx['transaction_date'])->translatedFormat('d M Y') }}</td>
                    <td>{{ $trx['category'] }}</td>
                    <td>{{ $trx['description'] }}</td>
                    <td class="text-right neg">{{ $rp((int) $trx['amount']) }}</td>
                    <td class="text-right {{ $trx['saldo_setelah'] < 0 ? 'neg' : '' }}">{{ $rp((int) $trx['saldo_setelah']) }}</td>
                    <td class="text-center">{{ $trx['has_bukti'] ? '✓' : '—' }}</td>
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
