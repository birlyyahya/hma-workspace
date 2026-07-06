{{--
    Satu halaman utama SPD. Dipakai dua kali oleh spd-pdf:
    - $adminCopy = false → Halaman 1: TTD mengikuti checklist (is_submitted / is_approved).
    - $adminCopy = true  → Halaman 2: salinan administrasi, TTD lengkap statis + stempel lampiran.

    Field rich-text (task/department/destination/address/masaTugas) sudah
    disanitasi di spd-pdf dan diterima sebagai HTML siap render.
--}}
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
            @if ($adminCopy)
            <td class="stamp-cell">
                <div class="stamp">Lampiran<br>Administrasi</div>
            </td>
            @endif
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
            <td class="rich">{!! $task !!}</td>
        </tr>
        <tr>
            <td class="label">Satuan Kerja</td>
            <td class="colon">:</td>
            <td class="rich">{!! $department !!}</td>
        </tr>
        <tr>
            <td class="label">Tujuan/lokasi</td>
            <td class="colon">:</td>
            <td class="rich">{!! $destination !!}</td>
        </tr>
        <tr>
            <td class="label">Alamat</td>
            <td class="colon">:</td>
            <td class="rich">{!! $address !!}</td>
        </tr>
        <tr>
            <td class="label">Masa Tugas</td>
            <td class="colon">:</td>
            <td class="rich">{!! $masaTugas !!}</td>
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
                @if (($adminCopy || $isSubmitted) && file_exists($ttdAndrePath))
                <img class="ttd" src="{{ $ttdAndrePath }}" alt="TTD Andre">
                @endif
            </td>
            <td class="sig-area" style="padding-left: 90px;">
                @if (($adminCopy || ($isSubmitted && $isApproved)) && file_exists($ttdIrwanPath))
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
</div>
