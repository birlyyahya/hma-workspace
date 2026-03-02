@php
use Carbon\Carbon;

$nama = data_get($izin, 'users.name', 'Muhammad Birly Yahya');
$divisi = data_get($izin, 'users.divisi', 'IT');
$reason = data_get($izin, 'reason', 'Tugas luar kantor');
$description = data_get($izin, 'description', '-');

$startDate = data_get($izin, 'start_date');
$endDate = data_get($izin, 'end_date');
$startTime = data_get($izin, 'start_time');
$endTime = data_get($izin, 'end_time');
$createdAt = data_get($izin, 'created_at');

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
                <td class="logo-cell" width="20%">
                    <img src="{{ public_path('img/logo/logo-hma2.png') }}" class="logo" alt="HMA Logo">
                </td>
                <td width="80%">
                    <div class="title">FORM IZIN</div>
                    <div class="title">PT HANATEKINDO MULIA ABADI</div>
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
                    <td class="label">Mengajukan izin</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $reason }}</td>
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
                            <div class="signature-title">Disetujui oleh<br>Manager</div>

                            <div class="signature-image">
                                @if(data_get($izin, 'superadmins_base64'))
                                <img src="{{ data_get($izin, 'superadmins_base64') }}" alt="Tanda tangan manager">
                                @endif
                            </div>

                            <div class="signature-footer">
                                {{ data_get($izin, 'superadmin_username', 'Manager') }}<br>
                                {{ $tanggalTtd }}
                            </div>
                        </td>

                        <td class="signature-box">
                            <div class="signature-title">Mengetahui oleh<br>Atasan Langsung</div>

                            <div class="signature-image">
                                @if(data_get($izin, 'admins_base64'))
                                <img src="{{ data_get($izin, 'admins_base64') }}" alt="Tanda tangan atasan langsung">
                                @endif
                            </div>

                            <div class="signature-footer">
                                {{ data_get($izin, 'admin_username', 'Atasan Langsung') }}<br>
                                {{ $tanggalTtd }}
                            </div>
                        </td>

                        <td class="signature-box">
                            <div class="signature-title">Pemohon</div>

                            <div class="signature-image">
                                @if(data_get($izin, 'pemohon_base64'))
                                <img src="{{ data_get($izin, 'pemohon_base64') }}" alt="Tanda tangan pemohon">
                                @endif
                            </div>

                            <div class="signature-footer">
                                {{ $nama }}<br>
                                {{ $tanggalTtd }}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</x-layouts.pdf-layout>
