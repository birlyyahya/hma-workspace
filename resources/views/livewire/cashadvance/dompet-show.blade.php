<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Detail Dompet'])]
class extends Component {
    //
}; ?>

<div class="max-h-screen overflow-auto px-2 py-4">
    <div class="py-4 space-y-4">

        {{-- HEADER --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button :href="route('cashadvance')" wire:navigate icon="arrow-left" variant="ghost" size="sm" />
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading class="font-bold text-xl">Detail Dompet Kegiatan</flux:heading>
                        <flux:badge size="sm" color="green">active</flux:badge>
                    </div>
                    <flux:text>&mdash;</flux:text>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <flux:button variant="primary" icon="plus" size="sm">Catat Transaksi</flux:button>
                <flux:modal.trigger name="tutup-kegiatan">
                    <flux:button variant="outline" icon="lock-closed" size="sm">Tutup Kegiatan</flux:button>
                </flux:modal.trigger>
                <flux:button target="_blank" variant="outline" icon="document-arrow-down" size="sm">Export PDF</flux:button>
            </div>
        </div>

        {{-- SUMMARY --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Saldo Berjalan</p>
                <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">Rp 0</h3>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Masuk</p>
                <h3 class="text-2xl font-semibold mt-2 tabular-nums text-green-600">Rp 0</h3>
            </div>
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Keluar</p>
                <h3 class="text-2xl font-semibold mt-2 tabular-nums text-red-600">Rp 0</h3>
            </div>
        </div>

        {{-- TRANSAKSI --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-4">
                <flux:heading class="font-bold">Riwayat Transaksi</flux:heading>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[800px] md:min-w-full text-sm text-left text-zinc-600 dark:text-zinc-300">
                    <thead class="bg-zinc-50 dark:bg-zinc-800 text-xs uppercase text-zinc-500 dark:text-zinc-400">
                        <tr>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Tanggal</th>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Jenis</th>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Kategori</th>
                            <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Deskripsi</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Jumlah</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Saldo Setelah</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-center whitespace-nowrap">Bukti</th>
                            <th class="px-3 py-3 md:px-6 font-normal text-center whitespace-nowrap">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-zinc-400">Belum ada transaksi pada dompet ini</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ===== MODAL: CATAT TRANSAKSI ===== --}}
        <flux:modal name="catat-transaksi" class="w-lg">
            <form class="space-y-6">
                <flux:heading size="lg">Catat Transaksi</flux:heading>

                <flux:radio.group label="Arah" variant="segmented">
                    <flux:radio value="keluar" label="Keluar" />
                    <flux:radio value="masuk" label="Masuk" />
                </flux:radio.group>

                <flux:input type="number" label="Nominal" prefix="Rp" placeholder="0" />
                <flux:select label="Kategori" placeholder="Pilih kategori" />
                <flux:textarea label="Deskripsi" placeholder="Keterangan transaksi" rows="2" />
                <flux:input type="date" label="Tanggal" />
                <flux:input type="file" label="Bukti (wajib &mdash; jpg, png, pdf)" />

                <flux:button type="submit" variant="primary" class="w-full">Simpan</flux:button>
            </form>
        </flux:modal>

        {{-- ===== MODAL: TUTUP KEGIATAN / SETTLEMENT ===== --}}
        <flux:modal name="tutup-kegiatan" class="w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg">Tutup Kegiatan</flux:heading>
                    <flux:text class="mt-1">Setelah ditutup, dompet tidak menerima transaksi lagi.</flux:text>
                </div>

                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
                    <span class="text-sm text-zinc-500">Saldo Akhir</span>
                    <span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">Rp 0</span>
                </div>

                <flux:input type="file" label="Bukti setor balik (opsional)" />

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" icon="lock-closed">Tutup &amp; Segel</flux:button>
                </div>
            </div>
        </flux:modal>

    </div>
</div>
