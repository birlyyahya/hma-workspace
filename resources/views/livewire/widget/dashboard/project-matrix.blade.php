<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>
@php
// ================= DATA DUMMY =================
    $stages = [
        [
            'key' => 'kontrak',
            'title' => 'PO / TTD Kontrak',
            'icon' => 'document-check',
            'status' => 'done', // done | current | pending
            'date' => '12 Mei 2026',
            'range' => '1 Mei – 15 Mei 2026',
            'signals' => ['1 Dokumen BA', '2 Task DAR selesai'],
            'activities' => [
                ['title' => 'Kick Off Meeting Paket 9', 'user' => 'Birly Yahya', 'date' => '12 Mei 2026', 'status' => 'CLOSED'],
                ['title' => 'Finalisasi dokumen kontrak dengan PPK', 'user' => 'Amal Zakaria', 'date' => '10 Mei 2026', 'status' => 'CLOSED'],
            ],
            'documents' => [
                ['name' => 'BA Kick Off Meeting Ttd.pdf', 'size' => '882 KB'],
                ['name' => 'Kontrak-008-2026.pdf', 'size' => '2.7 MB'],
            ],
            'notes' => 'PO sudah terbit, lead time vendor ± 6 minggu.',
        ],
        [
            'key' => 'barang-tiba',
            'title' => 'Barang Tiba',
            'icon' => 'truck',
            'status' => 'done',
            'date' => '3 Jul 2026',
            'range' => '1 Jun – 10 Jul 2026',
            'signals' => ['Spektek diterima 18/20 item', '1 Task DAR selesai'],
            'activities' => [
                ['title' => 'Penerimaan unit laptop di gudang', 'user' => 'Pungkas', 'date' => '3 Jul 2026', 'status' => 'CLOSED'],
            ],
            'documents' => [
                ['name' => 'Surat Jalan 003.pdf', 'size' => '640 KB'],
                ['name' => 'Foto Unboxing.jpg', 'size' => '1.1 MB'],
            ],
            'notes' => 'Laptop per 3 Juli sudah datang, 2 item menyusul minggu depan.',
        ],
        [
            'key' => 'pemeriksaan',
            'title' => 'Pemeriksaan',
            'icon' => 'clipboard-document-check',
            'status' => 'current',
            'date' => null,
            'range' => '5 Jul – 20 Jul 2026',
            'signals' => ['2 Task DAR berjalan', 'Belum ada BA Pemeriksaan'],
            'activities' => [
                ['title' => 'Kroscek serial number seluruh unit', 'user' => 'Pungkas', 'date' => '6 Jul 2026', 'status' => 'OPEN'],
                ['title' => 'Pengecekan kelengkapan aksesori', 'user' => 'Amal Zakaria', 'date' => '5 Jul 2026', 'status' => 'PENDING'],
            ],
            'documents' => [],
            'notes' => 'Menunggu feedback dari vendor terkait SN.',
        ],
        [
            'key' => 'pelatihan',
            'title' => 'Pelatihan',
            'icon' => 'academic-cap',
            'status' => 'pending',
            'date' => null,
            'range' => '7 Jul – 9 Jul 2027',
            'signals' => [],
            'activities' => [],
            'documents' => [],
            'notes' => 'Terjadwal 7–9 Juli 2027 (Hotel Trembesi).',
        ],
        [
            'key' => 'uji-fungsi',
            'title' => 'Uji Fungsi / Petik',
            'icon' => 'beaker',
            'status' => 'pending',
            'date' => null,
            'range' => '15 Jul – 30 Jul 2027',
            'signals' => [],
            'activities' => [],
            'documents' => [],
            'notes' => null,
        ],
        [
            'key' => 'distribusi',
            'title' => 'Distribusi',
            'icon' => 'cube',
            'status' => 'pending',
            'date' => null,
            'range' => '1 Agu – 20 Agu 2027',
            'signals' => [],
            'activities' => [],
            'documents' => [],
            'notes' => null,
        ],
        [
            'key' => 'bast',
            'title' => 'BAST',
            'icon' => 'clipboard-document-list',
            'status' => 'pending',
            'date' => null,
            'range' => '25 Agu – 31 Agu 2027',
            'signals' => [],
            'activities' => [],
            'documents' => [],
            'notes' => null,
        ],
    ];

    $doneCount = collect($stages)->where('status', 'done')->count();

    // Data dummy laporan matriks (Ide 3)
    $matrixStages = ['PO', 'Barang Tiba', 'Pemeriksaan', 'Pelatihan', 'Uji Fungsi/Petik', 'Distribusi', 'BAST'];
    $matrixRows = [
        ['company' => 'PT Bintang Asheeqa Teknologi', 'code' => 'P12', 'progress' => 60, 'checks' => [true, false, false, false, true, false, false], 'note' => 'Pelatihan tgl 7–9 Juli 2027 (Hotel Trembesi)'],
        ['company' => 'PT Cahaya Radja', 'code' => 'P14', 'progress' => 30, 'checks' => [true, false, false, false, false, false, false], 'note' => 'Masih ada penyesuaian yang belum sesuai spektek. Packing diminta pakai hardcase.'],
        ['company' => 'PT Bitra Solusi Tekindo', 'code' => 'P09', 'progress' => 45, 'checks' => [true, true, false, false, false, false, false], 'note' => 'Laptop per 3 Juli 2026 sudah datang'],
        ['company' => 'PT Binacitra Teknologi Indonesia', 'code' => 'P15', 'progress' => 45, 'checks' => [true, true, false, false, false, false, false], 'note' => 'Menunggu feedback dari vendor terkait SN'],
        ['company' => 'PT Adhibuana Artha Kencana', 'code' => 'P08', 'progress' => 100, 'checks' => [true, true, true, true, true, true, true], 'note' => 'Selesai — BAST ditandatangani 20 Jun 2026'],
    ];

    $stageChip = fn (string $status) => match ($status) {
        'done' => ['label' => 'Selesai', 'class' => 'bg-green-50 text-green-700 ring-green-200'],
        'current' => ['label' => 'Berjalan', 'class' => 'bg-blue-50 text-blue-700 ring-blue-200'],
        default => ['label' => 'Belum', 'class' => 'bg-zinc-50 text-zinc-500 ring-zinc-200'],
    };
@endphp

<div>
    <section class="space-y-3">

        <div class="bg-white rounded-2xl border border-zinc-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-zinc-200">
                <div>
                    <p class="text-sm font-semibold text-zinc-900">Laporan Progress Project</p>
                    <p class="text-xs text-zinc-500">Terisi otomatis dari timeline, DAR, dan dokumen tiap project</p>
                </div>
                <div class="flex gap-2">
                    <flux:button size="sm" variant="outline" icon="funnel">Filter</flux:button>
                    <flux:button size="sm" variant="outline" icon="arrow-down-tray">Export</flux:button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-max">
                    <thead>
                        <tr class="bg-zinc-50 text-zinc-600">
                            <th class="px-4 py-3 text-left font-semibold sticky left-0 bg-zinc-50">Project / Perusahaan</th>
                            <th class="px-3 py-3 text-center font-semibold">Progress</th>
                            @foreach ($matrixStages as $stageName)
                            <th class="px-3 py-3 text-center font-semibold whitespace-nowrap text-xs">{{ $stageName }}</th>
                            @endforeach
                            <th class="px-4 py-3 text-left font-semibold min-w-64">Catatan Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach ($matrixRows as $row)
                        <tr class="hover:bg-zinc-50/60 transition">
                            <td class="px-4 py-3 sticky left-0 bg-white">
                                <p class="font-medium text-zinc-800 whitespace-nowrap">{{ $row['company'] }}</p>
                                <span class="inline-flex mt-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-700 ring-1 ring-red-100">
                                    {{ $row['code'] }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col items-center gap-1 w-20 mx-auto">
                                    <span class="text-xs font-semibold {{ $row['progress'] === 100 ? 'text-green-600' : 'text-zinc-700' }}">{{ $row['progress'] }}%</span>
                                    <div class="w-full h-1.5 rounded-full bg-zinc-100 overflow-hidden">
                                        <div class="h-full rounded-full {{ $row['progress'] === 100 ? 'bg-green-500' : 'bg-blue-500' }}" style="width: {{ $row['progress'] }}%"></div>
                                    </div>
                                </div>
                            </td>
                            @foreach ($row['checks'] as $checked)
                            <td class="px-3 py-3 text-center">
                                @if ($checked)
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-green-500 text-white">
                                    <flux:icon.check class="w-4 h-4" />
                                </span>
                                @else
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-md ring-1 ring-zinc-200 bg-white"></span>
                                @endif
                            </td>
                            @endforeach
                            <td class="px-4 py-3 text-xs text-zinc-600 max-w-72">{{ $row['note'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
