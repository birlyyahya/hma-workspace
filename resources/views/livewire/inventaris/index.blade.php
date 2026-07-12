<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Pilih Project'])]
class extends Component {
    //
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        {{-- HERO HEADER --}}
        <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-linear-to-br from-red-700 via-red-600 to-red-600 px-6 sm:px-8 py-6 text-white shadow-sm">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -left-24 -bottom-28 size-72 rounded-full bg-black/15 blur-3xl"></div>
            </div>

            <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                <div class="min-w-0 space-y-3">
                    <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20 backdrop-blur">
                        <flux:icon name="archive-box" class="size-3.5" />
                        <span>Workspace</span>
                        <span class="opacity-50">/</span>
                        <span>Inventaris</span>
                    </div>
                    <div class="space-y-1">
                        <flux:heading size="xl" class="text-white leading-tight">
                            Inventaris
                        </flux:heading>
                        <p class="text-sm text-white/80">
                            Pilih project untuk mengelola unit barang, serial number, dan pergerakannya.
                        </p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-3">
                    <a href="{{ route('inventaris.scan') }}" wire:navigate class="w-full sm:w-auto">
                        <flux:button icon="qr-code" variant="primary" class="cursor-pointer w-full sm:w-auto bg-white! text-red-700! hover:bg-white/90!">
                            Scan SN
                        </flux:button>
                    </a>
                </div>
            </div>
        </div>

        {{-- SEARCH SN GLOBAL + FILTER PROJECT --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <flux:input icon="qr-code" placeholder="Cari serial number di semua project..." class="sm:max-w-md" />
            <flux:input icon="magnifying-glass" placeholder="Filter project..." class="sm:max-w-xs" />
        </div>

        {{-- PROJECT CARDS --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

            <a href="{{ route('inventaris.project', 101) }}" wire:navigate
                class="group rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-red-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading class="truncate group-hover:text-red-700 dark:group-hover:text-red-400">
                            Pembangunan Jaringan FO Cikarang
                        </flux:heading>
                        <flux:text size="sm" class="mt-0.5">PT Telkom Infra</flux:text>
                    </div>
                    <flux:badge size="sm" color="amber">Batch belum lengkap</flux:badge>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>86/120 unit ber-SN</span>
                        <span>4 spektek</span>
                    </div>
                    <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="w-[72%] bg-emerald-500"></div>
                        <div class="w-[13%] bg-blue-400"></div>
                    </div>
                </div>
                <flux:text size="xs" class="mt-3">Aktivitas terakhir: 9 Jul 2026</flux:text>
            </a>

            <a href="{{ route('inventaris.project', 102) }}" wire:navigate
                class="group rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-red-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading class="truncate group-hover:text-red-700 dark:group-hover:text-red-400">
                            Instalasi CCTV Kawasan MM2100
                        </flux:heading>
                        <flux:text size="sm" class="mt-0.5">PT Bekasi Fajar</flux:text>
                    </div>
                    <flux:badge size="sm" color="emerald">Lengkap</flux:badge>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>45/45 unit ber-SN</span>
                        <span>2 spektek</span>
                    </div>
                    <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <div class="w-full bg-emerald-500"></div>
                    </div>
                </div>
                <flux:text size="xs" class="mt-3">Aktivitas terakhir: 7 Jul 2026</flux:text>
            </a>

            <a href="{{ route('inventaris.project', 103) }}" wire:navigate
                class="group rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm transition hover:border-red-300 hover:shadow-md dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-red-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading class="truncate group-hover:text-red-700 dark:group-hover:text-red-400">
                            Upgrade Server Room Gedung B
                        </flux:heading>
                        <flux:text size="sm" class="mt-0.5">PT Hana Tekindo</flux:text>
                    </div>
                    <flux:badge size="sm" color="zinc">Belum mulai</flux:badge>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                        <span>0/18 unit ber-SN</span>
                        <span>3 spektek</span>
                    </div>
                    <div class="flex h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700"></div>
                </div>
                <flux:text size="xs" class="mt-3">Aktivitas terakhir: 1 Jul 2026</flux:text>
            </a>

        </div>
    </div>
</div>
