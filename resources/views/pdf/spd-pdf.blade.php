@php
use Carbon\Carbon;

$username = data_get($user, 'name', '-');
$role = data_get($role, 'name', '-');
$task = data_get($spd, 'task', '-');
$department = data_get($spd, 'department', '-');
$destination = data_get($spd, 'destination', 'Terlampir');
$address = data_get($spd, 'address', 'Terlampir');
$startDate = data_get($spd, 'start_date');
$endDate = data_get($spd, 'end_date');
$createdAt = data_get($spd, 'created_at');
$isSubmitted = (bool) data_get($spd, 'is_submitted', false);
$isApproved = (bool) data_get($spd, 'is_approved', false);

$masaTugas = '-';
if ($startDate && $endDate) {
$masaTugas = Carbon::parse($startDate)->locale('id')->translatedFormat('d F Y')
. ' s/d ' . Carbon::parse($endDate)->locale('id')->translatedFormat('d F Y');
}

$tanggalDibuat = $createdAt
? Carbon::parse($createdAt)->locale('id')->translatedFormat('d F Y')
: Carbon::now()->locale('id')->translatedFormat('d F Y');

$idPadded = str_pad((string) data_get($spd, 'id', '0'), 2, '0', STR_PAD_LEFT);
$monthRoman = (function ($m) {
$r = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
return $r[((int) $m) - 1] ?? '';
})(Carbon::now()->month);
$year = Carbon::now()->year;
$refNo = "HMA/IT RnD/SPD/{$idPadded}/{$monthRoman}/{$year}";

// Signatures
$ttdAndrePath = public_path('img/ttd/ttd-andre.png');
$ttdIrwanPath = public_path('img/ttd/ttd-irwan.png');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4 portrait;
            margin-top: 10mm;
            margin-bottom: 10mm;
            margin-left: 18mm;
            margin-right: 18mm;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #111;
            background-color: #111;
            font-size: 10.5pt;
            line-height: 1.45;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .page {
            background-color: #fff;
            width: 175mm;
            min-height: 277mm;
            padding: 10mm 18mm;
        }

        table {
            max-width: 100%;
        }

        td {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Header */
        .header {
            width: 100%;
            border-collapse: collapse;
        }

        .header td {
            vertical-align: middle;
            padding: 0;
        }

        .header .logo {
            width: 110px;
        }

        .header .logo img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .header .company {
            padding-left: 14px;
        }

        .header .company .name {
            font-size: 20pt;
            font-weight: 700;
            color: #b91c1c;
            letter-spacing: 0.5px;
        }

        .header .company .info {
            font-size: 9pt;
            line-height: 1.5;
            color: #1f1f1f;
            margin-top: 2px;
        }

        .divider {
            border: none;
            border-top: 2px solid #b91c1c;
            margin: 6px 0 0;
        }

        /* Title */
        .title-block {
            text-align: center;
            margin: 22px 0 10px;
        }

        .title-block .title {
            font-weight: 700;
            font-size: 12pt;
            text-decoration: underline;
        }

        .title-block .ref {
            font-weight: 700;
            font-size: 11pt;
        }

        /* Body */
        .intro {
            margin: 18px 0 14px;
            text-align: justify;
        }

        table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        table.detail td {
            vertical-align: top;
            padding: 2px 0;
        }

        table.detail .label {
            width: 36%;
        }

        table.detail .colon {
            width: 12px;
        }

        .closing {
            margin-top: 18px;
            text-align: justify;
        }

        /* Issue */
        .issue {
            margin-top: 22px;
            width: 50%;
            border-collapse: collapse;
            text-align: left;
        }

        .issue td {
            padding: 2px 0;
            vertical-align: top;
            text-align: left;
        }

        .issue .label {
        }

        /* Signatures */
        .company-name {
            margin-top: 28px;
            font-weight: 700;
        }

        .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        .sig-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }

        .sig-table .role {
            font-size: 11pt;
            padding-bottom: 4px;
        }

        .sig-area {
            height: 95px;
            text-align: left;
            position: relative;
        }

        .sig-area img.ttd {
            max-height: 90px;
            max-width: 180px;
            display: block;
        }

        .sig-table .name {
            font-weight: 700;
            text-decoration: underline;
        }

        .sig-table .position {
            font-size: 10.5pt;
            color: #1f1f1f;
        }

        /* Cc */
        .cc {
            margin-top: 28px;
            font-size: 10pt;
        }

        .cc .label {
            font-weight: 600;
        }

        .cc ul {
            margin: 2px 0 0;
            padding-left: 0;
            list-style: none;
        }

        .cc li {
            padding-left: 0;
        }

        /* Attachment page */
        .page-break {
            page-break-before: always;
        }

        .attachment-title {
            text-align: center;
            font-weight: 700;
            font-size: 13pt;
            text-decoration: underline;
            margin-bottom: 16px;
        }

        .attachment-wrap {
            text-align: center;
        }

        .attachment-wrap img {
            max-width: 100%;
            max-height: 230mm;
            border: 1px solid #ddd;
        }

    </style>
</head>
<body>
    <div class="page">
        <table class="header">
            <tr>
                <td class="logo">
                    <img src="{{ public_path('img/logo/logo-hma2.png') }}" alt="HMA">
                </td>
                <td class="company">
                    <div class="name">PT HANATEKINDO MULIA ABADI</div>
                    <div class="info">
                        Komplek Golden Fatmawati Blok J-10, Jl. RS. Fatmawati No.15, Jakarta Selatan 12420<br>
                        Tel : (021) 750 8989 &nbsp;&nbsp;|&nbsp;&nbsp; Fax : (021) 750 7519<br>
                        Web : www.hanatekindo.co.id &nbsp;&nbsp;|&nbsp;&nbsp; E-mail : info@hanatekindo.co.id
                    </div>
                </td>
            </tr>
        </table>
        <hr class="divider">

        <div class="title-block">
            <div class="title">SURAT PERJALANAN DINAS</div>
            <div class="ref">Ref. No : {{ $refNo }}</div>
        </div>

        <div class="intro">
            Yang bertandatangan dibawah ini, Manager IT RnD PT. HANATEKINDO MULIA ABADI, menugaskan
            karyawan kami dibawah ini :
        </div>

        <table class="detail">
            <tr>
                <td class="label">Nama</td>
                <td class="colon">:</td>
                <td>{{ $username }}</td>
            </tr>
            <tr>
                <td class="label">Jabatan</td>
                <td class="colon">:</td>
                <td>{{ $role }}</td>
            </tr>
            <tr>
                <td class="label">Melaksanakan tugas</td>
                <td class="colon">:</td>
                <td>{{ $task }}</td>
            </tr>
            <tr>
                <td class="label">Satuan Kerja</td>
                <td class="colon">:</td>
                <td>{{ $department }}</td>
            </tr>
            <tr>
                <td class="label">Tujuan/lokasi</td>
                <td class="colon">:</td>
                <td>{{ $destination }}</td>
            </tr>
            <tr>
                <td class="label">Alamat</td>
                <td class="colon">:</td>
                <td>{{ $address }}</td>
            </tr>
            <tr>
                <td class="label">Masa Tugas</td>
                <td class="colon">:</td>
                <td>{{ $masaTugas }}</td>
            </tr>
        </table>

        <div class="closing">
            Bilamana selesai menjalankan tugas, harap segera menyelesaikan dan melaporkan berkas-berkas
            administrasi (laporan petty cash dan activity report) selambat-lambatnya 1 (satu) hari setelah tanggal
            kepulangan.
        </div>

        <div class="closing">
            Demikian Surat Perjalanan Dinas ini dibuat untuk dipergunakan semestinya.
        </div>

        <table class="issue">
            <tr>
                <td class="label" style="width: 50%;">Dikeluarkan di</td>
                <td class="colon" style="width: 12px;">:</td>
                <td>Jakarta</td>
            </tr>
            <tr>
                <td class="label" style="width: 50%;">Tanggal</td>
                <td class="colon" style="width: 12px;">:</td>
                <td>{{ $tanggalDibuat }}</td>
            </tr>
        </table>

        <div class="company-name">PT. HANATEKINDO MULIA ABADI</div>
        <table class="sig-table">
            <tr>
                <td class="role">Diajukan Oleh,</td>
                <td class="role" style="padding-left: 90px;">Menyetujui,</td>
            </tr>
            <tr>
                <td class="sig-area">
                    @if ($isSubmitted && file_exists($ttdAndrePath))
                    <img class="ttd" src="{{ $ttdAndrePath }}" alt="TTD Andre">
                    @endif
                </td>
                <td class="sig-area" style="padding-left: 90px;">
                    @if ($isSubmitted && $isApproved && file_exists($ttdIrwanPath))
                    <img class="ttd" src="{{ $ttdIrwanPath }}" alt="TTD Irwan">
                    @endif
                </td>
            </tr>
            <tr>
                <td>
                    <div class="name">Andre Lukmana Budhiarto</div>
                    <div class="position">Manager IT RnD</div>
                </td>
                <td style="padding-left: 90px;">
                    <div class="name">Ranap Irwan Rajagukguk</div>
                    <div class="position">Direktur</div>
                </td>
            </tr>
        </table>

        <div class="cc">
            <div class="label">Cc:</div>
            <ul>
                <li>- HRGA Dept</li>
                <li>- Finance Dept</li>
            </ul>
        </div>

        {{-- ── Lampiran (image attachment) ── --}}
        @if (! empty($attachmentImage['data']))
        <div class="page-break"></div>
        <div class="attachment-title">LAMPIRAN</div>
        <div class="attachment-wrap">
            <img src="{{ $attachmentImage['data'] }}" alt="Lampiran SPD">
        </div>
        @endif
    </div>

</body>
</html>
