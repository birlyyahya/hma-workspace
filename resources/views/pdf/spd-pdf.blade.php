@php
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Field rich-text SPD dibuat lewat editor bullet/list-only. Batasi tag yang
 * dirender agar aman (hanya list & inline dasar) lalu jatuhkan ke '-' bila kosong.
 */
$richText = function ($value, string $fallback = '-'): string {
    $html = trim(strip_tags((string) $value, '<ul><ol><li><p><br><strong><em><b><i><u><s>'));

    if ($html === '' || trim(strip_tags($html)) === '') {
        return e($fallback);
    }

    return $html;
};

$username = data_get($user, 'name', '-');
$role = data_get($role, 'name', '-');
$task = $richText(data_get($spd, 'task'));
$department = $richText(data_get($spd, 'department'));
$destination = $richText(data_get($spd, 'destination'), 'Terlampir');
$address = $richText(data_get($spd, 'address'), 'Terlampir');
$masaTugas = $richText(data_get($spd, 'date'), '-');
$createdAt = data_get($spd, 'created_at');
$isSubmitted = (bool) data_get($spd, 'is_submitted', false);
$isApproved = (bool) data_get($spd, 'is_approved', false);

$tanggalDibuat = $createdAt
? Carbon::parse($createdAt)->locale('id')->translatedFormat('d F Y')
: Carbon::now()->locale('id')->translatedFormat('d F Y');

$idPadded = str_pad((string) data_get($spd, 'number', '0'), 2, '0', STR_PAD_LEFT);
$monthRoman = (function ($m) {
$r = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
return $r[((int) $m) - 1] ?? '';
})(Carbon::now()->month);
$year = Carbon::now()->year;
$refNo = "HMA/IT RnD/SPD/{$idPadded}/{$monthRoman}/{$year}";

// Signatures
$ttdAndrePath = public_path('img/ttd/ttd-andre.png');
$ttdIrwanPath = public_path('img/ttd/ttd-irwan.png');

$bodyData = compact(
    'username', 'role', 'task', 'department', 'destination', 'address', 'masaTugas',
    'tanggalDibuat', 'refNo', 'isSubmitted', 'isApproved', 'ttdAndrePath', 'ttdIrwanPath',
);
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

        .header .stamp-cell {
            width: 120px;
            text-align: right;
            vertical-align: top;
        }

        .header .stamp {
            display: inline-block;
            border: 2px solid #b91c1c;
            border-radius: 4px;
            color: #b91c1c;
            font-size: 10pt;
            font-weight: 700;
            line-height: 1.15;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            text-align: center;
            padding: 6px 10px;
            transform: rotate(-8deg);
            opacity: 0.9;
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

        table.detail td.rich ul,
        table.detail td.rich ol {
            margin: 0;
            padding-left: 18px;
        }

        table.detail td.rich p {
            margin: 0 0 2px;
        }

        table.detail td.rich p:last-child {
            margin-bottom: 0;
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
    {{-- ── Halaman 1: SPD (TTD mengikuti checklist) ── --}}
    @include('pdf.partials.spd-body', array_merge($bodyData, ['adminCopy' => false]))

    {{-- ── Halaman 2: SPD Administrasi (TTD lengkap statis + stempel lampiran) ── --}}
    <div class="page-break"></div>
    @include('pdf.partials.spd-body', array_merge($bodyData, ['adminCopy' => true]))

    {{-- ── Halaman 3+: Lampiran gambar (PDF digabung terpisah oleh service merge) ── --}}
    @if (! empty($attachmentImage['data']))
    <div class="page-break"></div>
    <div class="page">
        <div class="attachment-title">LAMPIRAN</div>
        <div class="attachment-wrap">
            <img src="{{ $attachmentImage['data'] }}" alt="Lampiran SPD">
        </div>
    </div>
    @endif

</body>
</html>
