<?php

use App\Models\User;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Preview SPD'])]
class extends Component {
    public $id;

    public ?array $spd = null;

    public function mount(): void
    {
        $this->loadSpd();
    }

    protected function loadSpd(): void
    {
        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');
            $response = Http::timeout(30)->get($apiIzin . '/global/dar/activity/list-spd', [
                'per_page' => 1000,
            ])->json();

            $rows = $response['data'] ?? [];
            $this->spd = collect($rows)->firstWhere('id', (int) $this->id);
        } catch (\Throwable $e) {
            Log::error('SPD preview load failed', ['message' => $e->getMessage(), 'id' => $this->id]);
            $this->spd = null;
        }
    }

    public function downloadPdf()
    {
        if (! $this->spd) {
            Toaster::error('Data SPD tidak tersedia.');

            return;
        }

        $user = User::find($this->spd['user_id'] ?? null);
        $role = $user->role;
        $attachmentImage = $this->fetchAttachmentImage($this->spd['attachment_url'] ?? null);


        $pdf = Pdf::loadView('pdf.spd-pdf', [
            'spd' => $this->spd,
            'user' => $user,
            'role' => $role,
            'attachmentImage' => $attachmentImage,
        ])->setPaper('A4', 'portrait');

        $filename = 'SPD-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT) . '.pdf';

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            $filename,
        );
    }

    /**
     * @return array{data:string,mime:string}|null
     */
    protected function fetchAttachmentImage(?string $url): ?array
    {
        if (! $url) {
            return null;
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();
            $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $body) ?: 'image/png';

            return [
                'data' => 'data:' . $mime . ';base64,' . base64_encode($body),
                'mime' => $mime,
            ];
        } catch (\Throwable $e) {
            Log::warning('SPD attachment fetch failed', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }
    public function sendText()
    {
        $user = User::find($this->spd['user_id']);
        $message = 'Test Notification';
        // kirim ke service
        NotificationService::send($user, $message, $this->spd);
    }
}; ?>

<div>
    <style>
        /* A4 page styling */
        @page {
            margin: 0;
            overflow: hidden;
        }

        .a4-paper {
            width: 210mm;
            min-height: 257mm;
            padding: 8mm 22mm;
            background: #fff;
            margin: 0 auto;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .08), 0 8px 24px rgba(0, 0, 0, .08);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #111;
        }

        .a4-paper .doc-header {
            width: 100%;
            border-collapse: collapse;
        }

        .a4-paper .doc-header td {
            vertical-align: middle;
            padding: 0;
        }

        .a4-paper .doc-header .logo {
            width: 110px;
        }

        .a4-paper .doc-header .logo img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .a4-paper .doc-header .company {
            padding-left: 14px;
        }

        .a4-paper .doc-header .company .name {
            font-size: 22pt;
            font-weight: 700;
            color: #b91c1c;
            letter-spacing: 1px;
        }

        .a4-paper .doc-header .company .info {
            font-size: 9.5pt;
            line-height: 1.5;
            color: #1f1f1f;
            margin-top: 2px;
        }

        .a4-paper .divider {
            border: none;
            border-top: 2px solid #b91c1c;
            margin: 6px 0 0;
        }

        .a4-paper .title-block {
            text-align: center;
            margin: 22px 0 10px;
        }

        .a4-paper .title-block .title {
            font-weight: 700;
            font-size: 12pt;
            text-decoration: underline;
        }

        .a4-paper .title-block .ref {
            font-weight: 700;
            font-size: 11pt;
        }

        .a4-paper .intro,
        .a4-paper .closing {
            margin: 18px 0 14px;
            text-align: justify;
        }

        .a4-paper table.detail {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .a4-paper table.detail td {
            vertical-align: top;
            padding: 2px 0;
        }

        .a4-paper table.detail .label {
            width: 36%;
        }

        .a4-paper table.detail .colon {
            width: 12px;
        }

        .a4-paper .issue {
            margin-top: 22px;
            width: 100%;
            border-collapse: collapse;
        }

        .a4-paper .issue td {
            padding: 2px 0;
            vertical-align: top;
        }

        .a4-paper .issue .label {
            width: 28%;
        }

        .a4-paper .company-name {
            margin-top: 28px;
            font-weight: 700;
        }

        .a4-paper .sig-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        .a4-paper .sig-table td {
            width: 50%;
            vertical-align: top;
            padding-right: 12px;
        }

        .a4-paper .sig-table .role {
            font-size: 11pt;
            padding-bottom: 4px;
        }

        .a4-paper .sig-area {
            height: 95px;
            position: relative;
        }

        .a4-paper .sig-area img.ttd {
            max-height: 90px;
            max-width: 180px;
            display: block;
        }

        .a4-paper .sig-table .name {
            font-weight: 700;
            text-decoration: underline;
        }

        .a4-paper .sig-table .position {
            font-size: 10.5pt;
            color: #1f1f1f;
        }

        .a4-paper .cc {
            margin-top: 28px;
            font-size: 10pt;
        }

        .a4-paper .cc .label {
            font-weight: 600;
        }

        .a4-paper .cc ul {
            margin: 2px 0 0;
            padding-left: 0;
            list-style: none;
        }

        .a4-paper .cc li {
            padding-left: 0;
        }

        .page-break {
            page-break-before: always;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .print-area,
            .print-area * {
                visibility: visible;
            }

            .print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .a4-paper {
                box-shadow: none;
                margin: 0;
                width: 100%;
                min-height: 200mm;
            }
        }

    </style>

    <div class="min-h-screen py-8 px-4">
        @if (!$spd)
        <div class="mx-auto max-w-2xl rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200">
            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-400">
                <flux:icon name="exclamation-triangle" class="h-6 w-6" />
            </div>
            <h1 class="text-lg font-semibold text-zinc-900">SPD tidak ditemukan</h1>
            <p class="mt-1 text-sm text-zinc-600">Data SPD dengan ID <span class="font-semibold">{{ $id }}</span> tidak tersedia.</p>
            <a href="{{ route('izin') }}" class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800">
                <flux:icon name="arrow-left" class="h-4 w-4" />
                Kembali ke Izin
            </a>
        </div>
        @else
        @php
        $task = $spd['task'] ?? '-';
        $username = User::find($this->spd['user_id']);
        $department = $spd['department'] ?? '-';
        $destination = $spd['destination'] ?? 'Terlampir';
        $address = $spd['address'] ?? 'Terlampir';
        $startDate = $spd['start_date'] ?? null;
        $endDate = $spd['end_date'] ?? null;
        $createdAt = $spd['created_at'] ?? null;
        $isSubmitted = (bool) ($spd['is_submitted'] ?? false);
        $isApproved = (bool) ($spd['is_approved'] ?? false);
        $attachmentUrl = $spd['attachment_url'] ?? null;
        $attachmentExt = strtolower(pathinfo(parse_url($attachmentUrl ?? '', PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
        $isAttachmentImage = in_array($attachmentExt, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

        $masaTugas = '-';
        if ($startDate && $endDate) {
        $masaTugas = Carbon::parse($startDate)->locale('id')->translatedFormat('d F Y')
        . ' s/d ' . Carbon::parse($endDate)->locale('id')->translatedFormat('d F Y');
        }

        $tanggalDibuat = $createdAt
        ? Carbon::parse($createdAt)->locale('id')->translatedFormat('d F Y')
        : Carbon::now()->locale('id')->translatedFormat('d F Y');

        $idPadded = str_pad((string) ($spd['number'] ?? '0'), 2, '0', STR_PAD_LEFT);
        $monthRoman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][Carbon::now()->month - 1];
        $year = Carbon::now()->year;
        $refNo = "HMA/IT RnD/SPD/{$idPadded}/{$monthRoman}/{$year}";

        if ($isApproved) {
        $statusLabel = 'Disetujui';
        $statusClass = 'bg-emerald-50 text-emerald-700 ring-emerald-200';
        } elseif ($isSubmitted) {
        $statusLabel = 'Menunggu Persetujuan Direktur';
        $statusClass = 'bg-amber-50 text-amber-800 ring-amber-200';
        } else {
        $statusLabel = 'Pending';
        $statusClass = 'bg-zinc-100 text-zinc-700 ring-zinc-200';
        }
        @endphp

        {{-- Toolbar (sticky, hidden when printing) --}}
        <div class="no-print mx-auto mb-5 flex  flex-wrap items-center justify-between gap-3 rounded-2xl border border-zinc-200 bg-white px-5 py-3 shadow-sm">
            <div class="flex items-center gap-3">
                <a href="{{ route('izin') }}" class="inline-flex items-center gap-1.5 rounded-full bg-zinc-50 px-3 py-1.5 text-sm font-medium text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-100">
                    <flux:icon name="arrow-left" class="h-4 w-4" />
                    Kembali
                </a>
                <div class="hidden sm:block">
                    <p class="text-sm font-semibold text-zinc-900">Preview SPD</p>
                    <p class="text-xs text-zinc-500">{{ $refNo }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                    {{ $statusLabel }}
                </span>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50">
                    <flux:icon name="printer" class="h-4 w-4" />
                    Print
                </button>
                <button type="button" wire:click="downloadPdf" wire:loading.attr="disabled" wire:target="downloadPdf" class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">
                    <flux:icon name="document-arrow-down" class="h-4 w-4" />
                    <span wire:loading.remove wire:target="downloadPdf">Download PDF</span>
                    <span wire:loading wire:target="downloadPdf">Generating...</span>
                </button>
            </div>
        </div>

        <div class="print-area max-w-[210mm] mx-auto">
            {{-- ── Page 1: SPD ── --}}
            <div class="a4-paper">
                <table class="doc-header">
                    <tr>
                        <td class="logo">
                            <img src="{{ asset('img/logo/logo-hma2.png') }}" alt="HMA">
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
                        <td>{{ $username->name }}</td>
                    </tr>
                    <tr>
                        <td class="label">Jabatan</td>
                        <td class="colon">:</td>
                        <td>{{ $username->role->name }}</td>
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
                        <td class="label">Dikeluarkan di</td>
                        <td class="colon">:</td>
                        <td>Jakarta</td>
                    </tr>
                    <tr>
                        <td class="label">Tanggal</td>
                        <td class="colon">:</td>
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
                            @if ($isSubmitted)
                            <img class="ttd" src="{{ asset('img/ttd/ttd-andre.png') }}" alt="TTD Andre">
                            @endif
                        </td>
                        <td class="sig-area" style="padding-left: 90px;">
                            @if ($isSubmitted && $isApproved)
                            <img class="ttd" src="{{ asset('img/ttd/ttd-irwan.png') }}" alt="TTD Irwan">
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
            </div>
            <div class="page-break"></div>
            {{-- ── Page 2: Attachment (image) ── --}}
            @if ($attachmentUrl && $isAttachmentImage)
            <div class="a4-paper" style="margin-top: 1.5rem;">
                <div class="title-block">
                    <div class="title">LAMPIRAN</div>
                </div>
                <div style="text-align: center; margin-top: 16px;">
                    <img src="{{ $attachmentUrl }}" alt="Lampiran" style="max-width: 100%; max-height: 230mm; border: 1px solid #ddd;" />
                </div>
            </div>
            @elseif ($attachmentUrl)
            <div class="no-print mx-auto mt-4 max-w-[210mm] rounded-2xl border border-zinc-200 bg-white px-5 py-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="grid h-10 w-10 place-items-center rounded-xl bg-red-50 text-red-600">
                        <flux:icon name="paper-clip" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-zinc-900">Lampiran tersedia</p>
                        <p class="text-xs text-zinc-500">
                            File <span class="uppercase">{{ $attachmentExt }}</span> akan digabungkan saat download PDF (jika berformat gambar) atau dapat dibuka terpisah.
                        </p>
                    </div>
                    <a href="{{ $attachmentUrl }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-50 px-3 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-100">
                        <flux:icon name="arrow-top-right-on-square" class="h-4 w-4" />
                        Buka
                    </a>
                </div>
            </div>
            @endif
        </div>
        @endif
    </div>
</div>
