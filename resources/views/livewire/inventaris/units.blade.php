<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — List Unit'])]
class extends Component {
    public int $goodsReceiptId;

    public function mount(int $goodsReceiptId): void
    {
        $this->goodsReceiptId = $goodsReceiptId;
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6" x-data="{ selected: [], scanMode: false }">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('inventaris.project', 101)" wire:navigate>Pembangunan Jaringan FO Cikarang</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('inventaris.spektek', [101, 12])" wire:navigate>Perangkat OLT + Aksesoris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>GR-2026-0007</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- BANNER SETELAH GENERATE --}}
        <flux:callout icon="check-circle" color="green">
            <flux:callout.heading>8 unit berhasil dibuat dari batch GR-2026-0007</flux:callout.heading>
            <flux:callout.text>Langkah berikutnya: import SN dari file, aktifkan mode scan, atau print label QR untuk ditempel di fisik barang.</flux:callout.text>
        </flux:callout>

        {{-- HEADER BATCH --}}
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <flux:heading size="lg" class="font-mono">GR-2026-0007</flux:heading>
                    <flux:badge size="sm" color="blue">4/8 ber-SN</flux:badge>
                </div>
                <flux:text size="sm" class="mt-0.5">Modul Uplink 10G · 8 unit · diterima 8 Jul 2026 oleh Budi Santoso</flux:text>
            </div>
            <div class="flex flex-wrap gap-2">
                <flux:modal.trigger name="import-sn-modal">
                    <flux:button size="sm" variant="outline" icon="arrow-up-tray" class="cursor-pointer">Import SN</flux:button>
                </flux:modal.trigger>
                <flux:button size="sm" variant="outline" icon="qr-code" class="cursor-pointer" x-on:click="scanMode = !scanMode" x-bind:class="scanMode && 'ring-2 ring-red-500'">
                    Mode Scan
                </flux:button>
                <flux:button size="sm" variant="outline" icon="printer" class="cursor-pointer">Print Semua Label</flux:button>
            </div>
        </div>

        {{-- PANEL MODE SCAN --}}
        <div x-show="scanMode" x-collapse x-cloak
            class="rounded-2xl border-2 border-dashed border-red-300 bg-red-50/50 p-5 dark:border-red-800 dark:bg-red-950/20">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <flux:heading size="sm">Mode scan beruntun aktif</flux:heading>
                    <flux:text size="sm">Scan barcode SN vendor — otomatis mengisi unit kosong berikutnya (unit #5).</flux:text>
                </div>
                <flux:input icon="qr-code" placeholder="Kursor fokus di sini, tinggal scan..." class="sm:max-w-xs" autofocus />
            </div>
        </div>

        {{-- FILTER STATUS --}}
        <div class="flex flex-wrap items-center gap-2">
            <flux:badge as="button" color="red" class="cursor-pointer">Semua (8)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">Belum ada SN (3)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">SN Terpasang (1)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">Dalam Pengiriman (1)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">Diterima (1)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">Terpasang (1)</flux:badge>
            <flux:badge as="button" variant="outline" class="cursor-pointer">Rusak (1)</flux:badge>
        </div>

        {{-- TABEL UNIT --}}
        <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <th class="w-10 px-4 py-3">
                            <flux:checkbox x-on:change="selected = $event.target.checked ? [1,2,3,4,5,6,7,8] : []" />
                        </th>
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Kode Unit</th>
                        <th class="px-4 py-3">Serial Number</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Tujuan</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @foreach ([
                        ['no' => 1, 'id' => 9001, 'code' => 'INV-0007-001', 'sn' => 'ZTE10G-88341', 'status' => 'Terpasang', 'color' => 'emerald', 'dest' => 'POP Cikarang Utara'],
                        ['no' => 2, 'id' => 9002, 'code' => 'INV-0007-002', 'sn' => 'ZTE10G-88342', 'status' => 'Diterima di Site', 'color' => 'green', 'dest' => 'POP Cikarang Utara'],
                        ['no' => 3, 'id' => 9003, 'code' => 'INV-0007-003', 'sn' => 'ZTE10G-88357', 'status' => 'Dalam Pengiriman', 'color' => 'amber', 'dest' => 'POP Jababeka'],
                        ['no' => 4, 'id' => 9004, 'code' => 'INV-0007-004', 'sn' => 'ZTE10G-88360', 'status' => 'SN Terpasang', 'color' => 'blue', 'dest' => null],
                        ['no' => 5, 'id' => 9005, 'code' => 'INV-0007-005', 'sn' => null, 'status' => 'Belum ada SN', 'color' => 'zinc', 'dest' => null],
                        ['no' => 6, 'id' => 9006, 'code' => 'INV-0007-006', 'sn' => null, 'status' => 'Belum ada SN', 'color' => 'zinc', 'dest' => null],
                        ['no' => 7, 'id' => 9007, 'code' => 'INV-0007-007', 'sn' => null, 'status' => 'Belum ada SN', 'color' => 'zinc', 'dest' => null],
                        ['no' => 8, 'id' => 9008, 'code' => 'INV-0007-008', 'sn' => null, 'status' => 'Rusak', 'color' => 'red', 'dest' => 'Gudang Pusat'],
                    ] as $unit)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3">
                                <flux:checkbox x-bind:checked="selected.includes({{ $unit['no'] }})"
                                    x-on:change="$event.target.checked ? selected.push({{ $unit['no'] }}) : selected = selected.filter(i => i !== {{ $unit['no'] }})" />
                            </td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $unit['no'] }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $unit['code'] }}</td>
                            <td class="px-4 py-3">
                                @if ($unit['sn'])
                                    <span class="font-mono text-zinc-800 dark:text-zinc-100">{{ $unit['sn'] }}</span>
                                @else
                                    <span class="italic text-zinc-400 dark:text-zinc-500">— belum diisi</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><flux:badge size="sm" :color="$unit['color']">{{ $unit['status'] }}</flux:badge></td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $unit['dest'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex gap-1">
                                    <flux:button size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" title="Edit SN" />
                                    <a href="{{ route('inventaris.unit-detail', $unit['id']) }}" wire:navigate>
                                        <flux:button size="xs" variant="outline" icon="eye">Detail</flux:button>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- BULK ACTION BAR (muncul saat ada seleksi) --}}
        <div x-show="selected.length > 0" x-cloak x-transition
            class="fixed inset-x-0 bottom-6 z-40 mx-auto flex w-fit items-center gap-3 rounded-full border border-zinc-200 bg-white px-5 py-2.5 shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
            <flux:text size="sm"><span class="font-semibold" x-text="selected.length"></span> unit dipilih</flux:text>
            <flux:separator vertical class="h-5" />
            <flux:button size="sm" variant="ghost" icon="printer" class="cursor-pointer">Print Label</flux:button>
            <flux:button size="sm" variant="ghost" icon="pencil-square" class="cursor-pointer">Edit SN</flux:button>
            <flux:button size="sm" variant="ghost" icon="x-mark" class="cursor-pointer" x-on:click="selected = []" />
        </div>

        {{-- MODAL IMPORT SN (step preview) --}}
        <flux:modal name="import-sn-modal" class="w-full max-w-2xl">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Import SN — Preview</flux:heading>
                    <flux:text size="sm" class="mt-1">modul-uplink-sn.xlsx · 5 baris terbaca · di-assign berurutan ke unit kosong</flux:text>
                </div>

                <flux:callout icon="exclamation-triangle" color="amber">
                    <flux:callout.text>1 SN duplikat terdeteksi dan akan dilewati. Periksa pairing di bawah sebelum konfirmasi.</flux:callout.text>
                </flux:callout>

                <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-4 py-2.5">Baris File</th>
                                <th class="px-4 py-2.5">SN</th>
                                <th class="px-4 py-2.5">→ Unit</th>
                                <th class="px-4 py-2.5">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                            <tr>
                                <td class="px-4 py-2.5 text-zinc-500">1</td>
                                <td class="px-4 py-2.5 font-mono text-xs">ZTE10G-88361</td>
                                <td class="px-4 py-2.5 font-mono text-xs">INV-0007-005</td>
                                <td class="px-4 py-2.5"><flux:badge size="sm" color="green">OK</flux:badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5 text-zinc-500">2</td>
                                <td class="px-4 py-2.5 font-mono text-xs">ZTE10G-88362</td>
                                <td class="px-4 py-2.5 font-mono text-xs">INV-0007-006</td>
                                <td class="px-4 py-2.5"><flux:badge size="sm" color="green">OK</flux:badge></td>
                            </tr>
                            <tr class="bg-red-50/50 dark:bg-red-950/20">
                                <td class="px-4 py-2.5 text-zinc-500">3</td>
                                <td class="px-4 py-2.5 font-mono text-xs">ZTE10G-88342</td>
                                <td class="px-4 py-2.5 font-mono text-xs">—</td>
                                <td class="px-4 py-2.5"><flux:badge size="sm" color="red">Duplikat (sudah dipakai unit #2)</flux:badge></td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2.5 text-zinc-500">4</td>
                                <td class="px-4 py-2.5 font-mono text-xs">ZTE10G-88364</td>
                                <td class="px-4 py-2.5 font-mono text-xs">INV-0007-007</td>
                                <td class="px-4 py-2.5"><flux:badge size="sm" color="green">OK</flux:badge></td>
                            </tr>
                            <tr class="bg-amber-50/50 dark:bg-amber-950/20">
                                <td class="px-4 py-2.5 text-zinc-500">5</td>
                                <td class="px-4 py-2.5 font-mono text-xs">ZTE10G-88365</td>
                                <td class="px-4 py-2.5 font-mono text-xs">—</td>
                                <td class="px-4 py-2.5"><flux:badge size="sm" color="amber">Unit kosong habis</flux:badge></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="cursor-pointer">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check" class="cursor-pointer">Assign 3 SN</flux:button>
                </div>
            </div>
        </flux:modal>

    </div>
</div>
