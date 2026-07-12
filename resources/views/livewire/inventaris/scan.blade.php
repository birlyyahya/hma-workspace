<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Scan'])]
class extends Component {
    //
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-sm mx-auto px-4 sm:px-6 py-6 space-y-5" x-data="{ tab: 'kamera', result: 'found' }">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Scan</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div>
            <flux:heading size="lg">Scan Unit</flux:heading>
            <flux:text size="sm" class="mt-0.5">Scan QR label internal atau barcode SN vendor.</flux:text>
        </div>

        {{-- TAB KAMERA / MANUAL --}}
        <div class="inline-flex w-full rounded-xl bg-zinc-100 p-1 shadow-sm dark:bg-zinc-800">
            <button x-on:click="tab = 'kamera'"
                x-bind:class="tab === 'kamera' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="w-full cursor-pointer rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200">
                <flux:icon name="camera" class="me-1 inline size-4" /> Kamera
            </button>
            <button x-on:click="tab = 'manual'"
                x-bind:class="tab === 'manual' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="w-full cursor-pointer rounded-lg px-4 py-2 text-sm font-medium transition-all duration-200">
                <flux:icon name="pencil" class="me-1 inline size-4" /> Ketik SN
            </button>
        </div>

        {{-- VIEWFINDER (placeholder kamera) --}}
        <div x-show="tab === 'kamera'" class="space-y-3">
            <div class="relative aspect-square w-full overflow-hidden rounded-2xl bg-zinc-900">
                <div class="absolute inset-0 flex items-center justify-center">
                    <flux:icon name="qr-code" class="size-20 text-zinc-700" />
                </div>
                {{-- frame sudut --}}
                <div class="absolute inset-12">
                    <div class="absolute left-0 top-0 size-10 rounded-tl-xl border-l-4 border-t-4 border-red-500"></div>
                    <div class="absolute right-0 top-0 size-10 rounded-tr-xl border-r-4 border-t-4 border-red-500"></div>
                    <div class="absolute bottom-0 left-0 size-10 rounded-bl-xl border-b-4 border-l-4 border-red-500"></div>
                    <div class="absolute bottom-0 right-0 size-10 rounded-br-xl border-b-4 border-r-4 border-red-500"></div>
                </div>
                <div class="absolute inset-x-0 bottom-4 text-center">
                    <span class="rounded-full bg-black/60 px-3 py-1.5 text-xs text-white">Arahkan kamera ke QR label atau barcode SN</span>
                </div>
            </div>
            <flux:text size="xs" class="text-center">Kamera aktif saat integrasi scanner dipasang. Gunakan tab "Ketik SN" sebagai alternatif.</flux:text>
        </div>

        {{-- INPUT MANUAL --}}
        <div x-show="tab === 'manual'" x-cloak class="space-y-3">
            <div class="flex gap-2">
                <flux:input icon="qr-code" placeholder="Ketik / tempel serial number..." value="ZTE10G-88342" class="flex-1" />
                <flux:button variant="primary" icon="magnifying-glass" class="cursor-pointer">Cari</flux:button>
            </div>
            <flux:text size="xs">Mendukung scanner USB/bluetooth — fokuskan kursor di kolom ini lalu scan.</flux:text>
        </div>

        {{-- TOGGLE CONTOH STATE (demo statis) --}}
        <div class="flex items-center justify-center gap-2 pt-2">
            <flux:badge as="button" x-on:click="result = 'found'" x-bind:variant="result === 'found' ? undefined : 'outline'" color="green" class="cursor-pointer">Contoh: ketemu</flux:badge>
            <flux:badge as="button" x-on:click="result = 'notfound'" x-bind:variant="result === 'notfound' ? undefined : 'outline'" color="amber" class="cursor-pointer">Contoh: tidak ketemu</flux:badge>
        </div>

        {{-- HASIL: DITEMUKAN (bottom card, scan beruntun tanpa pindah halaman) --}}
        <div x-show="result === 'found'" x-transition
            class="rounded-2xl border border-green-200 bg-white p-5 shadow-lg dark:border-green-900 dark:bg-zinc-800">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 space-y-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="check-circle" class="size-5 text-green-500" />
                        <flux:heading class="font-mono">ZTE10G-88342</flux:heading>
                    </div>
                    <flux:text size="sm">Modul Uplink 10G · Pembangunan Jaringan FO Cikarang</flux:text>
                    <div class="flex items-center gap-2 pt-1">
                        <flux:badge size="sm" color="green">Diterima di Site</flux:badge>
                        <flux:text size="xs">POP Cikarang Utara</flux:text>
                    </div>
                </div>
                <a href="{{ route('inventaris.unit-detail', 9002) }}" wire:navigate class="shrink-0">
                    <flux:button size="sm" variant="primary" icon:trailing="arrow-right">Buka Detail</flux:button>
                </a>
            </div>
        </div>

        {{-- HASIL: TIDAK DITEMUKAN (form assign cepat) --}}
        <div x-show="result === 'notfound'" x-cloak x-transition
            class="space-y-4 rounded-2xl border border-amber-200 bg-white p-5 shadow-lg dark:border-amber-900 dark:bg-zinc-800">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-5 text-amber-500" />
                <div>
                    <flux:heading size="sm" class="font-mono">SN-BARU-0091</flux:heading>
                    <flux:text size="xs">SN belum terdaftar di sistem — assign ke unit kosong:</flux:text>
                </div>
            </div>

            <flux:field>
                <flux:label>Unit tujuan</flux:label>
                <flux:select>
                    <flux:select.option>INV-0007-005 — Modul Uplink 10G (GR-2026-0007)</flux:select.option>
                    <flux:select.option>INV-0007-006 — Modul Uplink 10G (GR-2026-0007)</flux:select.option>
                    <flux:select.option>INV-0007-007 — Modul Uplink 10G (GR-2026-0007)</flux:select.option>
                </flux:select>
            </flux:field>

            <flux:field>
                <flux:label>Tujuan / lokasi (opsional)</flux:label>
                <flux:input placeholder="Contoh: POP Jababeka" />
            </flux:field>

            <button class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-300 py-4 text-sm text-zinc-400 transition hover:border-red-400 hover:text-red-500 dark:border-zinc-600 dark:text-zinc-500">
                <flux:icon name="camera" class="size-5" /> Ambil foto fisik + SN
            </button>

            <flux:button variant="primary" icon="check" class="w-full cursor-pointer">Assign SN ke Unit</flux:button>
        </div>

    </div>
</div>
