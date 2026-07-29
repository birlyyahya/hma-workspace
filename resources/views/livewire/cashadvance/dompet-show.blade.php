<?php

use App\Services\CaCache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Detail Dompet'])]
class extends Component {
    public string $kodeCa;

    public array $wallet = [];

    public string $search = '';

    /** @var array<int, string> */
    public array $categories = ['Transport', 'Akomodasi', 'Konsumsi', 'ATK', 'Komunikasi', 'Lain-lain'];

    public function mount(string $kodeCa): void
    {
        $this->kodeCa = $kodeCa;
        $this->loadWallet();
    }

    public function loadWallet(): void
    {
        $response = app(CaCache::class)->walletKegiatanByKode($this->kodeCa, ['tr_ca_transaction' => 'true']);

        $this->wallet = $response['data'] ?? [];
    }

    public function performSearch(): void
    {
        // Method ini dipanggil saat Enter di search input
        // Blade akan refresh component dan meload data dengan search param
    }

    #[On('ca-updated')]
    public function refreshWallet(): void
    {
        $this->loadWallet();

        $this->dispatch('ca-chart-updated', series: $this->getChart());
    }

    public function isClosed(): bool
    {
        return ! ($this->wallet['is_active'] ?? true);
    }

    /**
     * Transaksi pada dompet ini; setiap baris diberi bukti_url dari MinIO.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transaksi(): array
    {
        $result = app(CaCache::class)->transaksiWalletKegiatan($this->kodeCa, ['search' => $this->search])['data'] ?? [];

        return $result;
    }

    /**
     * Chart masuk vs keluar per bulan, dari transaksi yang sudah ter-embed di detail wallet.
     */
    public function getChart(): array
    {
        $transactions = collect($this->wallet['tr_ca_transaction'] ?? []);

        $seriesFor = fn (string $jenis): array => collect(range(1, 12))
            ->map(fn ($month) => $transactions
                ->filter(fn ($t) => ($t['jenis'] ?? null) === $jenis
                    && \Carbon\Carbon::parse($t['tanggal'])->month === $month)
                ->sum(fn ($t) => (float) $t['jumlah']))
            ->values()
            ->all();

        return [
            ['name' => 'Masuk', 'data' => $seriesFor('pemasukan')],
            ['name' => 'Keluar', 'data' => $seriesFor('pengeluaran')],
        ];
    }

    /**
     * Breakdown pengeluaran per kategori pada dompet ini.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getKategoriPengeluaran(): array
    {
        $pengeluaran = collect($this->wallet['tr_ca_transaction'] ?? [])
            ->filter(fn ($t) => ($t['jenis'] ?? null) === 'pengeluaran');

        $total = $pengeluaran->sum(fn ($t) => (float) $t['jumlah']);

        return $pengeluaran
            ->groupBy('kategori')
            ->map(function ($items, $kategori) use ($total): array {
                $jumlah = $items->sum(fn ($t) => (float) $t['jumlah']);

                return [
                    'nama' => $kategori ?: 'Lain-lain',
                    'jumlah' => $jumlah,
                    'persentase' => $total > 0 ? round($jumlah / $total * 100, 1) : 0,
                ];
            })
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

    /**
     * URL publik MinIO untuk path bukti dari API CA (bucket: cashadvance).
     */
    private function buktiUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return config('services.aws_url').'cashadvance/'.$path;
    }
}; ?>

<div class="max-h-screen overflow-auto px-2 py-4">
    <div class="py-4 space-y-4">

        {{-- HEADER --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <flux:button :href="route('cashadvance')" wire:navigate icon="arrow-left" variant="ghost" size="sm" />
                <div>
                    <div class="flex items-center gap-2">
                        <flux:heading class="font-bold text-xl">{{ $wallet['judul_kegiatan'] ?? 'Detail Dompet Kegiatan' }}</flux:heading>
                        @if ($this->isClosed())
                        <flux:badge size="sm" color="zinc">closed</flux:badge>
                        @else
                        <flux:badge size="sm" color="green">active</flux:badge>
                        @endif
                        <flux:badge size="sm" color="blue">{{ $wallet['kode_ca'] ?? '' }}</flux:badge>
                    </div>
                    <flux:text>
                        Dibuka {{ isset($wallet['tanggal_mulai']) ? \Carbon\Carbon::parse($wallet['tanggal_mulai'])->locale('id')->translatedFormat('d M Y') : '—' }}
                        @if ($wallet['tanggal_selesai'] ?? null)
                        &middot; Ditutup {{ \Carbon\Carbon::parse($wallet['tanggal_selesai'])->locale('id')->translatedFormat('d M Y') }}
                        @endif
                    </flux:text>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @unless ($this->isClosed())
                <flux:button variant="primary" icon="plus" size="sm"
                    x-on:click="$flux.modal('catat-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-new-transaksi')">
                    Catat Transaksi
                </flux:button>
                <flux:modal.trigger name="tutup-kegiatan">
                    <flux:button variant="outline" icon="lock-closed" size="sm">Tutup Kegiatan</flux:button>
                </flux:modal.trigger>
                @endunless
                <flux:button :href="route('cashadvance.laporan.kegiatan', $kodeCa)" target="_blank" variant="outline" icon="document-arrow-down" size="sm">Export PDF</flux:button>
            </div>
        </div>

        {{-- TOP GRID: BALANCE + CHART / BREAKDOWN --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- LEFT: BALANCE + CHART + STATS --}}
            <div class="lg:col-span-2 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
                <div class="p-6 space-y-6">
                    <div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Saldo Berjalan</p>
                        <h2 class="text-4xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                            Rp {{ number_format($wallet['saldo_akhir'] ?? 0, 0, ',', '.') }}
                        </h2>
                    </div>

                    {{-- CHART: masuk vs keluar per bulan --}}
                    <div wire:ignore x-data="{ chart: null }" x-init="
                            chart = new ApexCharts($refs.kegiatanChart, {
                                series: @js($this->getChart() ?? []),
                                chart: { height: 260, type: 'area', toolbar: { show: false } },
                                dataLabels: { enabled: false },
                                stroke: { curve: 'smooth', width: 3 },
                                colors: ['#22C55E', '#E91E63'],
                                xaxis: { type: 'category', categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
                                yaxis: { labels: { align: 'left', formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                                tooltip: { theme: 'light' },
                            });
                            chart.render();
                            Livewire.on('ca-chart-updated', (data) => {
                                chart.updateSeries(data.series);
                            });
                        ">
                        <div x-ref="kegiatanChart" class="h-64 w-full"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 border-t border-zinc-200 dark:border-zinc-700">
                    <div class="p-6 sm:border-e border-zinc-200 dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Saldo Awal</p>
                        <h3 class="text-xl font-semibold mt-1 tabular-nums text-zinc-900 dark:text-white">
                            Rp {{ number_format($wallet['saldo_awal_priode'] ?? 0, 0, ',', '.') }}
                        </h3>
                    </div>
                    <div class="p-6 sm:border-e border-zinc-200 dark:border-zinc-700 border-t sm:border-t-0">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Masuk</p>
                        <h3 class="text-xl font-semibold mt-1 tabular-nums text-green-600">
                            Rp {{ number_format($wallet['total_penerimaan'] ?? 0, 0, ',', '.') }}
                        </h3>
                    </div>
                    <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Keluar</p>
                        <h3 class="text-xl font-semibold mt-1 tabular-nums text-red-600">
                            Rp {{ number_format($wallet['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                        </h3>
                    </div>
                </div>
            </div>

            {{-- RIGHT: BREAKDOWN PER KATEGORI --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($wallet['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                </h3>
                @php
                // Kelas literal lengkap agar Tailwind men-generate-nya (jangan dirangkai dinamis).
                $palette = [
                    ['bg' => 'bg-orange-500/20', 'border' => 'border-orange-500'],
                    ['bg' => 'bg-blue-500/20', 'border' => 'border-blue-500'],
                    ['bg' => 'bg-purple-500/20', 'border' => 'border-purple-500'],
                    ['bg' => 'bg-pink-500/20', 'border' => 'border-pink-500'],
                    ['bg' => 'bg-green-500/20', 'border' => 'border-green-500'],
                    ['bg' => 'bg-teal-500/20', 'border' => 'border-teal-500'],
                ];
                @endphp
                @if (!empty($wallet['total_pengeluaran']))
                <div class="h-10 rounded bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                    @foreach ($this->getKategoriPengeluaran() as $i => $row)
                    @php $c = $palette[$i % count($palette)]; @endphp
                    <div class="{{ $c['bg'] }} border-l-2 {{ $c['border'] }}" style="width: {{ max($row['persentase'], 2) }}%" wire:key="bar-{{ $row['nama'] }}"></div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($this->getKategoriPengeluaran() as $i => $row)
                    <p class="border-l-2 ps-2 {{ $palette[$i % count($palette)]['border'] }}" wire:key="legend-{{ $row['nama'] }}">
                        {{ $row['nama'] }} • {{ $row['persentase'] }}%
                    </p>
                    @endforeach
                </div>
                @else
                <p class="text-sm text-zinc-400">Belum ada pengeluaran pada dompet ini.</p>
                @endif

                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Bukti pencairan</span>
                        @if ($wallet['bukti'] ?? null)
                        <flux:button size="sm" variant="ghost" icon="paper-clip" type="button"
                            x-on:click="$dispatch('preview-bukti', { url: '{{ $this->buktiUrl($wallet['bukti']) }}', title: 'Bukti Pencairan' }); $flux.modal('bukti-preview').show()" />
                        @else
                        <span class="text-zinc-300">-</span>
                        @endif
                    </div>
                    @if ($this->isClosed() && ($wallet['bukti_setor'] ?? null))
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Bukti setor balik</span>
                        <flux:button size="sm" variant="ghost" icon="paper-clip" type="button"
                            x-on:click="$dispatch('preview-bukti', { url: '{{ $this->buktiUrl($wallet['bukti_setor']) }}', title: 'Bukti Setor Balik' }); $flux.modal('bukti-preview').show()" />
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- TRANSAKSI --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <flux:heading class="font-bold">Riwayat Transaksi</flux:heading>
                <div class="flex-1 relative max-w-xs">
                    <flux:input wire:model="search" wire:keydown.enter="performSearch" icon="magnifying-glass" placeholder="Cari deskripsi / kategori (tekan Enter)" wire:loading.attr="disabled" wire:target="performSearch" />
                    <div wire:loading wire:target="performSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <div class="animate-spin h-5 w-5 border-2 border-zinc-300 border-t-zinc-900 dark:border-zinc-600 dark:border-t-white rounded-full"></div>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-[900px] md:min-w-full text-sm text-left text-zinc-600 dark:text-zinc-300">
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
                    <tbody wire:loading.class="opacity-50" wire:target="performSearch">
                        @forelse ($this->transaksi() as $trx)
                        <tr wire:key="trx-kegiatan-{{ $trx['id_transaksi'] }}" class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d/m/Y') }}</td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                                <flux:badge size="sm" :color="$trx['jenis'] === 'pemasukan' ? 'green' : 'red'">
                                    {{ ucfirst($trx['jenis']) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $trx['kategori'] ?? '-' }}</td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap text-zinc-900 dark:text-white">{{ $trx['deskripsi'] }}</td>
                            <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap tabular-nums {{ $trx['jenis'] === 'pemasukan' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $trx['jenis'] === 'pemasukan' ? '+' : '−' }} Rp {{ number_format($trx['jumlah'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap tabular-nums {{ $trx['saldo_setelah'] < 0 ? 'text-red-600' : '' }}">
                                Rp {{ number_format($trx['saldo_setelah'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                                @if ($trx['bukti_url'] ?? false)
                                <flux:button size="sm" variant="ghost" icon="paper-clip" type="button"
                                    x-on:click="$dispatch('preview-bukti', { url: '{{ $trx['bukti_url'] }}', title: '{{ addslashes($trx['deskripsi'] ?? 'Bukti Transaksi') }}' }); $flux.modal('bukti-preview').show()" />
                                @else
                                <span class="text-zinc-300">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                                @unless ($this->isClosed())
                                <div class="flex items-center justify-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" type="button"
                                        x-on:click="$flux.modal('catat-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-edit-transaksi', { id: {{ $trx['id_transaksi'] }} })" />
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600" type="button"
                                        x-on:click="$flux.modal('hapus-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-confirm-delete', { id: {{ $trx['id_transaksi'] }} })" />
                                </div>
                                @else
                                <span class="text-zinc-300">-</span>
                                @endunless
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-zinc-400">Belum ada transaksi pada dompet ini</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ================= MODAL COMPONENTS ================= --}}
        <livewire:cashadvance.modals.catat-transaksi-kegiatan :kode-ca="$kodeCa" :categories="$categories" />
        <livewire:cashadvance.modals.hapus-transaksi-kegiatan :kode-ca="$kodeCa" />
        <livewire:cashadvance.modals.tutup-kegiatan :wallet="$wallet" />

        {{-- ================= MODAL: PREVIEW BUKTI ================= --}}
        <flux:modal name="bukti-preview" class="w-2xl"
            x-data="{
                url: '',
                title: 'Bukti Transaksi',
                get isPdf() { return this.url.split('?')[0].toLowerCase().endsWith('.pdf'); },
            }"
            x-on:preview-bukti.window="url = $event.detail.url; title = $event.detail.title">
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Bukti Transaksi</flux:heading>
                    <flux:text class="mt-1 truncate" x-text="title"></flux:text>
                </div>

                <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <template x-if="isPdf">
                        <iframe :src="url" class="h-[70vh] w-full" title="Bukti PDF"></iframe>
                    </template>
                    <template x-if="! isPdf">
                        <img :src="url" alt="Bukti transaksi" class="max-h-[70vh] w-full object-contain" />
                    </template>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button href="#" x-bind:href="url" target="_blank" variant="ghost" icon="arrow-top-right-on-square">
                        Buka di tab baru
                    </flux:button>
                    <flux:modal.close>
                        <flux:button variant="ghost">Tutup</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        </flux:modal>
    </div>
</div>
