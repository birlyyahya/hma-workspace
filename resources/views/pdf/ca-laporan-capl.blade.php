<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Transaksi CA PL</title>
    <style>
        @page { size: A4 landscape; margin: 10mm 14mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            font-size: 12px;
        }

        .rekap-title { text-align: center; margin-bottom: 10px; }
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
            white-space: nowrap;
        }
        table.rekap tr.section td,
        table.rekap tr.total td {
            background: #cdd9ec;
            font-weight: bold;
        }
        table.rekap td.num { text-align: center; width: 32px; }
        table.rekap td.tgl { white-space: nowrap; }
        table.rekap td.money, table.rekap th.money { text-align: right; white-space: nowrap; }
        table.rekap col.c-money { width: 150px; }
        table.rekap col.c-tgl { width: 150px; }

        .neg { color: #b91c1c; }

        .reimburse { margin-top: 6px; font-style: italic; font-weight: bold; font-size: 12px; }

        {{-- Tabel, bukan flex: DomPDF tidak menjaga kolom flex tetap sejajar saat
             blok ini terpotong page-break — kolom malah jadi tersusun vertikal.
             Tabel + page-break-inside:avoid mendorong seluruh blok ke halaman
             berikutnya sebagai satu kesatuan bila tak muat, bukan terpotong. --}}
        table.signrow {
            margin-top: 18px;
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        .signrow td.col { width: 33.33%; text-align: center; vertical-align: top; }
        .signrow .role-top { margin-bottom: 45px; }
        .signrow .name { font-weight: bold; text-decoration: underline; }
        .signrow .role { font-weight: bold; }
        .place-date { margin-top: 14px; font-weight: bold; }
    </style>
</head>
<body>
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
        <p>Rekap Transaksi Pembukuan Cash Advance</p>
        <p>{{ $projectName }}</p>
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
                <td class="tgl">{{ $mulai?->translatedFormat('d F Y') ?? '—' }}</td>
                <td>Uang CA PL</td>
                <td class="money">Rp {{ number_format($opening, 0) }}</td>
                <td class="money"></td>
                <td class="money">Rp {{ number_format($opening, 0) }}</td>
            </tr>
            @foreach ($penerimaan as $i => $trx)
            <tr>
                <td class="num">{{ $i + 2 }}</td>
                <td class="tgl">{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d F Y') }}</td>
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
            <tr>
                <td class="num">{{ $i + 1 }}</td>
                <td class="tgl">{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d F Y') }}</td>
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

    <table class="signrow">
        <tr>
            <td class="col">
                <div class="role-top">Dibuat Oleh,</div>
                <div class="name">{{ $preparedBy }}</div>
                <div class="role">Project Leader</div>
            </td>
            <td class="col">
                <div class="role-top">Mengetahui,</div>
                <div class="name">Andre Lukmana Budhiarto</div>
                <div class="role">IT Manager</div>
            </td>
            <td class="col">
                <div class="role-top">Menyetujui,</div>
                <div class="name">Didik Setyo Nugroho</div>
                <div class="role">MAP Manager</div>
            </td>
        </tr>
    </table>
</body>
</html>
