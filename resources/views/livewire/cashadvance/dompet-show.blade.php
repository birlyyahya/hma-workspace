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

        $chart = $this->getChart();
        $this->dispatch('ca-chart-updated', series: $chart['series'], categories: $chart['categories']);
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
     * Chart masuk vs keluar per hari (bukan per bulan), dari transaksi yang
     * sudah ter-embed di detail wallet. Rentang tanggal mengikuti
     * tanggal_mulai s/d tanggal_selesai (atau tanggal transaksi bila field
     * tersebut kosong), sehingga sumbu-x menampilkan tanggal kalender asli
     * per kegiatan, bukan urutan bulan 1–12 yang generik.
     *
     * @return array{categories: array<int, string>, series: array<int, array{name: string, data: array<int, float>}>}
     */
    public function getChart(): array
    {
        $transactions = collect($this->wallet['tr_ca_transaction'] ?? []);
        $tanggalTransaksi = $transactions->map(fn ($t) => \Carbon\Carbon::parse($t['tanggal']));

        $start = isset($this->wallet['tanggal_mulai'])
            ? \Carbon\Carbon::parse($this->wallet['tanggal_mulai'])->startOfDay()
            : ($tanggalTransaksi->min() ?? now())->copy()->startOfDay();

        $end = ! empty($this->wallet['tanggal_selesai'])
            ? \Carbon\Carbon::parse($this->wallet['tanggal_selesai'])->startOfDay()
            : ($tanggalTransaksi->max() ?? now())->copy()->startOfDay();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        // Minimum tampilkan rentang seminggu supaya garis chart tidak jadi
        // satu titik saja saat kegiatan cuma berlangsung 1 hari; kalau
        // rentang asli sudah lebih panjang, ikuti rentang asli (dinamis).
        if ($start->diffInDays($end) < 6) {
            $end = $start->copy()->addDays(6);
        }

        $dates = collect(\Carbon\CarbonPeriod::create($start, $end));

        $seriesFor = fn (string $jenis): array => $dates
            ->map(fn ($date) => $transactions
                ->filter(fn ($t) => ($t['jenis'] ?? null) === $jenis
                    && \Carbon\Carbon::parse($t['tanggal'])->isSameDay($date))
                ->sum(fn ($t) => (float) $t['jumlah']))
            ->values()
            ->all();

        return [
            'categories' => $dates->map(fn ($date) => $date->translatedFormat('d M'))->all(),
            'series' => [
                ['name' => 'Masuk', 'data' => $seriesFor('penerimaan')],
                ['name' => 'Keluar', 'data' => $seriesFor('pengeluaran')],
            ],
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

    /**
     * Palet warna "kantong" yang dirotasi per indeks, konsisten dengan carousel
     * dompet kegiatan & breakdown kategori di ca-index.
     *
     * @return array{dot: string, text: string}
     */
    private function pocketTone(int $index): array
    {
        $palette = [
            ['dot' => 'bg-ca-pocket-1', 'text' => 'text-ca-pocket-1'],
            ['dot' => 'bg-ca-pocket-2', 'text' => 'text-ca-pocket-2'],
            ['dot' => 'bg-ca-pocket-3', 'text' => 'text-ca-pocket-3'],
            ['dot' => 'bg-ca-pocket-4', 'text' => 'text-ca-pocket-4'],
            ['dot' => 'bg-ca-pocket-5', 'text' => 'text-ca-pocket-5'],
        ];

        return $palette[$index % count($palette)];
    }

    /**
     * Ikon Flux untuk kategori transaksi; dipakai pada chip ikon baris transaksi.
     */
    private function iconForKategori(?string $kategori): string
    {
        return match ($kategori) {
            'Transport' => 'truck',
            'Akomodasi' => 'building-office',
            'Konsumsi' => 'shopping-bag',
            'ATK' => 'pencil-square',
            'Komunikasi' => 'signal',
            default => 'ellipsis-horizontal',
        };
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
                        <flux:badge size="sm" color="zinc">Ditutup</flux:badge>
                        @else
                        <flux:badge size="sm" color="green">Aktif</flux:badge>
                        @endif
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
                <flux:button :href="route('cashadvance.laporan.kegiatan', $kodeCa)" target="_blank" variant="ghost" icon="document-arrow-down" size="sm" class="text-ca-copper! hover:text-ca-copper-deep!">Export PDF</flux:button>
            </div>
        </div>

        {{-- TOP GRID: BALANCE + CHART / BREAKDOWN --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            {{-- LEFT: MASTER WALLET CARD (saldo + chart menyatu) --}}
            <div class="lg:col-span-2">
                <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-ca-ink-lift to-ca-ink p-6 text-white shadow-xl shadow-black/10">
                    <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 24px);"></div>

                    <div class="relative space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-gold">Dompet Kegiatan</p>
                                <p class="mt-1 text-xs text-white/50">{{ $wallet['kode_ca'] ?? '' }}</p>
                            </div>
                            @if ($this->isClosed())
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white/70">
                                <flux:icon name="lock-closed" class="size-3" /> Ditutup
                            </span>
                            @else
                            <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">
                                <span class="size-1.5 rounded-full bg-emerald-400"></span> Aktif
                            </span>
                            @endif
                        </div>

                        <div>
                            <p class="text-xs text-white/50">Saldo berjalan</p>
                            <h2 class="mt-1 text-4xl font-bold tabular-nums sm:text-5xl">
                                Rp {{ number_format($wallet['saldo_akhir'] ?? 0, 0, ',', '.') }}
                            </h2>
                        </div>

                        {{-- CHART: masuk vs keluar per hari (mengikuti rentang tanggal kegiatan) --}}
                        @php $chart = $this->getChart(); @endphp
                        <div wire:key="kegiatan-chart-wrapper" wire:ignore x-data="{ chart: null }" x-init="
                                chart = new ApexCharts($refs.kegiatanChart, {
                                    series: @js($chart['series'] ?? []),
                                    chart: { height: 200, type: 'area', toolbar: { show: false }, background: 'transparent' },
                                    dataLabels: { enabled: false },
                                    stroke: { curve: 'smooth', width: 3 },
                                    colors: ['#34D399', '#D9A054'],
                                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0, stops: [0, 90, 100] } },
                                    grid: { show: false },
                                    legend: { labels: { colors: '#c9beb3' }, fontSize: '11px' },
                                    xaxis: { type: 'category', categories: @js($chart['categories'] ?? []), labels: { style: { colors: '#c9beb3', fontSize: '10px' } }, axisBorder: { show: false }, axisTicks: { show: false } },
                                    yaxis: { show: false },
                                    tooltip: { theme: 'dark' },
                                });
                                chart.render();
                                Livewire.on('ca-chart-updated', (data) => {
                                    chart.updateOptions({ xaxis: { categories: data.categories } });
                                    chart.updateSeries(data.series);
                                });
                            ">
                            <div x-ref="kegiatanChart" class="-mx-2 h-52 w-[calc(100%+1rem)]"></div>

                            <div class="flex gap-2">
                                @unless ($this->isClosed())
                                <flux:button variant="primary" icon="plus" size="sm" class="bg-ca-copper! items-center! flex-1  py-2.5! rounded-xl hover:bg-ca-copper-deep!"
                                    x-on:click="$flux.modal('catat-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-new-transaksi')">
                                    Catat Transaksi
                                </flux:button>
                                <flux:modal.trigger name="tutup-kegiatan">
                                    <button variant="outline"  class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-4 text-sm font-semibold text-white transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ca-gold"
                                     icon="lock-closed" size="sm">
                                      <flux:icon name="lock-closed" class="size-4" />
                                     Tutup Kegiatan</button>
                                </flux:modal.trigger>
                                @endunless
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4 border-t border-white/10 pt-4">
                            <div>
                                <p class="text-[11px] text-white/50">Saldo Awal</p>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-white">
                                    Rp {{ number_format($wallet['saldo_awal_priode'] ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-white/50">Total Masuk</p>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-300">
                                    Rp {{ number_format($wallet['total_penerimaan'] ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-white/50">Total Keluar</p>
                                <p class="mt-0.5 text-sm font-semibold tabular-nums text-orange-300">
                                    Rp {{ number_format($wallet['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: BREAKDOWN PER KATEGORI --}}
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($wallet['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                </h3>
                @if (!empty($wallet['total_pengeluaran']))
                <div class="h-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                    @foreach ($this->getKategoriPengeluaran() as $i => $row)
                    <div class="{{ $this->pocketTone($i)['dot'] }}" style="width: {{ max($row['persentase'], 2) }}%" wire:key="bar-{{ $row['nama'] }}"></div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($this->getKategoriPengeluaran() as $i => $row)
                    <p class="flex items-center gap-1.5" wire:key="legend-{{ $row['nama'] }}">
                        <span class="size-2 rounded-full {{ $this->pocketTone($i)['dot'] }}"></span>
                        {{ $row['nama'] }} &middot; {{ $row['persentase'] }}%
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
                        <flux:button size="sm" variant="ghost" icon="paper-clip" type="button" class="text-ca-copper! hover:text-ca-copper-deep!"
                            x-on:click="$dispatch('preview-bukti', { url: '{{ $this->buktiUrl($wallet['bukti']) }}', title: 'Bukti Pencairan' }); $flux.modal('bukti-preview').show()" />
                        @else
                        <span class="text-zinc-300">-</span>
                        @endif
                    </div>
                    @if ($this->isClosed() && ($wallet['bukti_setor'] ?? null))
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-zinc-500 dark:text-zinc-400">Bukti setor balik</span>
                        <flux:button size="sm" variant="ghost" icon="paper-clip" type="button" class="text-ca-copper! hover:text-ca-copper-deep!"
                            x-on:click="$dispatch('preview-bukti', { url: '{{ $this->buktiUrl($wallet['bukti_setor']) }}', title: 'Bukti Setor Balik' }); $flux.modal('bukti-preview').show()" />
                    </div>
                    @endif
                </div>

                {{-- ================= KOLABORATOR (placeholder — belum tersambung backend) ================= --}}
                {{-- Opsi A (avatar stack) di mobile, Opsi B (list detail) di desktop; sesuai preview yang disetujui. --}}
                @php
                    $collaborators = [
                        ['nama' => 'Andre Lukmana', 'inisial' => 'AL', 'role' => 'Editor'],
                        ['nama' => 'Budi Santoso', 'inisial' => 'BS', 'role' => 'Viewer'],
                        ['nama' => 'Citra Dewi', 'inisial' => 'CD', 'role' => 'Editor'],
                        ['nama' => 'Dedi Firmansyah', 'inisial' => 'DF', 'role' => 'Viewer'],
                    ];
                @endphp
                <div class="pt-2 border-t border-zinc-200 dark:border-zinc-700 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 sm:hidden">Kolaborator</p>
                        <p class="hidden text-sm text-zinc-500 dark:text-zinc-400 sm:block">Kolaborator &middot; {{ count($collaborators) }}</p>
                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-ca-copper hover:text-ca-copper-deep">
                            <flux:icon name="plus" class="size-3.5" /> Undang
                        </button>
                    </div>

                    {{-- Mobile: avatar stack ringkas --}}
                    <div class="sm:hidden">
                        <div class="flex items-center">
                            @foreach (array_slice($collaborators, 0, 3) as $i => $person)
                            <span wire:key="collab-avatar-{{ $i }}"
                                class="flex size-8 items-center justify-center rounded-full text-[11px] font-bold text-white ring-2 ring-white dark:ring-zinc-900 {{ $this->pocketTone($i)['dot'] }} {{ $i > 0 ? '-ml-2' : '' }}">
                                {{ $person['inisial'] }}
                            </span>
                            @endforeach
                            @if (count($collaborators) > 3)
                            <span class="-ml-2 flex size-8 items-center justify-center rounded-full bg-zinc-100 text-[10.5px] font-bold text-zinc-500 ring-2 ring-white dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-900">
                                +{{ count($collaborators) - 3 }}
                            </span>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ collect($collaborators)->take(3)->pluck('nama')->map(fn ($n) => explode(' ', $n)[0])->join(', ') }}
                            @if (count($collaborators) > 3), +{{ count($collaborators) - 3 }} lainnya @endif
                        </p>
                    </div>

                    {{-- Desktop: list detail per anggota --}}
                    <div class="hidden sm:block sm:space-y-1">
                        @foreach ($collaborators as $i => $person)
                        <div wire:key="collab-row-{{ $i }}" class="flex items-center gap-3 py-1">
                            <span class="flex size-8 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white {{ $this->pocketTone($i)['dot'] }}">
                                {{ $person['inisial'] }}
                            </span>
                            <p class="min-w-0 flex-1 truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $person['nama'] }}</p>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $person['role'] === 'Editor' ? 'bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper' : 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' }}">
                                {{ $person['role'] }}
                            </span>
                            <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- RIWAYAT TRANSAKSI --}}
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
            <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-white/50">Riwayat Transaksi</p>
                </div>
                <div class="flex-1 relative max-w-xs">
                    <flux:input wire:model="search" wire:keydown.enter="performSearch" icon="magnifying-glass" placeholder="Cari deskripsi / kategori (tekan Enter)" wire:loading.attr="disabled" wire:target="performSearch" />
                    <div wire:loading wire:target="performSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <div class="animate-spin h-5 w-5 border-2 border-zinc-300 border-t-zinc-900 dark:border-zinc-600 dark:border-t-white rounded-full"></div>
                    </div>
                </div>
            </div>

            <div wire:loading.class="opacity-50" wire:target="performSearch" class="divide-y divide-zinc-100 dark:divide-zinc-800 border-t border-zinc-100 dark:border-zinc-800">
                @forelse ($this->transaksi() as $trx)
                @php $isIn = ($trx['jenis'] ?? null) === 'penerimaan'; @endphp
                <div wire:key="trx-kegiatan-{{ $trx['id_transaksi'] }}" class="flex items-center gap-3 px-4 py-3 md:px-6">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $isIn ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' : 'bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper' }}">
                        <flux:icon :name="$isIn ? 'banknotes' : $this->iconForKategori($trx['kategori'] ?? null)" class="size-5" />
                    </span>

                    <div class="hidden w-24 shrink-0 text-sm text-zinc-500 dark:text-zinc-400 sm:block">
                        {{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d M Y') }}
                    </div>

                    <div class="hidden shrink-0 sm:block">
                        <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                            {{ $isIn ? 'Pemasukan' : ($trx['kategori'] ?? '-') }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="line-champ-2 text-xs sm:text-sm md:text-md lg:text-md font-normal text-zinc-900 dark:text-white">{{ $trx['deskripsi'] }}</p>
                        <div class="mt-1 flex items-center gap-2 sm:hidden">
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                {{ $isIn ? 'Pemasukan' : ($trx['kategori'] ?? '-') }}
                            </span>
                            <span class="text-xs text-zinc-400">{{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d M Y') }}</span>
                        </div>
                    </div>

                    <div class="shrink-0 text-right">
                        <p class="text-sm font-semibold tabular-nums {{ $isIn ? 'text-emerald-600 dark:text-emerald-400' : 'text-ca-copper-deep dark:text-ca-copper' }}">
                            {{ $isIn ? '+' : '−' }} Rp {{ number_format($trx['jumlah'], 0, ',', '.') }}
                        </p>
                        <p class="text-xs text-zinc-400 {{ $trx['saldo_setelah'] < 0 ? 'text-red-500' : '' }}">
                            Saldo Rp {{ number_format($trx['saldo_setelah'], 0, ',', '.') }}
                        </p>
                    </div>

                    <flux:dropdown position="bottom" align="end">
                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                        <flux:menu>
                            @if ($trx['bukti_url'] ?? false)
                            <flux:menu.item icon="paper-clip"
                                x-on:click="$dispatch('preview-bukti', { url: '{{ $trx['bukti_url'] }}', title: '{{ addslashes($trx['deskripsi'] ?? 'Bukti Transaksi') }}' }); $flux.modal('bukti-preview').show()">
                                Lihat Bukti
                            </flux:menu.item>
                            @endif
                            @unless ($this->isClosed())
                            <flux:menu.item icon="pencil-square"
                                x-on:click="$flux.modal('catat-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-edit-transaksi', { id: {{ $trx['id_transaksi'] }} })">
                                Edit
                            </flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger"
                                x-on:click="$flux.modal('hapus-transaksi-kegiatan').show(); $wire.dispatch('ca-kegiatan-confirm-delete', { id: {{ $trx['id_transaksi'] }} })">
                                Hapus
                            </flux:menu.item>
                            @endunless
                        </flux:menu>
                    </flux:dropdown>
                </div>
                @empty
                <div class="px-6 py-10 text-center text-sm text-zinc-400">Belum ada transaksi pada dompet ini</div>
                @endforelse
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
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Lampiran</p>
                    <flux:heading size="lg" class="mt-1">Bukti Transaksi</flux:heading>
                    <flux:text class="mt-1 truncate" x-text="title"></flux:text>
                </div>

                <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800">
                    <template x-if="isPdf">
                        <iframe :src="url" class="h-[70vh] w-full" title="Bukti PDF"></iframe>
                    </template>
                    <template x-if="! isPdf">
                        <img :src="url" alt="Bukti transaksi" class="max-h-[70vh] w-full object-contain" />
                    </template>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:button href="#" x-bind:href="url" target="_blank" variant="ghost" icon="arrow-top-right-on-square" class="text-ca-copper! hover:text-ca-copper-deep!">
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
