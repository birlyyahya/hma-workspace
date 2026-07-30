<?php

use App\Services\CaCache;
use App\Services\ProjectCache;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.ca-pdf', ['title' => 'Rekap Transaksi CA PL'])]
class extends Component
{
    /** @var array<string, mixed> */
    public array $balance = [];

    /** Project yang melibatkan user (PL pilih salah satu untuk judul laporan). */
    /** @var array<int, array{id: int, code: ?string, name: ?string}> */
    public array $projects = [];

    public ?int $selectedProjectId = null;

    /** @var array<int, array<string, mixed>> */
    public array $penerimaan = [];

    /** @var array<int, array<string, mixed>> */
    public array $pengeluaran = [];

    public function mount(?string $kodeCa = null): void
    {
        $userId = Auth::id();

        $this->projects = app(ProjectCache::class)->involvedProjects($userId);
        $this->selectedProjectId = $this->projects[0]['id'] ?? null;

        $dompets = collect(app(CaCache::class)->DompetCaActiveTransaksi($userId));

        $wallet = $kodeCa !== null
            ? $dompets->firstWhere('kode_ca', $kodeCa)
            : $dompets->firstWhere('is_active', 1);

        $this->balance = $wallet ?? [];

        if (! empty($this->balance['kode_ca'])) {
            $transaksi = collect(app(CaCache::class)->transaksiCaPlActive($this->balance['kode_ca'])['data'] ?? []);

            $this->penerimaan = $transaksi->where('jenis', 'pemasukan')->values()->all();
            $this->pengeluaran = $transaksi->where('jenis', 'pengeluaran')->values()->all();
        }
    }

    /**
     * Nama project terpilih untuk header; fallback ke judul_kegiatan bila
     * user tak terlibat project apa pun.
     */
    public function projectName(): string
    {
        $project = collect($this->projects)->firstWhere('id', $this->selectedProjectId);

        return $project['name'] ?? ($this->balance['judul_kegiatan'] ?? '-');
    }
}; ?>

<div>
    <style>
        .rekap-title { text-align: center; margin-bottom: 18px; }
        .rekap-title p { margin: 0; font-weight: bold; text-transform: uppercase; font-size: 13px; line-height: 1.5; }

        table.rekap { width: 100%; border-collapse: collapse; }
        table.rekap th, table.rekap td {
            border: 1px solid #000;
            padding: 5px 7px;
            font-size: 11.5px;
            vertical-align: top;
        }
        table.rekap thead th {
            background: #b9c7e2;
            text-align: center;
            font-weight: bold;
        }
        table.rekap tr.section td,
        table.rekap tr.total td {
            background: #cdd9ec;
            font-weight: bold;
        }
        table.rekap td.num { text-align: center; width: 32px; }
        table.rekap td.money, table.rekap th.money { text-align: right; white-space: nowrap; }
        table.rekap col.c-money { width: 130px; }
        table.rekap col.c-tgl { width: 120px; }

        .reimburse { margin-top: 8px; font-style: italic; font-weight: bold; font-size: 12px; }

        .signrow {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            text-align: center;
        }
        .signrow .col { width: 30%; }
        .signrow .role-top { margin-bottom: 70px; }
        .signrow .name { font-weight: bold; text-decoration: underline; }
        .signrow .role { font-weight: bold; }
        .place-date { margin-top: 28px; font-weight: bold; }

        @media print {
            .no-print { display: none; }
        }
    </style>

    {{-- Kartu kontrol pemilihan project (tidak ikut tercetak) --}}
    @if (count($projects) > 1)
    <div class="no-print" style="max-width:800px;margin:0 auto 16px;">
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm">
            <label class="mb-2 block text-xs font-medium text-zinc-500">Pilih project untuk judul laporan</label>
            <x-search-select
                model="selectedProjectId"
                :live="true"
                :options="collect($projects)->map(fn ($p) => ['value' => $p['id'], 'label' => $p['name']])->all()"
                placeholder="Pilih project..."
                searchPlaceholder="Cari project..." />
        </div>
    </div>
    @endif

    <div class="sheet">
    @php
        $opening = (float) ($balance['saldo_awal_priode'] ?? 0);
        $sumPenerimaan = $opening + collect($penerimaan)->sum(fn ($t) => (float) ($t['jumlah'] ?? 0));
        $sumPengeluaran = (float) ($balance['total_pengeluaran'] ?? collect($pengeluaran)->sum(fn ($t) => (float) ($t['jumlah'] ?? 0)));
        $saldoAkhir = (float) ($balance['saldo_akhir'] ?? 0);

        $mulai = ! empty($balance['tanggal_mulai']) ? \Carbon\Carbon::parse($balance['tanggal_mulai']) : null;
        $selesai = ! empty($balance['tanggal_selesai'])
            ? \Carbon\Carbon::parse($balance['tanggal_selesai'])
            : collect($pengeluaran)->merge($penerimaan)->pluck('tanggal')->filter()->map(fn ($t) => \Carbon\Carbon::parse($t))->max();
    @endphp

    {{-- ================= HEADER ================= --}}
    <div class="rekap-title">
        <p>Rekap Transaksi Pembukuan</p>
        <p>Pemasukan dan Pengeluaran Cash Advance</p>
        <p>{{ $this->projectName() }}</p>
        <p>Tahun Anggaran {{ $balance['tahun_anggaran'] ?? now()->year }}</p>
        <p>
            {{ $mulai?->translatedFormat('d F Y') ?? '—' }}
            @if ($selesai) s/d {{ $selesai->translatedFormat('d F Y') }} @endif
        </p>
    </div>

    {{-- ================= TABEL REKAP ================= --}}
    <table class="rekap">
        <colgroup>
            <col style="width:32px">
            <col class="c-tgl">
            <col>
            <col class="c-money">
            <col class="c-money">
            <col class="c-money">
        </colgroup>
        <thead>
            <tr>
                <th>No</th>
                <th>Tanggal Laporan</th>
                <th>Transaksi</th>
                <th class="money">Penerimaan</th>
                <th class="money">Pengeluaran</th>
                <th class="money">Saldo</th>
            </tr>
        </thead>
        <tbody>
            {{-- ===== A. PENERIMAAN ===== --}}
            <tr class="section">
                <td class="num">A</td>
                <td colspan="5">PENERIMAAN</td>
            </tr>
            <tr>
                <td class="num">1</td>
                <td>{{ $mulai?->translatedFormat('d F Y') ?? '—' }}</td>
                <td>Uang CA PL</td>
                <td class="money">Rp {{ number_format($opening, 0) }}</td>
                <td class="money"></td>
                <td class="money">Rp {{ number_format($opening, 0) }}</td>
            </tr>
            @foreach ($penerimaan as $i => $trx)
            <tr wire:key="in-{{ $trx['id_transaksi'] }}">
                <td class="num">{{ $i + 2 }}</td>
                <td>{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d F Y') }}</td>
                <td>{{ $trx['deskripsi'] ?? '-' }}</td>
                <td class="money">Rp {{ number_format((float) ($trx['jumlah'] ?? 0), 0) }}</td>
                <td class="money"></td>
                <td class="money">Rp {{ number_format((float) ($trx['saldo_setelah'] ?? 0), 0) }}</td>
            </tr>
            @endforeach

            {{-- ===== B. PENGELUARAN ===== --}}
            <tr class="section">
                <td class="num">B</td>
                <td colspan="5">PENGELUARAN</td>
            </tr>
            @forelse ($pengeluaran as $i => $trx)
            <tr wire:key="out-{{ $trx['id_transaksi'] }}">
                <td class="num">{{ $i + 1 }}</td>
                <td>{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d F Y') }}</td>
                <td>{{ $trx['deskripsi'] ?? '-' }}</td>
                <td class="money"></td>
                <td class="money">Rp {{ number_format((float) ($trx['jumlah'] ?? 0), 0) }}</td>
                <td class="money {{ ($trx['saldo_setelah'] ?? 0) < 0 ? 'neg' : '' }}">Rp {{ number_format((float) ($trx['saldo_setelah'] ?? 0), 0) }}</td>
            </tr>
            @empty
            <tr>
                <td class="num"></td>
                <td colspan="5">Belum ada pengeluaran pada periode ini</td>
            </tr>
            @endforelse

            {{-- ===== JUMLAH TOTAL ===== --}}
            <tr class="total">
                <td colspan="3" style="text-align:center">JUMLAH TOTAL</td>
                <td class="money">Rp {{ number_format($sumPenerimaan, 0) }}</td>
                <td class="money">Rp {{ number_format($sumPengeluaran, 0) }}</td>
                <td class="money {{ $saldoAkhir < 0 ? 'neg' : '' }}">Rp {{ number_format($saldoAkhir, 0) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($saldoAkhir < 0)
    <p class="reimburse">*reimbursement Kegiatan Rp {{ number_format(abs($saldoAkhir), 0) }}</p>
    @endif

    {{-- ================= TANDA TANGAN ================= --}}
    <p class="place-date">Jakarta, {{ now()->translatedFormat('d F Y') }}</p>

    <div class="signrow">
        <div class="col">
            <div class="role-top">Dibuat Oleh,</div>
            <div class="name">{{ Auth::user()->name }}</div>
            <div class="role">Project Leader</div>
        </div>
        <div class="col">
            <div class="role-top">Mengetahui,</div>
            <div class="name">Andre Lukmana Budhiarto</div>
            <div class="role">IT Manager</div>
        </div>
        <div class="col">
            <div class="role-top">Menyetujui,</div>
            <div class="name">Didik Setyo Nugroho</div>
            <div class="role">MAP Manager</div>
        </div>
    </div>
    </div>{{-- /.sheet --}}
</div>
