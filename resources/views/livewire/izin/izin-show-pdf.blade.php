<?php

use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.pdf-layout')]
class extends Component {
    public $id;

    public function getIzinProperty()
    {
        \Log::info('Fetching izin data for ID: ' . $this->id);
        $response = Http::get(env('API_IZIN') . '/global/izin/detail/' . $this->id)->json();

        if (($response['success'] ?? false) === true) {
            return $response['data'] ?? [];
        }

        return [];
    }
}; ?>

<div class="container">
    <table class="header-table">
        <tr>
            <td class="logo-cell" width="20%">
                <img src="{{ asset('img/logo/logo-hma2.png') }}" class="logo" alt="HMA Logo">
            </td>
            <td width="80%">
                <div class="title">FORM IZIN</div>
                <div class="title">PT HANATEKINDO MULIA ABADI</div>
            </td>
        </tr>
    </table>

    <div class="body-frame">
        <div class="content">
            <table class="form-table">
                <tr>
                    <td colspan="3" class="intro-text">Yang bertanda tangan dibawah ini:</td>
                </tr>

                <tr>
                    <td class="label">Nama</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $this->izin['users']['name'] }}</td>
                </tr>

                <tr>
                    <td class="label">Divisi</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $this->izin['users']['divisi'] ?? 'IT' }}</td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td class="label">Mengajukan izin</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $this->izin['reason'] }}</td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td class="label">Hari/Tanggal</td>
                    <td class="colon">:</td>
                    <td class="value">
                        {{ Carbon::parse($this->izin['start_date'])->locale('id')->translatedFormat('l, d-m-Y') }}
                    </td>

                </tr>

                <tr>
                    <td class="label">Pukul</td>
                    <td class="colon">:</td>
                    <td class="value">{{ $this->izin['start_time'].' s/d '.$this->izin['end_time'] }}</td>
                </tr>

                <tr>
                    <td colspan="3" class="spacer"></td>
                </tr>

                <tr>
                    <td colspan="3" class="intro-text">Dikarenakan keperluan / alasan sebagai berikut:</td>
                </tr>

                <tr>
                    <td colspan="3" class="value multi">{{ $this->izin['description'] }}</td>
                </tr>
            </table>
        </div>

        <div class="signature-section">
            <p class="signature-date">Jakarta, {{ Carbon::parse($this->izin['created_at'])->locale('id')->translatedFormat('d F Y') }}</p>
            <table class="signature-table">
                <tr>
                    <td class="signature-box">
                        <div class="signature-title">Disetujui oleh<br>Manager</div>

                        <div class="signature-image">
                            @if($this->izin['superadmins'])
                            <img src="{{ $this->izin['superadmins'] }}" alt="Tanda tangan manager">
                            @endif
                        </div>

                        <div class="signature-footer">
                            {{ $this->izin['superadmin_username'] ?? 'Manager' }}<br>
                            {{ $this->izin['created_at'] ?? '' }}
                        </div>
                    </td>

                    <td class="signature-box">
                        <div class="signature-title">Mengetahui oleh<br>Atasan Langsung</div>

                        <div class="signature-image">
                            @if($this->izin['admins'])
                            <img src="{{ $this->izin['admins'] }}" alt="Tanda tangan atasan langsung">
                            @endif
                        </div>

                        <div class="signature-footer">
                            {{ $this->izin['admin_username'] ?? 'Atasan Langsung' }}<br>
                            {{ $this->izin['created_at'] ?? '' }}
                        </div>
                    </td>

                    <td class="signature-box">
                        <div class="signature-title">Pemohon</div>

                        <div class="signature-image">
                            @if($this->izin['users']['signature'])
                            <img src="{{ $this->izin['url_sign'] }}" alt="Tanda tangan pemohon">
                            @endif
                        </div>

                        <div class="signature-footer">
                            {{ $this->izin['users']['name'] ?? 'Pemohon' }}<br>
                            {{ $this->izin['created_at'] ?? '' }}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
