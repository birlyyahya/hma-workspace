<?php

use Livewire\Volt\Component;

new class extends Component {

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
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-600 text-white text-xs font-bold">1</span>
            <h2 class="text-base font-semibold text-zinc-900">Riwayat tahapan - "Progress"</h2>
        </div>

        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6">
            <ol class="relative">
                @foreach ($stages as $stage)
                @php $chip = $stageChip($stage['status']); @endphp
                <li class="relative pl-12 {{ $loop->last ? '' : 'pb-8' }}" x-data="{ open: {{ $stage['status'] === 'current' ? 'true' : 'false' }} }">

                    {{-- garis rail --}}
                    @unless ($loop->last)
                    <span class="absolute left-[17px] top-10 bottom-0 w-0.5 {{ $stage['status'] === 'done' ? 'bg-green-300' : 'bg-zinc-200' }}"></span>
                    @endunless

                    {{-- bullet --}}
                    @if ($stage['status'] === 'done')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-green-500 text-white ring-4 ring-green-100">
                        <flux:icon.check class="w-5 h-5" />
                    </span>
                    @elseif ($stage['status'] === 'current')
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-blue-500 text-white ring-4 ring-blue-100">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @else
                    <span class="absolute left-0 top-0.5 flex items-center justify-center w-9 h-9 rounded-full bg-white text-zinc-400 ring-2 ring-zinc-200">
                        <flux:icon name="{{ $stage['icon'] }}" class="w-4.5 h-4.5" />
                    </span>
                    @endif

                    {{-- header tahap (klik untuk expand) --}}
                    <button type="button" @click="open = !open" class="w-full text-left cursor-pointer group">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold {{ $stage['status'] === 'pending' ? 'text-zinc-400' : 'text-zinc-900' }} group-hover:text-red-600 transition">
                                {{ $stage['title'] }}
                            </h3>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium ring-1 {{ $chip['class'] }}">
                                {{ $chip['label'] }}
                            </span>
                            @foreach ($stage['signals'] as $signal)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] bg-zinc-50 text-zinc-500 ring-1 ring-zinc-200">
                                <flux:icon.bolt class="w-3 h-3" />
                                {{ $signal }}
                            </span>
                            @endforeach
                            <flux:icon.chevron-down class="w-4 h-4 text-zinc-400 ml-auto transition-transform" x-bind:class="open && 'rotate-180'" />
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500">
                            <flux:icon.calendar class="w-3.5 h-3.5 inline -mt-0.5" />
                            Rencana: {{ $stage['range'] }}
                            @if ($stage['date'])
                            <span class="text-zinc-300 mx-1">·</span>
                            <span class="text-green-600 font-medium">Selesai {{ $stage['date'] }}</span>
                            @endif
                        </p>
                    </button>

                    {{-- detail expandable --}}
                    <div x-show="open" x-collapse x-cloak class="mt-3 space-y-3">

                        @if (count($stage['activities']))
                        <div class="rounded-xl border border-zinc-200 divide-y divide-zinc-100">
                            <p class="px-3 py-2 text-[11px] font-semibold text-zinc-500 uppercase tracking-wide bg-zinc-50 rounded-t-xl">
                                Aktivitas (dari DAR)
                            </p>
                            @foreach ($stage['activities'] as $activity)
                            <div class="flex items-center gap-3 px-3 py-2.5">
                                <flux:avatar circle size="xs" name="{{ $activity['user'] }}" color="auto" color:seed="{{ $activity['user'] }}" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-medium text-zinc-800 truncate">{{ $activity['title'] }}</p>
                                    <p class="text-[11px] text-zinc-400">{{ $activity['user'] }} · {{ $activity['date'] }}</p>
                                </div>
                                <span @class([ 'inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium ring-1' , 'bg-green-50 text-green-700 ring-green-200'=> $activity['status'] === 'CLOSED',
                                    'bg-blue-50 text-blue-700 ring-blue-200' => $activity['status'] === 'OPEN',
                                    'bg-amber-50 text-amber-700 ring-amber-200' => $activity['status'] === 'PENDING',
                                    ])>
                                    {{ $activity['status'] }}
                                </span>
                            </div>
                            @endforeach
                        </div>
                        @endif

                        @if (count($stage['documents']))
                        <div class="flex flex-wrap gap-2">
                            @foreach ($stage['documents'] as $doc)
                            <span class="inline-flex items-center gap-1.5 pl-2 pr-3 py-1.5 rounded-lg bg-red-50 text-red-700 ring-1 ring-red-100 text-xs font-medium cursor-pointer hover:bg-red-100 transition">
                                <flux:icon.document-text class="w-4 h-4" />
                                {{ $doc['name'] }}
                                <span class="text-red-400 font-normal">{{ $doc['size'] }}</span>
                            </span>
                            @endforeach
                        </div>
                        @endif

                        @if ($stage['notes'])
                        <div class="flex items-start gap-2 rounded-lg bg-zinc-50 px-3 py-2 text-xs text-zinc-600">
                            <flux:icon.chat-bubble-bottom-center-text class="w-4 h-4 text-zinc-400 shrink-0 mt-0.5" />
                            {{ $stage['notes'] }}
                        </div>
                        @endif

                        @if (! count($stage['activities']) && ! count($stage['documents']) && ! $stage['notes'])
                        <p class="text-xs text-zinc-400 italic">Belum ada aktivitas maupun dokumen di tahap ini.</p>
                        @endif
                    </div>
                </li>
                @endforeach
            </ol>
        </div>
    </section>
</div>
