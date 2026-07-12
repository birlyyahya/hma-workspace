<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Detail Spektek'])]
class extends Component {
    public int $projectId;

    public int $spektekId;

    public function mount(int $projectId, int $spektekId): void
    {
        $this->projectId = $projectId;
        $this->spektekId = $spektekId;
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('inventaris.project', 101)" wire:navigate>Pembangunan Jaringan FO Cikarang</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Perangkat OLT + Aksesoris</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="lg">Perangkat OLT + Aksesoris</flux:heading>
                <flux:text size="sm" class="mt-0.5">3 sub-item · 56 unit total · 40 sudah digenerate</flux:text>
            </div>
        </div>

        {{-- SUB-ITEM LIST --}}
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">

                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <flux:heading size="sm">OLT ZTE C320</flux:heading>
                        <flux:text size="xs">Total 8 unit · digenerate 8 · ber-SN 8</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="emerald">Selesai</flux:badge>
                        <flux:button size="sm" variant="outline" disabled>Generate</flux:button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <flux:heading size="sm">Modul Uplink 10G</flux:heading>
                        <flux:text size="xs">Total 16 unit · digenerate 16 · ber-SN 12</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="blue">4 belum SN</flux:badge>
                        <flux:button size="sm" variant="outline" disabled>Generate</flux:button>
                    </div>
                </div>

                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 space-y-1">
                        <flux:heading size="sm">Patchcord SC/UPC 3m</flux:heading>
                        <flux:text size="xs">Total 32 unit · digenerate 16 · ber-SN 0</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="zinc">Sisa 16 unit</flux:badge>
                        <flux:modal.trigger name="generate-modal">
                            <flux:button size="sm" variant="primary" icon="plus-circle" class="cursor-pointer">Generate</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

            </div>
        </div>

        {{-- RIWAYAT BATCH --}}
        <div class="space-y-3">
            <flux:heading size="sm">Riwayat Batch</flux:heading>
            <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="px-4 py-3">Kode Batch</th>
                            <th class="px-4 py-3">Sub-item</th>
                            <th class="px-4 py-3">Jumlah</th>
                            <th class="px-4 py-3">SN Terisi</th>
                            <th class="px-4 py-3">Diterima</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">GR-2026-0007</td>
                            <td class="px-4 py-3 text-zinc-800 dark:text-zinc-100">Modul Uplink 10G</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">8 unit</td>
                            <td class="px-4 py-3"><flux:badge size="sm" color="blue">4/8</flux:badge></td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">8 Jul 2026 · Budi Santoso</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('inventaris.units', 501) }}" wire:navigate>
                                    <flux:button size="xs" variant="outline" icon="eye">Lihat Unit</flux:button>
                                </a>
                            </td>
                        </tr>
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">GR-2026-0005</td>
                            <td class="px-4 py-3 text-zinc-800 dark:text-zinc-100">Modul Uplink 10G</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">8 unit</td>
                            <td class="px-4 py-3"><flux:badge size="sm" color="emerald">8/8</flux:badge></td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">28 Jun 2026 · Budi Santoso</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('inventaris.units', 500) }}" wire:navigate>
                                    <flux:button size="xs" variant="outline" icon="eye">Lihat Unit</flux:button>
                                </a>
                            </td>
                        </tr>
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                            <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">GR-2026-0002</td>
                            <td class="px-4 py-3 text-zinc-800 dark:text-zinc-100">OLT ZTE C320</td>
                            <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">8 unit</td>
                            <td class="px-4 py-3"><flux:badge size="sm" color="emerald">8/8</flux:badge></td>
                            <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">15 Jun 2026 · Budi Santoso</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('inventaris.units', 498) }}" wire:navigate>
                                    <flux:button size="xs" variant="outline" icon="eye">Lihat Unit</flux:button>
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- MODAL GENERATE --}}
        <flux:modal name="generate-modal" class="w-full max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Generate Unit</flux:heading>
                    <flux:text size="sm" class="mt-1">Patchcord SC/UPC 3m</flux:text>
                </div>

                <div class="grid grid-cols-3 gap-3 rounded-xl bg-zinc-50 p-4 text-center dark:bg-zinc-900/50">
                    <div>
                        <flux:heading size="lg">32</flux:heading>
                        <flux:text size="xs">Total spektek</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg">16</flux:heading>
                        <flux:text size="xs">Sudah digenerate</flux:text>
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-red-600 dark:text-red-400">16</flux:heading>
                        <flux:text size="xs">Sisa</flux:text>
                    </div>
                </div>

                <flux:field>
                    <flux:label>Jumlah unit yang diterima</flux:label>
                    <flux:input type="number" value="16" min="1" max="16" />
                    <flux:description>Maksimal sesuai sisa qty spektek. Satu kali generate = satu batch penerimaan.</flux:description>
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost" class="cursor-pointer">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check" class="cursor-pointer">Generate 16 Unit</flux:button>
                </div>
            </div>
        </flux:modal>

    </div>
</div>
