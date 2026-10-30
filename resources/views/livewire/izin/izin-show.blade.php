<?php

use App\Services\IzinCache;
use App\Services\IzinPdfComposer;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Izin Detail'])]
class extends Component {

    public $data;
    public $id;
    public $pdfPreview;

    public function mount() {
        $response = app(IzinCache::class)->detail((int) $this->id);

        if (! ($response['success'] ?? false)) {
            Toaster::error('Failed to fetch izin detail from API.');
            \Log::error('Izin Detail API failed', [
                'body' => $response['message'] ?? 'No message',
            ]);
        }

        $this->data = $response['data'] ?? null;
    }

    public function generatePDF($id)
    {
        $response = app(IzinCache::class)->detail((int) $id);
        $izin = ($response['success'] ?? false) ? $response['data'] : $this->data;
        $pdfBase64 = app(IzinPdfComposer::class)->render($izin ?? []);

        if ($pdfBase64 === null) {
            Toaster::error('Data pemohon tidak ditemukan.');

            return;
        }

        $this->pdfPreview = $pdfBase64;
    }

}; ?>

<div>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">

            {{-- header --}}
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <a onclick="history.back()" class="cursor-pointer group inline-flex items-center gap-2 text-sm text-gray-500 hover:text-red-600 transition" wire:navigate>

                    <flux:icon name="arrow-left" class="size-5 transition-transform duration-300 group-hover:-translate-x-2 group-hover:animate-pulse">
                    </flux:icon>

                    <span>Back to Izin</span>

                </a>
                <div class="flex items-end gap-4">
                    <flex:description class="font-light text-gray-500 text-sm">Everything about your izin, in one place</flex:description>
                </div>
            </div>

            {{-- content --}}
            <div class="grid lg:grid-cols-3 grid-cols-1 gap-4">
                {{-- pdf reader --}}

                <div class="bg-white border rounded-lg p-3 sm:p-4 lg:col-span-2">
                    @if($pdfPreview)
                    <iframe src="{{ $this->pdfPreview }}" class="w-full h-[420px] sm:h-[500px] md:h-[600px] rounded-lg">
                    </iframe>
                    @else
                    <div class="flex flex-col sm:flex-row items-center gap-3 sm:gap-4 justify-center h-[420px] sm:h-[500px] md:h-[600px] text-gray-400 text-center sm:text-left">
                        <span wire:loading.remove wire:target="generatePDF">PDF preview akan muncul di sini</span>
                        <span wire:loading wire:target="generatePDF">Sedang membuat PDF...</span>
                        <flux:button wire:click="generatePDF({{ $data['id'] }})" wire:loading.attr="disabled" wire:target="generatePDF" variant="primary" size="sm" class="cursor-pointer w-full sm:w-auto">
                            <span wire:loading.remove wire:target="generatePDF">Preview PDF</span>
                            <span wire:loading wire:target="generatePDF">Membuat PDF...</span>
                        </flux:button>
                    </div>
                    @endif
                </div>

                {{-- Detail --}}
                <div class="order-first lg:order-last">
                    <div class="bg-white p-4 space-y-4 border rounded-lg">
                        <div class="flex items-center gap-2 justify-between mb-6">
                            <flux:heading size="lg">Detail Pengajuan Izin</flux:heading>
                            @if($data['status'] === '2')
                            <flux:badge color="green" size="sm">Approved</flux:badge>
                            @elseif($data['status'] === '1')
                            <flux:badge color="red" size="sm">Rejected</flux:badge>
                            @else
                            <flux:badge color="yellow" size="sm">Pending</flux:badge>
                            @endif
                        </div>
                        <div>
                            <flux:input icon="user" value="{{ $data['users']['name'] ?? '-' }}" readonly></flux:input>
                        </div>
                        <div>
                            <flux:input icon="document-text" value="{{ $data['reason'] ?? '-' }}" readonly></flux:input>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="w-full">
                                <flux:input icon="calendar" value="{{ Carbon::parse($data['start_date'])->format('d M Y') }}" readonly class="w-full"></flux:input>
                            </div>
                            <span class="hidden sm:inline">-</span>
                            <div class="w-full">
                                <flux:input icon="calendar" value="{{ Carbon::parse($data['end_date'])->format('d M Y') }}" readonly class="w-full"></flux:input>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="w-full">
                                <flux:input icon="clock" value="{{ $data['start_time'] }}" readonly class="w-full"></flux:input>
                            </div>
                            <span class="hidden sm:inline">-</span>
                            <div class="w-full">
                                <flux:input icon="clock" value="{{ $data['end_time'] }}" readonly class="w-full"></flux:input>
                            </div>
                        </div>
                        <div>
                            <flux:textarea icon="check" readonly>{{ $data['description'] ?? '-' }}</flux:textarea>
                        </div>
                    </div>
                    {{-- DUMMY UI: kartu Alur Persetujuan, 3 varian statis (hapus setelah logic siap) --}}
                    <div class="mt-4 space-y-4">

                        {{-- Varian 1: Disetujui IT, menunggu HRD --}}
                        <div class="bg-white p-4 border rounded-lg">
                            <div class="flex items-center justify-between gap-2 mb-5">
                                <flux:heading size="lg">Alur Persetujuan</flux:heading>
                                <flux:badge color="blue" size="sm" icon="check-circle">Disetujui IT</flux:badge>
                            </div>
                            <ol class="relative ml-3 space-y-6 border-s-2 border-zinc-200">
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white ring-4 ring-white">
                                        <flux:icon name="paper-airplane" variant="micro" class="size-3" />
                                    </span>
                                    <p class="text-sm font-medium text-zinc-900">Diajukan</p>
                                    <p class="text-xs text-zinc-500">Budi Santoso · 30 Okt 2026, 09:12</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-blue-500 text-white ring-4 ring-white">
                                        <flux:icon name="check" variant="micro" class="size-3" />
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-zinc-900">Disetujui Divisi IT</p>
                                        <flux:badge color="blue" size="sm">IT</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-500">Andi Pratama · 30 Okt 2026, 10:05</p>
                                    <p class="mt-2 rounded-md bg-zinc-50 px-3 py-2 text-xs text-zinc-600">"Sudah sesuai jadwal tim."</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-white ring-4 ring-white">
                                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-300 opacity-60"></span>
                                        <span class="relative size-3.5 rounded-full border-2 border-amber-400 bg-white"></span>
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-zinc-500">Menunggu Divisi HRD</p>
                                        <flux:badge color="yellow" size="sm">HRD</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-400">Belum diproses</p>
                                </li>
                            </ol>
                        </div>

                        {{-- Varian 2: Disetujui HRD (final) --}}
                        <div class="bg-white p-4 border rounded-lg">
                            <div class="flex items-center justify-between gap-2 mb-5">
                                <flux:heading size="lg">Alur Persetujuan</flux:heading>
                                <flux:badge color="green" size="sm" icon="check-badge">Disetujui HRD</flux:badge>
                            </div>
                            <ol class="relative ml-3 space-y-6 border-s-2 border-emerald-300">
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white ring-4 ring-white">
                                        <flux:icon name="paper-airplane" variant="micro" class="size-3" />
                                    </span>
                                    <p class="text-sm font-medium text-zinc-900">Diajukan</p>
                                    <p class="text-xs text-zinc-500">Siti Rahma · 28 Okt 2026, 08:40</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white ring-4 ring-white">
                                        <flux:icon name="check" variant="micro" class="size-3" />
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-zinc-900">Disetujui Divisi IT</p>
                                        <flux:badge color="green" size="sm">IT</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-500">Andi Pratama · 28 Okt 2026, 09:30</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-emerald-600 text-white ring-4 ring-emerald-100">
                                        <flux:icon name="check-badge" variant="micro" class="size-3.5" />
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-emerald-700">Disetujui Divisi HRD</p>
                                        <flux:badge color="green" size="sm">HRD</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-500">Rina Wulandari · 28 Okt 2026, 13:15</p>
                                    <p class="mt-2 rounded-md bg-emerald-50 px-3 py-2 text-xs text-emerald-800">Pengajuan selesai dan disetujui.</p>
                                </li>
                            </ol>
                        </div>

                        {{-- Varian 3: Ditolak HRD --}}
                        <div class="bg-white p-4 border rounded-lg">
                            <div class="flex items-center justify-between gap-2 mb-5">
                                <flux:heading size="lg">Alur Persetujuan</flux:heading>
                                <flux:badge color="red" size="sm" icon="x-circle">Ditolak</flux:badge>
                            </div>
                            <ol class="relative ml-3 space-y-6 border-s-2 border-zinc-200">
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-emerald-500 text-white ring-4 ring-white">
                                        <flux:icon name="paper-airplane" variant="micro" class="size-3" />
                                    </span>
                                    <p class="text-sm font-medium text-zinc-900">Diajukan</p>
                                    <p class="text-xs text-zinc-500">Dewi Lestari · 25 Okt 2026, 11:02</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-blue-500 text-white ring-4 ring-white">
                                        <flux:icon name="check" variant="micro" class="size-3" />
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-medium text-zinc-900">Disetujui Divisi IT</p>
                                        <flux:badge color="blue" size="sm">IT</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-500">Andi Pratama · 25 Okt 2026, 14:20</p>
                                </li>
                                <li class="ms-6">
                                    <span class="absolute -start-3.25 flex size-6 items-center justify-center rounded-full bg-red-500 text-white ring-4 ring-white">
                                        <flux:icon name="x-mark" variant="micro" class="size-3" />
                                    </span>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-semibold text-red-700">Ditolak Divisi HRD</p>
                                        <flux:badge color="red" size="sm">HRD</flux:badge>
                                    </div>
                                    <p class="text-xs text-zinc-500">Rina Wulandari · 26 Okt 2026, 09:10</p>
                                    <div class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2">
                                        <p class="text-[11px] font-semibold uppercase tracking-wide text-red-700">Alasan penolakan</p>
                                        <p class="mt-0.5 text-xs text-red-800">Kuota cuti tahunan sudah habis.</p>
                                    </div>
                                </li>
                            </ol>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
