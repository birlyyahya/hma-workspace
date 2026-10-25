@php
use Carbon\Carbon;

$nama = data_get($izin, 'users.name', 'IT');
$divisi = data_get($izin, 'users.role.name', 'IT');
$reason = data_get($izin, 'reason', 'Tugas luar kantor');
$jenisIzin = [
    'Datang terlambat',
    'Tugas luar kantor',
    'Lain-lain',
    'Pulang lebih awal',
    'Dinas luar kota',
    'Sakit',
];
$description = data_get($izin, 'description', '-');

$startDate = data_get($izin, 'start_date');
$endDate = data_get($izin, 'end_date');
$startTime = data_get($izin, 'start_time');
$endTime = data_get($izin, 'end_time');
$createdAt = data_get($izin, 'created_at');

$approvalSignatures = data_get($izin, 'approval_signatures', []);

if (empty($approvalSignatures)) {
    $approvalSignatures = [
        [
            'title' => 'Mengetahui Oleh',
            'role' => 'Atasan Langsung',
            'name' => data_get($izin, 'atasan_langsung_name', data_get($izin, 'asmen_name', 'Atasan Langsung')),
            'signature_base64' => data_get($izin, 'admins_base64'),
        ],
        [
            'title' => 'Disetujui Oleh',
            'role' => 'Manager',
            'name' => data_get($izin, 'manager_name', 'Manager'),
            'signature_base64' => data_get($izin, 'superadmins_base64'),
        ],
    ];
}

$tanggal = '-';
if ($startDate) {
$tanggal = Carbon::parse($startDate)->locale('id')->translatedFormat('l, d-m-Y');
if ($endDate && $endDate !== $startDate) {
$tanggal .= ' s/d ' . Carbon::parse($endDate)->format('d-m-Y');
}
}

$jam = ($startTime && $endTime) ? "{$startTime} s/d {$endTime}" : '-';
$tanggalTtd = $createdAt
? Carbon::parse($createdAt)->locale('id')->translatedFormat('d F Y')
: Carbon::now()->locale('id')->translatedFormat('d F Y');
@endphp

<x-layouts.pdf-layout>
    <div class="container">
        <table class="header-table">
            <tr>
                <td class="logo-cell" width="18%">
                    <img src="{{ public_path('img/logo/logo-hma2.png') }}" class="logo" alt="HMA Logo">
                </td>
                <td class="title-cell" width="82%">
                    <div class="title">FORM IZIN</div>
                    <div class="title company-title">PT HANATEKINDO MULIA ABADI</div>
                </td>
            </tr>
        </table>

        <div class="content">
            <table class="form-table">
                <tr>
                    <td colspan="3" class="intro-text">Yang bertanda tangan dibawah ini:</td>
                </tr>

                <tr>
                    <td class="label">Nama</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $nama }}</td>
                </tr>

                <tr>
                    <td class="label">Divisi</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $divisi }}</td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td colspan="3" class="leave-reason-cell">
                        <div class="leave-reason-title">Mengajukan izin :</div>
                        <table class="leave-reason-grid">
                            @foreach ($jenisIzin as $jenis)
                                @if ($loop->first || $loop->index % 3 === 0)
                                    <tr>
                                @endif
                                <td class="leave-reason-option">
                                    <span class="checkbox">
                                        @if ($reason === $jenis)
                                            <span class="checkbox-mark"></span>
                                        @endif
                                    </span>
                                    <span class="reason-text">{{ $jenis }}</span>
                                </td>
                                @if ($loop->last || $loop->index % 3 === 2)
                                    </tr>
                                @endif
                            @endforeach
                        </table>
                    </td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td class="label">Hari/Tanggal</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $tanggal }}</td>
                </tr>

                <tr>
                    <td class="label">Pukul</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $jam }}</td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td colspan="3" class="intro-text">Dikarenakan keperluan / alasan sebagai berikut:</td>
                </tr>

                <tr>
                    <td colspan="3" class="value multi">{{ $description }}</td>
                </tr>
            </table>

            <div class="signature-section">
                <p class="signature-date">Jakarta, {{ $tanggalTtd }}</p>

                <table class="signature-table">
                    <tr>
                        <td class="signature-box">
                            <div class="signature-title">Pemohon</div>

                            <div class="signature-image">
                                @if(data_get($izin, 'users.signature'))
                                <img src="{{ data_get($izin, 'users.signature') }}" >
                                @endif
                            </div>

                            <div class="signature-footer">
                                {{ $nama }}
                            </div>
                        </td>

                        @foreach ($approvalSignatures as $approval)
                            <td class="signature-box">
                                <div class="signature-title">
                                    {{ $approval['title'] }}
                                </div>

                                <div class="signature-image">
                                    @if($approval['signature_base64'])
                                    <img src="{{ $approval['signature_base64'] }}" alt="Tanda tangan {{ $approval['name'] }}">
                                    @endif
                                </div>

                                <div class="signature-footer">
                                    {{ $approval['name'] }}
                                </div>
                            </td>
                        @endforeach
                            <td class="signature-box">
                                <div class="signature-title">
                                    HRD
                                </div>

                                <div class="signature-image">
                                </div>

                                <div class="signature-footer">
                                </div>
                            </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</x-layouts.pdf-layout>
