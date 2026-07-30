<?php

use App\Services\CaCache;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public array $caPlBalance = [];
    public array $chart = [];
    public array $caKegiatanBalance = [];

    /** @var array<int, string> */
    public array $categories = [];

    public string $search = '';

    public function mount(): void
    {
        $this->Dompet();
    }

    public function performSearch(): void
    {
        // Method ini dipanggil saat Enter di search input
        // Blade akan refresh component dan meload data dengan search param
    }

    /**
     * Transaksi periode aktif; setiap baris diberi bukti_url dari MinIO
     * (aws_url + bucket cashadvance + path), mengikuti pola attachment-card.
     *
     * @return array<string, mixed>
     */
    public function transaksiCaPl($kode): array
    {
        $result = app(CaCache::class)->transaksiCaPlActive($kode, ['search' => $this->search])['data'] ?? [];
        $result = array_map(function (array $trx): array {
            $trx['bukti_url'] = $this->buktiUrl($trx['bukti'] ?? null);

            return $trx;
        }, $result ?? []);

        return $result;
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

    #[On('ca-updated')]
    public function refreshDompet(): void
    {
        $this->Dompet();

        $this->dispatch(
            'ca-chart-updated',
            series: $this->getChart()
        );
    }

    public function Dompet() : Array
    {
        $reponse = app(CaCache::class)->DompetCaActiveTransaksi(Auth::id());

        $this->caPlBalance = collect($reponse)->where('is_active', 1)->first() ?? [];

        $this->categories = $response['categories'] ?? ['Transport', 'Akomodasi', 'Konsumsi', 'ATK', 'Komunikasi', 'Lain-lain'];

        $this->loadKegiatan();

        return collect($reponse)->first() ?? [];
    }

    /**
     * Dompet kegiatan aktif milik user (kartu sidebar ca-index).
     */
    public function loadKegiatan(): void
    {
        $response = app(CaCache::class)->listWalletKegiatan(Auth::id());
        $data = $response['data'] ?? [];

        $this->caKegiatanBalance = [
            'jumlah_dompet' => $response['meta']['total'] ?? count($data),
            'data' => collect($data)
                ->map(fn (array $item): array => [
                    'kode_ca' => $item['kode_ca'],
                    'judul_kegiatan' => $item['judul_kegiatan'],
                    'status' => ($item['is_active'] ?? 1) ? 'open' : 'closed',
                    'sisa_saldo' => (float) ($item['saldo_akhir'] ?? 0),
                ])
                ->all(),
        ];
    }

    public function hasCa() {
        return $this->caPlBalance['kode_ca'] ?? [];
    }

    public function getChart(): array
    {
        $transactions = collect(
            app(CaCache::class)->transaksiCaPlActive($this->caPlBalance['kode_ca'])['data'] ?? []
        );

        $expense = collect(range(1, 12))
            ->map(function ($month) use ($transactions) {
                return $transactions
                    ->filter(fn ($transaction) =>
                        ($transaction['jenis'] ?? null) === 'pengeluaran'
                        && Carbon\Carbon::parse($transaction['tanggal'])->month === $month
                    )
                    ->sum(fn ($transaction) => (float) $transaction['jumlah']);
            })
            ->values()
            ->all();


        return [
            [
                'name' => 'Expense',
                'data' => $expense,
            ],
        ];
    }

    public function getKategoriPengeluaran() {
        $transaksi = collect(
            $this->transaksiCaPl($this->caPlBalance['kode_ca']) ?? []
        );

       $pengeluaran = $transaksi
        ->filter(fn ($transaction) =>
            ($transaction['jenis'] ?? null) === 'pengeluaran'
        );

        $totalPengeluaran = $pengeluaran->sum(
            fn($transaksi) => (float) $transaksi['jumlah']
        );

        return $pengeluaran
            ->groupBy('kategori')
            ->map(function($transaksi, $kategori) use ($totalPengeluaran){
                $jumlah = $transaksi->sum(
                    fn($transaksi) => (float) $transaksi['jumlah']
                );

                return [
                'nama' => $kategori,
                'jumlah' => $jumlah,
                'persentase' => $totalPengeluaran > 0
                    ? round(($jumlah / $totalPengeluaran) * 100, 1)
                    : 0,
                ];
            })
            ->sortByDesc('jumlah')
            ->values()
            ->all();
    }

}; ?>

<div class="space-y-4">
    @if (!$this->hasCa())
    {{-- ================= ONBOARDING: BELUM MEMILIKI CA ================= --}}
    <div class="relative rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">

        <div class="mx-auto flex max-w-lg flex-col items-center gap-5 px-6 py-20 text-center">
            <div class="flex size-16 items-center justify-center rounded-full bg-blue-500/10 dark:bg-blue-500/20">
                <flux:icon.wallet class="size-8 text-blue-500" />
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">Anda belum memiliki Cash Advance</flux:heading>
                <flux:text>
                    Lakukan top up pertama untuk membuka dompet CA PL Anda.
                    Setelah dana masuk, saldo, dompet kegiatan, dan riwayat transaksi akan tampil di halaman ini.
                </flux:text>
            </div>

            <div class="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 text-left">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <flux:icon.banknotes class="size-5 text-zinc-400 mb-1.5" />
                    <p class="text-xs font-medium text-zinc-900 dark:text-white">1. Ajukan top up</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Isi nominal &amp; bukti transfer</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <flux:icon.clock class="size-5 text-zinc-400 mb-1.5" />
                    <p class="text-xs font-medium text-zinc-900 dark:text-white">2. Menunggu verifikasi</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Finance memverifikasi pengajuan</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                    <flux:icon.check-circle class="size-5 text-zinc-400 mb-1.5" />
                    <p class="text-xs font-medium text-zinc-900 dark:text-white">3. CA aktif</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">Dompet siap mencatat pengeluaran</p>
                </div>
            </div>

            <flux:modal.trigger name="topup-pertama">
                <flux:button variant="primary" icon="arrow-up-tray">Top up Pertama</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:cashadvance.modals.topup-pertama />
    @else
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
                            <flux:badge size="sm" color="zinc">{{ $this->caPlBalance['kode_ca'] ?? 'N/A' }}</flux:badge>
                        </div>

                        <h2 class="text-4xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                            Rp {{ number_format($this->caPlBalance['saldo_akhir'] ?? 0, 0, ',', '.') }}
                        </h2>

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Dibuka {{ Carbon\Carbon::parse($this->caPlBalance['created_at'])->locale('id')->translatedFormat('D, d M Y') }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        <flux:button size="sm" variant="primary" icon="minus-circle"
                            x-on:click="$flux.modal('catat-pengeluaran').show(); $wire.dispatch('ca-new-expense')">
                            Catat Pengeluaran
                        </flux:button>

                        <flux:modal.trigger name="topup-laporan">
                            <flux:button size="sm" variant="outline" icon="arrow-up-tray">Top up</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                {{-- CHART (data statik; wiring API dilakukan manual) --}}
                <div wire:ignore x-data="{ chart: null }" x-init="
                        chart = new ApexCharts($refs.caChart, {
                            series: @js($this->getChart() ?? []),
                            chart: { height: 280, type: 'area', toolbar: {
                                show: true,
                                tools: {
                                    download: true,
                                    selection: true,
                                    zoom: true,
                                    zoomin: true,
                                    zoomout: true,
                                    pan: true,
                                    reset: true,
                                },
                            }, },
                            dataLabels: { enabled: false },
                            stroke: { curve: 'smooth', width: 3 },
                            colors: ['#E91E63'],
                            xaxis: { type: 'category', categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] },
                            yaxis: { labels: { align: 'left', formatter: (v) => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } },
                            tooltip: { theme: 'light' },

                        });
                        chart.render();
                        Livewire.on('ca-chart-updated', (data) => {
                            chart.updateSeries(data.series);
                        });
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
                        Rp {{ number_format($this->caPlBalance['saldo_awal_priode'] ?? 0, 0, ',', '.') }}
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Saldo awal periode aktif</p>
                </div>

                <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Expense</p>
                        <flux:badge size="sm" color="red">Pengeluaran</flux:badge>
                    </div>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">
                        Rp {{ number_format($this->caPlBalance['total_pengeluaran'] ?? 0, 0, ',', '.') }}
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
                    <span class="text-sm text-zinc-400">{{ $this->caKegiatanBalance['jumlah_dompet'] ?? 0 }} dompet</span>
                </div>
                <div class="space-y-1">
                    @forelse (collect($this->caKegiatanBalance['data'] ?? [])->take(4) as $item)
                    @php $isClosed = ($item['status'] ?? 'open') === 'closed'; @endphp
                    <a wire:key="dompet-{{ $item['kode_ca'] }}" href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}" wire:navigate
                        class="flex items-center justify-between -mx-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition {{ $isClosed ? 'opacity-60' : '' }}">
                        <div class="flex items-center gap-3 min-w-0">
                            <flux:avatar :name="$item['judul_kegiatan']" :color="$isClosed ? 'zinc' : 'blue'" size="sm" />
                            <div class="text-left min-w-0">
                                <p class="text-sm font-medium truncate text-zinc-900 dark:text-white">{{ $item['judul_kegiatan'] }}</p>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-zinc-500">{{ $item['kode_ca'] }}</span>
                                    @if ($isClosed)
                                    <flux:badge size="sm" color="zinc">closed</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <p class="text-sm font-medium tabular-nums shrink-0 {{ $item['sisa_saldo'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                            Rp {{ number_format($item['sisa_saldo'], 0, ',', '.') }}
                        </p>
                    </a>
                    @empty
                    <p class="text-sm text-zinc-400 py-2">Belum ada dompet kegiatan.</p>
                    @endforelse
                </div>
                @if (count($this->caKegiatanBalance['data'] ?? []) > 4)
                <flux:modal.trigger name="semua-dompet-kegiatan">
                    <button type="button" class="flex w-full justify-end mb-5 items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900 dark:hover:text-white">
                        Lihat semua ({{ $this->caKegiatanBalance['jumlah_dompet'] ?? 0 }}) <flux:icon name="arrow-right" class="size-3.5" />
                    </button>
                </flux:modal.trigger>
                @endif
                <flux:modal.trigger name="buat-kegiatan">
                    <flux:button variant="outline" class="w-full" icon="plus">Create new card</flux:button>
                </flux:modal.trigger>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori &mdash; periode aktif</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($this->caPlBalance['total_pengeluaran'] ?? 0, 0, ',', '.') }}
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
                @if (!empty($this->caPlBalance['total_pengeluaran']))
                <div class="h-10 rounded bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                    @foreach ($this->getKategoriPengeluaran() as $i => $row)
                    @php
                    $c = $palette[$i % count($palette)];
                    @endphp
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
                <p class="text-sm text-zinc-400">Belum ada pengeluaran pada periode ini.</p>
                @endif


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
                <div class="flex-1 relative">
                    <flux:input wire:model="search" wire:keydown.enter="performSearch" icon="magnifying-glass" placeholder="Cari deskripsi / kategori (tekan Enter)" wire:loading.attr="disabled" wire:target="performSearch" />
                    <div wire:loading wire:target="performSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <div class="animate-spin h-5 w-5 border-2 border-zinc-300 border-t-zinc-900 dark:border-zinc-600 dark:border-t-white rounded-full"></div>
                    </div>
                </div>
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
                <tbody wire:loading.class="opacity-50" wire:target="performSearch">
                    @forelse ($this->transaksiCaPl($caPlBalance['kode_ca']) ?? [] as $trx)
                    <tr wire:key="trx-{{ $trx['id_transaksi'] }}" class="border-t border-zinc-100 dark:border-zinc-800">
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap text-zinc-900 dark:text-white">{{ $trx['deskripsi'] }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $trx['kategori'] ?? '-' }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ \Carbon\Carbon::parse($trx['created_at'])->translatedFormat('d/m/Y h:i') }}</td>
                        <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                            <flux:badge size="sm" :color="$trx['jenis'] === 'pemasukan' ? 'green' : 'red'">
                                {{ ucfirst($trx['jenis']) }}
                            </flux:badge>
                        </td>
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
                            <div class="flex items-center justify-center gap-1">
                                <flux:button size="sm" variant="ghost" icon="pencil-square" type="button"
                                    x-on:click="$flux.modal('catat-pengeluaran').show(); $wire.dispatch('ca-edit-expense', { id: {{ $trx['id_transaksi'] }} })" />
                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600" type="button"
                                    x-on:click="$flux.modal('hapus-pengeluaran').show(); $wire.dispatch('ca-confirm-delete', { id: {{ $trx['id_transaksi'] }} })" />
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-8 text-center text-zinc-400">Belum ada transaksi pada periode aktif</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ================= MODAL COMPONENTS ================= --}}
    <livewire:cashadvance.modals.catat-pengeluaran :kode-ca="$this->caPlBalance['kode_ca'] ?? ''" :categories="$categories" />
    <livewire:cashadvance.modals.hapus-pengeluaran :kode-ca="$this->caPlBalance['kode_ca'] ?? ''" />
    <livewire:cashadvance.modals.topup-laporan :balance="$caPlBalance" />
    <livewire:cashadvance.modals.buat-kegiatan :kode-ca="$this->caPlBalance['kode_ca'] ?? ''" />
    <livewire:cashadvance.modals.riwayat-periode />
    <livewire:cashadvance.modals.semua-dompet-kegiatan />

    {{-- ================= MODAL: PREVIEW BUKTI ================= --}}
    {{-- Satu modal dipakai bersama semua baris; url & judul diisi lewat event Alpine dari tombol paper-clip. --}}
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
    @endif
</div>
