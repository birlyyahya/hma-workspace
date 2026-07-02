<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div class="space-y-4">

    {{-- ================= TOP GRID ================= --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        {{-- ===== LEFT: BALANCE + CHART + STATS ===== --}}
        <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">

            {{-- BALANCE CARD --}}
            <div class="p-6 space-y-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Balance</p>
                            <flux:badge size="sm" color="green">active</flux:badge>
                            <flux:badge size="sm" color="zinc">Periode #1</flux:badge>
                        </div>

                        <h2 class="text-4xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                            Rp 0
                        </h2>

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            CA PL &bull; Float Rp 0 &bull; Dibuka &mdash;
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <flux:button size="sm" variant="primary" icon="minus-circle">
                            Catat Pengeluaran
                        </flux:button>

                        <flux:modal.trigger name="topup-laporan">
                            <flux:button size="sm" variant="outline" icon="arrow-up-tray">Top up</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                {{-- CHART (data statik; wiring API dilakukan manual) --}}
                <div wire:ignore
                    x-data="{ chart: null }"
                    x-init="
                        chart = new ApexCharts($refs.caChart, {
                            series: [
                                { name: 'Income', data: [0,0,0,0,0,0,0,0,0,0,0,0] },
                                { name: 'Expense', data: [0,0,0,0,0,0,0,0,0,0,0,0] },
                            ],
                            chart: { height: 280, type: 'area', toolbar: { show: false } },
                            dataLabels: { enabled: false },
                            stroke: { curve: 'smooth', width: 3 },
                            colors: ['#2E93fA', '#E91E63'],
                            xaxis: { type: 'category', categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
                            yaxis: { labels: { align: 'left', formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                            tooltip: { theme: 'light' },
                        });
                        chart.render();
                    ">
                    <div x-ref="caChart" class="h-72 w-full"></div>
                </div>
            </div>

            {{-- INCOME / EXPENSE --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 border-t border-zinc-200 dark:border-zinc-700">
                <div class="p-6 sm:border-e border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Income</p>
                        <flux:badge size="sm" color="blue">Opening periode</flux:badge>
                    </div>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">
                        Rp 0
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Saldo awal periode aktif</p>
                </div>

                <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Expense</p>
                        <flux:badge size="sm" color="red">Pengeluaran</flux:badge>
                    </div>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">
                        Rp 0
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Total pengeluaran periode aktif</p>
                </div>
            </div>
        </div>

        {{-- ===== RIGHT: DOMPET KEGIATAN + BREAKDOWN ===== --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">

            {{-- DOMPET KEGIATAN --}}
            <div class="p-6 space-y-4 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Dompet Kegiatan</h3>
                    <span class="text-sm text-zinc-400">0 dompet</span>
                </div>

                <p class="text-sm text-zinc-400 text-center py-2">Belum ada dompet kegiatan</p>

                <flux:modal.trigger name="buat-kegiatan">
                    <flux:button variant="outline" class="w-full" icon="plus">Create new card</flux:button>
                </flux:modal.trigger>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori &mdash; periode aktif</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp 0
                </h3>

                <p class="text-sm text-zinc-400">Belum ada pengeluaran pada periode ini.</p>
            </div>
        </div>
    </div>

    {{-- ================= RECENT TRANSACTION ================= --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <flux:heading class="font-bold">Recent Transaction</flux:heading>
                <flux:text class="text-xs">Periode #1 (aktif) saja</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:input icon="magnifying-glass" placeholder="Cari deskripsi / kategori" />
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="calendar" variant="outline" />
                    <flux:menu>
                        <flux:menu.item disabled>
                            <flux:input label="Start Date" type="date" />
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input label="End Date" type="date" />
                        </flux:menu.item>
                        <flux:menu.item>
                            <flux:button class="w-full" size="sm">Reset</flux:button>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:button icon="arrow-down-tray" variant="outline">Export CSV</flux:button>
                <flux:button :href="route('cashadvance.laporan.capl')" target="_blank" icon="document-arrow-down" variant="outline">Export PDF</flux:button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-zinc-600 dark:text-zinc-300">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-xs uppercase text-zinc-500 dark:text-zinc-400">
                    <tr>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Deskripsi</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Kategori</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Tanggal</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Jenis</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Jumlah</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Saldo Setelah</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-center whitespace-nowrap">Bukti</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-center whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-zinc-400">Belum ada transaksi pada periode aktif</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= MODAL: CATAT PENGELUARAN ================= --}}
    <flux:modal name="catat-pengeluaran" class="w-lg">
        <form class="space-y-6">
            <div>
                <flux:heading size="lg">Catat Pengeluaran</flux:heading>
                <flux:text class="mt-1">Arah transaksi terkunci sebagai pengeluaran (keluar).</flux:text>
            </div>

            <flux:input type="number" label="Nominal" prefix="Rp" placeholder="0" />
            <flux:select label="Kategori" placeholder="Pilih kategori" />
            <flux:textarea label="Deskripsi" placeholder="Keterangan pengeluaran" rows="2" />
            <flux:input type="date" label="Tanggal" />
            <flux:input type="file" label="Bukti (wajib &mdash; jpg, png, pdf)" />

            <flux:button type="submit" variant="primary" class="w-full">Simpan Pengeluaran</flux:button>
        </form>
    </flux:modal>

    {{-- ================= MODAL: TOP UP / LAPORAN PERIODE ================= --}}
    <flux:modal name="topup-laporan" class="w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Laporan Periode #1</flux:heading>
                <flux:text class="mt-1">Rekap pertanggungjawaban sebelum top up. Saldo akan direstore ke Rp 0.</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <p class="text-xs text-zinc-500">Total Pengeluaran</p>
                    <p class="text-lg font-semibold tabular-nums text-red-600">Rp 0</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <p class="text-xs text-zinc-500">Closing Balance</p>
                    <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">Rp 0</p>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
                <span class="text-sm text-zinc-500">Top up untuk restore ke float</span>
                <span class="text-lg font-semibold tabular-nums text-green-600">Rp 0</span>
            </div>

            <div class="flex justify-between gap-2">
                <flux:button :href="route('cashadvance.laporan.capl')" target="_blank" variant="ghost" icon="document-arrow-down">
                    Lihat Laporan PDF
                </flux:button>
                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check">Verifikasi &amp; Eksekusi Top up</flux:button>
                </div>
            </div>
        </div>
    </flux:modal>

    {{-- ================= MODAL: RIWAYAT PERIODE ================= --}}
    <flux:modal name="riwayat-periode" class="w-xl" flyout>
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Riwayat Periode CA PL</flux:heading>
                <flux:text class="mt-1">Periode yang sudah disegel (read-only). Buka laporan untuk cetak/simpan PDF.</flux:text>
            </div>

            <div class="space-y-2">
                <p class="text-sm text-zinc-400 text-center py-4">Belum ada periode tersegel.</p>
            </div>
        </div>
    </flux:modal>

    {{-- ================= MODAL: BUAT DOMPET KEGIATAN ================= --}}
    <flux:modal name="buat-kegiatan" class="w-lg">
        <form class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Dompet Kegiatan</flux:heading>
                <flux:text class="mt-1">Dana cair dicatat sebagai transaksi masuk pertama.</flux:text>
            </div>

            <flux:input label="Nama Kegiatan" placeholder="mis. Survei Lokasi Bandung" />
            <flux:input type="number" label="RAB (opsional)" prefix="Rp" placeholder="0" />
            <flux:input type="number" label="Dana Cair" prefix="Rp" placeholder="0" />
            <flux:input type="file" label="Bukti pencairan (wajib &mdash; jpg, png, pdf)" />

            <flux:button type="submit" variant="primary" class="w-full">Buat Dompet</flux:button>
        </form>
    </flux:modal>
</div>
