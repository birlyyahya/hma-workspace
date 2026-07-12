<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Detail Unit'])]
class extends Component {
    public int $unitId;

    public function mount(int $unitId): void
    {
        $this->unitId = $unitId;
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-lg mx-auto px-4 sm:px-6 py-6 space-y-6">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('inventaris.project', 101)" wire:navigate>Pembangunan Jaringan FO Cikarang</flux:breadcrumbs.item>
            <flux:breadcrumbs.item :href="route('inventaris.units', 501)" wire:navigate>GR-2026-0007</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Unit #2</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- KARTU IDENTITAS + AKSI UTAMA --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0 space-y-3">
                    <div class="flex items-center gap-2">
                        <flux:badge size="sm" color="green">Diterima di Site</flux:badge>
                        <flux:badge size="sm" color="zinc" class="font-mono">INV-0007-002</flux:badge>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:heading size="xl" class="font-mono tracking-tight">ZTE10G-88342</flux:heading>
                        <flux:button size="xs" variant="ghost" icon="clipboard" class="cursor-pointer" title="Salin SN" />
                    </div>
                    <flux:text size="sm">Modul Uplink 10G · Batch GR-2026-0007 · Tujuan: POP Cikarang Utara</flux:text>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <flux:button variant="primary" icon="wrench-screwdriver" class="cursor-pointer">Tandai Terpasang</flux:button>
                        <flux:button variant="outline" icon="printer" class="cursor-pointer">Print Label</flux:button>
                        <flux:dropdown>
                            <flux:button variant="ghost" icon="ellipsis-horizontal" class="cursor-pointer" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square">Edit SN</flux:menu.item>
                                <flux:menu.item icon="arrow-uturn-left">Kembalikan ke Gudang</flux:menu.item>
                                <flux:menu.item icon="exclamation-triangle" variant="danger">Tandai Rusak</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>

                {{-- QR label unit --}}
                <div class="flex shrink-0 flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <div class="flex size-28 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                        <flux:icon name="qr-code" class="size-16 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <flux:text size="xs" class="font-mono">INV-0007-002</flux:text>
                </div>
            </div>
        </div>

        {{-- FOTO --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <flux:heading size="sm">Foto Unit</flux:heading>
                <flux:button size="sm" variant="outline" icon="camera" class="cursor-pointer">Tambah Foto</flux:button>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="group relative aspect-square overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                    <div class="flex h-full items-center justify-center">
                        <flux:icon name="photo" class="size-10 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <div class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1.5 text-[11px] text-white">
                        Foto serah terima · 9 Jul
                    </div>
                </div>
                <div class="group relative aspect-square overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                    <div class="flex h-full items-center justify-center">
                        <flux:icon name="photo" class="size-10 text-zinc-400 dark:text-zinc-500" />
                    </div>
                    <div class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1.5 text-[11px] text-white">
                        Foto fisik + SN · 8 Jul
                    </div>
                </div>
                <button class="flex aspect-square cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 text-zinc-400 transition hover:border-red-400 hover:text-red-500 dark:border-zinc-600 dark:text-zinc-500">
                    <div class="flex flex-col items-center gap-1">
                        <flux:icon name="plus" class="size-6" />
                        <span class="text-xs">Upload</span>
                    </div>
                </button>
            </div>
        </div>

        {{-- TIMELINE MOVEMENT --}}
        <div class="space-y-3">
            <flux:heading size="sm">Riwayat Pergerakan</flux:heading>
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <ol class="relative space-y-6 border-s border-zinc-200 dark:border-zinc-700">

                    <li class="ms-5">
                        <span class="absolute -start-[9px] flex size-[18px] items-center justify-center rounded-full bg-green-100 ring-4 ring-white dark:bg-green-900 dark:ring-zinc-800">
                            <span class="size-2 rounded-full bg-green-500"></span>
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" color="green">Diterima di Site</flux:badge>
                            <flux:text size="xs">9 Jul 2026 14:20 · Andi Wijaya</flux:text>
                        </div>
                        <flux:text size="sm" class="mt-1">Diterima oleh tim site, kondisi baik.</flux:text>
                        <div class="mt-2 flex size-14 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                            <flux:icon name="photo" class="size-6 text-zinc-400" />
                        </div>
                    </li>

                    <li class="ms-5">
                        <span class="absolute -start-[9px] flex size-[18px] items-center justify-center rounded-full bg-amber-100 ring-4 ring-white dark:bg-amber-900 dark:ring-zinc-800">
                            <span class="size-2 rounded-full bg-amber-500"></span>
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" color="amber">Dalam Pengiriman</flux:badge>
                            <flux:text size="xs">9 Jul 2026 08:05 · Budi Santoso</flux:text>
                        </div>
                        <flux:text size="sm" class="mt-1">Dikirim via ekspedisi internal ke POP Cikarang Utara.</flux:text>
                    </li>

                    <li class="ms-5">
                        <span class="absolute -start-[9px] flex size-[18px] items-center justify-center rounded-full bg-blue-100 ring-4 ring-white dark:bg-blue-900 dark:ring-zinc-800">
                            <span class="size-2 rounded-full bg-blue-500"></span>
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" color="blue">SN Terpasang</flux:badge>
                            <flux:text size="xs">8 Jul 2026 16:41 · Budi Santoso</flux:text>
                        </div>
                        <flux:text size="sm" class="mt-1">SN di-scan dari barcode vendor.</flux:text>
                        <div class="mt-2 flex size-14 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                            <flux:icon name="photo" class="size-6 text-zinc-400" />
                        </div>
                    </li>

                    <li class="ms-5">
                        <span class="absolute -start-[9px] flex size-[18px] items-center justify-center rounded-full bg-zinc-100 ring-4 ring-white dark:bg-zinc-700 dark:ring-zinc-800">
                            <span class="size-2 rounded-full bg-zinc-400"></span>
                        </span>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm" color="zinc">Unit Dibuat</flux:badge>
                            <flux:text size="xs">8 Jul 2026 16:30 · Sistem</flux:text>
                        </div>
                        <flux:text size="sm" class="mt-1">Unit dibuat dari batch GR-2026-0007.</flux:text>
                    </li>

                </ol>
            </div>
        </div>

    </div>
</div>
