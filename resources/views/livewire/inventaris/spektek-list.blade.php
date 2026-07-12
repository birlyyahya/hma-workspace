<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Daftar Spektek'])]
class extends Component {
    public int $projectId;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>Pembangunan Jaringan FO Cikarang</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        {{-- HEADER PROJECT --}}
        <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
                <flux:heading size="lg" class="leading-tight">Pembangunan Jaringan FO Cikarang</flux:heading>
                <flux:text size="sm" class="mt-0.5">PT Telkom Infra · 4 spektek · 120 unit</flux:text>
            </div>
            <div class="flex flex-wrap items-center gap-4">
                <div class="text-center">
                    <flux:heading size="lg">86</flux:heading>
                    <flux:text size="xs">Ber-SN</flux:text>
                </div>
                <flux:separator vertical class="h-8" />
                <div class="text-center">
                    <flux:heading size="lg">34</flux:heading>
                    <flux:text size="xs">Belum SN</flux:text>
                </div>
                <flux:separator vertical class="h-8" />
                <div class="text-center">
                    <flux:heading size="lg">24</flux:heading>
                    <flux:text size="xs">Belum generate</flux:text>
                </div>
            </div>
        </div>

        {{-- LEGEND --}}
        <div class="flex flex-wrap items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> SN terpasang</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-blue-400"></span> Generated, belum SN</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-zinc-200 dark:bg-zinc-700"></span> Belum generate</span>
        </div>

        {{-- SPEKTEK LIST --}}
        <div class="space-y-3">

            {{-- Spektek flat --}}
            <div x-data="{ open: false }" class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex items-center gap-2">
                            <flux:heading class="truncate">Kabel Fiber Optic 48 Core</flux:heading>
                            <flux:badge size="sm" color="zinc">1 item</flux:badge>
                        </div>
                        <div class="flex h-2 w-full max-w-md overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="w-[90%] bg-emerald-500"></div>
                            <div class="w-[10%] bg-blue-400"></div>
                        </div>
                        <flux:text size="xs">36 ber-SN · 4 belum SN · 0 belum generate — total 40 haspel</flux:text>
                    </div>
                    <a href="{{ route('inventaris.spektek', [101, 11]) }}" wire:navigate>
                        <flux:button size="sm" variant="outline" icon:trailing="chevron-right">Kelola</flux:button>
                    </a>
                </div>
            </div>

            {{-- Spektek komposit (expandable) --}}
            <div x-data="{ open: true }" class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex items-center gap-2">
                            <flux:heading class="truncate">Perangkat OLT + Aksesoris</flux:heading>
                            <flux:badge size="sm" color="blue">3 sub-item</flux:badge>
                        </div>
                        <div class="flex h-2 w-full max-w-md overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <div class="w-[36%] bg-emerald-500"></div>
                            <div class="w-[35%] bg-blue-400"></div>
                        </div>
                        <flux:text size="xs">20 ber-SN · 20 belum SN · 16 belum generate — total 56 unit</flux:text>
                    </div>
                    <div class="flex items-center gap-2">
                        <flux:button size="sm" variant="ghost" x-on:click="open = !open">
                            <span x-show="!open">Lihat sub-item</span>
                            <span x-show="open" x-cloak>Tutup</span>
                        </flux:button>
                        <a href="{{ route('inventaris.spektek', [101, 12]) }}" wire:navigate>
                            <flux:button size="sm" variant="outline" icon:trailing="chevron-right">Kelola</flux:button>
                        </a>
                    </div>
                </div>
                <div x-show="open" x-collapse x-cloak class="border-t border-zinc-100 dark:border-zinc-700/50">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <flux:text size="sm">OLT ZTE C320</flux:text>
                            <flux:text size="xs" class="shrink-0">8/8 ber-SN</flux:text>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <flux:text size="sm">Modul Uplink 10G</flux:text>
                            <flux:text size="xs" class="shrink-0">12/16 ber-SN</flux:text>
                        </div>
                        <div class="flex items-center justify-between gap-3 px-5 py-3">
                            <flux:text size="sm">Patchcord SC/UPC 3m</flux:text>
                            <flux:text size="xs" class="shrink-0">16/32 digenerate · 0 ber-SN</flux:text>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Spektek belum digenerate --}}
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="flex items-center gap-2">
                            <flux:heading class="truncate">ODP Pole 16 Port</flux:heading>
                            <flux:badge size="sm" color="zinc">1 item</flux:badge>
                        </div>
                        <div class="flex h-2 w-full max-w-md overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700"></div>
                        <flux:text size="xs">Belum ada unit digenerate — total 24 unit</flux:text>
                    </div>
                    <a href="{{ route('inventaris.spektek', [101, 13]) }}" wire:navigate>
                        <flux:button size="sm" variant="primary" icon="plus-circle">Generate</flux:button>
                    </a>
                </div>
            </div>

        </div>
    </div>
</div>
