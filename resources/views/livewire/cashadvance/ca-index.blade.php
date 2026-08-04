<?php

use App\Services\CaCache;
use App\Services\CaKategoriPengeluaran;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public array $caPlBalance = [];
    public array $chart = [];
    public array $caKegiatanBalance = [];

    /** @var array<int, string> */
    public array $categories = [];

    public string $search = '';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public function mount(): void
    {
        $this->Dompet();
    }

    /**
     * Skeleton yang tampil selama komponen lazy di-mount (fetch data BEPM).
     */
    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('livewire.cashadvance.ca-index-placeholder');
    }

    public function performSearch(): void
    {
        // Method ini dipanggil saat Enter di search input
        // Blade akan refresh component dan meload data dengan search param
    }

    /**
     * Bersihkan filter tanggal dari dropdown kalender (tombol Reset).
     */
    public function resetDateFilter(): void
    {
        $this->reset(['startDate', 'endDate']);
    }

    /**
     * Label periode di header tabel — default merujuk periode aktif, atau
     * rentang tanggal terpilih bila filter kalender sedang dipakai.
     */
    public function periodeLabel(): string
    {
        if (! $this->startDate && ! $this->endDate) {
            return 'Periode aktif saat ini';
        }

        $from = $this->startDate ? \Carbon\Carbon::parse($this->startDate)->translatedFormat('d M Y') : 'awal periode';
        $to = $this->endDate ? \Carbon\Carbon::parse($this->endDate)->translatedFormat('d M Y') : 'sekarang';

        return "{$from} – {$to}";
    }

    /**
     * Transaksi periode aktif; setiap baris diberi bukti_url dari MinIO
     * (aws_url + bucket cashadvance + path), mengikuti pola attachment-card.
     * Filter tanggal (dropdown kalender) diterapkan di sisi PHP terhadap
     * hasil periode aktif — API tidak punya parameter rentang tanggal untuk
     * endpoint ini, jadi rentang cuma bisa mempersempit periode yang sudah
     * termuat, bukan menembus periode lain yang sudah tersegel.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function transaksiCaPl(): array
    {
        $kode = $this->caPlBalance['kode_ca'] ?? '';
        $result = app(CaCache::class)->transaksiCaPlActive($kode, ['search' => $this->search])['data'] ?? [];
        $result = array_map(function (array $trx): array {
            $trx['bukti_url'] = $this->buktiUrl($trx['bukti'] ?? null);

            return $trx;
        }, $result ?? []);

        if ($this->startDate || $this->endDate) {
            $start = $this->startDate ? \Carbon\Carbon::parse($this->startDate)->startOfDay() : null;
            $end = $this->endDate ? \Carbon\Carbon::parse($this->endDate)->endOfDay() : null;

            $result = array_values(array_filter($result, function (array $trx) use ($start, $end): bool {
                if (blank($trx['tanggal'] ?? null)) {
                    return false;
                }

                $tanggal = \Carbon\Carbon::parse($trx['tanggal']);

                return (! $start || $tanggal->greaterThanOrEqualTo($start))
                    && (! $end || $tanggal->lessThanOrEqualTo($end));
            }));
        }

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

        return Storage::disk('s3_cashadvance')->temporaryUrl($path, now()->addMinutes(60), ['ResponseContentDisposition' => 'inline; filename="'.basename($path).'"']);
    }

    #[On('ca-updated')]
    public function refreshDompet(): void
    {
        $this->Dompet();

        unset($this->getChart, $this->transaksiCaPl, $this->getKategoriPengeluaran);

        $this->dispatch(
            'ca-chart-updated',
            series: $this->getChart
        );
    }

    public function Dompet() : Array
    {
        $reponse = app(CaCache::class)->DompetCaActiveTransaksi(Auth::id());

        $this->caPlBalance = collect($reponse)->where('is_active', 1)->first() ?? [];

        $this->categories = $response['categories'] ?? CaKategoriPengeluaran::labels();

        $this->loadKegiatan();

        return collect($reponse)->first() ?? [];
    }

    /**
     * Dompet kegiatan aktif milik user (carousel kantong di ca-index).
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
                    // true bila dompet ini bukan milik user (dia muncul di
                    // daftar karena jadi kolaborator, bukan pemegang CA).
                    'is_collaborator' => (int) ($item['user_id'] ?? 0) !== (int) Auth::id(),
                ])
                ->all(),
        ];
    }

    public function hasCa() {
        return $this->caPlBalance['kode_ca'] ?? [];
    }

    #[Computed]
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

    #[Computed]
    public function getKategoriPengeluaran() {
        $transaksi = collect(
            $this->transaksiCaPl ?? []
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

    /**
     * Palet warna "kantong" yang dirotasi per indeks, dipakai untuk kartu dompet
     * kegiatan & legenda breakdown kategori supaya satu sistem warna konsisten.
     *
     * @return array{tint: string, dot: string, text: string}
     */
    private function pocketTone(int $index): array
    {
        $palette = [
            ['tint' => 'bg-ca-pocket-1/10 dark:bg-ca-pocket-1/15 border-ca-pocket-1/20', 'dot' => 'bg-ca-pocket-1', 'text' => 'text-ca-pocket-1'],
            ['tint' => 'bg-ca-pocket-2/10 dark:bg-ca-pocket-2/15 border-ca-pocket-2/20', 'dot' => 'bg-ca-pocket-2', 'text' => 'text-ca-pocket-2'],
            ['tint' => 'bg-ca-pocket-3/10 dark:bg-ca-pocket-3/15 border-ca-pocket-3/20', 'dot' => 'bg-ca-pocket-3', 'text' => 'text-ca-pocket-3'],
            ['tint' => 'bg-ca-pocket-4/10 dark:bg-ca-pocket-4/15 border-ca-pocket-4/20', 'dot' => 'bg-ca-pocket-4', 'text' => 'text-ca-pocket-4'],
            ['tint' => 'bg-ca-pocket-5/10 dark:bg-ca-pocket-5/15 border-ca-pocket-5/20', 'dot' => 'bg-ca-pocket-5', 'text' => 'text-ca-pocket-5'],
        ];

        return $palette[$index % count($palette)];
    }

    /**
     * Ikon Flux untuk kategori transaksi; dipakai pada chip ikon baris transaksi.
     */
    private function iconForKategori(?string $kategori): string
    {
        return CaKategoriPengeluaran::iconForLabel($kategori);
    }

}; ?>

<div class="space-y-4">
    @if (!$this->hasCa())
    {{-- ================= ONBOARDING: BELUM MEMILIKI CA ================= --}}
    @php
        $onboardingSteps = [
            ['icon' => 'banknotes', 'title' => 'Ajukan top up', 'desc' => 'Isi nominal & bukti transfer'],
            ['icon' => 'clock', 'title' => 'Menunggu Verifikasi', 'desc' => 'Pengecekan data'],
            ['icon' => 'check-circle', 'title' => 'CA aktif', 'desc' => 'Dompet siap mencatat pengeluaran'],
        ];
    @endphp
    <div class="relative overflow-hidden rounded-2xl border-2 border-dashed border-ca-copper/25 bg-linear-to-b from-ca-copper/4 to-transparent dark:border-ca-copper/20 dark:from-ca-copper/6 dark:bg-zinc-900">

        <div class="relative mx-auto flex max-w-lg flex-col items-center gap-6 py-16 text-center">
            {{-- SIGNATURE: dompet kosong &mdash; koin menunggu untuk "dijatuhkan" ke dalamnya --}}
            <div class="relative flex h-24 w-24 items-center justify-center">
                <span class="absolute left-0 top-0 flex size-8 items-center justify-center rounded-full bg-ca-gold/20 text-[11px] font-bold text-ca-copper-deep shadow-sm animate-ca-coin-bob dark:bg-ca-gold/15 dark:text-ca-gold">Rp</span>
                <span class="absolute right-0 top-7 flex size-6 items-center justify-center rounded-full bg-ca-gold/20 text-[10px] font-bold text-ca-copper-deep shadow-sm animate-ca-coin-bob [animation-delay:0.8s] dark:bg-ca-gold/15 dark:text-ca-gold">Rp</span>

                <div class="flex size-16 items-center justify-center rounded-full border-2 border-dashed border-ca-copper/30 bg-ca-copper/10 dark:border-ca-copper/25 dark:bg-ca-copper/15">
                    <flux:icon.wallet class="size-8 text-ca-copper" />
                </div>
            </div>

            <div class="space-y-2">
                <flux:heading size="lg">Anda belum memiliki Cash Advance</flux:heading>
                <flux:text>
                    Lakukan top up pertama untuk membuka dompet CA PL Anda.
                    Setelah dana masuk, saldo, dompet kegiatan, dan riwayat transaksi akan tampil di halaman ini.
                </flux:text>
            </div>

            {{-- TRACK 3 LANGKAH: urutan proses nyata, jadi simpul yang terhubung, bukan kartu lepas --}}
            <div class="flex w-full items-start">
                @foreach ($onboardingSteps as $i => $step)
                <div class="flex flex-1 flex-col items-center gap-2 px-1">
                    <span @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-full transition',
                        'bg-ca-copper text-white animate-ca-pulse-ring' => $i === 0,
                        'border-2 border-dashed border-zinc-200 text-zinc-400 dark:border-zinc-700' => $i !== 0,
                    ])>
                        <flux:icon :name="$step['icon']" class="size-4" />
                    </span>
                    <div>
                        <p class="text-[11px] font-semibold text-zinc-900 dark:text-white">{{ $i + 1 }}. {{ $step['title'] }}</p>
                        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">{{ $step['desc'] }}</p>
                    </div>
                </div>
                @if (!$loop->last)
                <div class="mt-5 h-px flex-1 border-t-2 border-dashed border-zinc-200 dark:border-zinc-700"></div>
                @endif
                @endforeach
            </div>

            <flux:modal.trigger name="topup-pertama">
                <flux:button variant="primary" class="bg-ca-copper! transition-transform hover:-translate-y-0.5 hover:bg-ca-copper-deep!" icon="arrow-up-tray">Top up Pertama</flux:button>
            </flux:modal.trigger>
        </div>
    </div>

    <livewire:cashadvance.modals.topup-pertama />

    @if (! empty($this->caKegiatanBalance['data']))
    {{-- ================= DOMPET KEGIATAN: KOLABORATOR TANPA CA PL SENDIRI ================= --}}
    {{-- User ini belum punya CA PL sendiri, tapi jadi kolaborator di dompet kegiatan --}}
    {{-- orang lain — tetap tampilkan supaya dia tidak kehilangan akses. --}}
    <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
        <div class="space-y-3 p-5">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-zinc-900 dark:text-white">Dompet Kegiatan</h3>
                <span class="text-xs text-zinc-400">{{ $this->caKegiatanBalance['jumlah_dompet'] ?? 0 }} dompet</span>
            </div>

            <div class="no-scrollbar -mx-1 flex snap-x snap-mandatory gap-3 overflow-x-auto px-1 pb-1 lg:mx-0 lg:grid lg:grid-cols-4 lg:snap-none lg:overflow-visible lg:px-0 lg:pb-0">
                @foreach (collect($this->caKegiatanBalance['data'] ?? [])->take(8) as $i => $item)
                @php $tone = $this->pocketTone($i); @endphp
                @include('livewire.cashadvance.partials.kegiatan-card', ['item' => $item, 'tone' => $tone])
                @endforeach
            </div>
        </div>
    </div>
    @endif
    @else
    {{-- ================= TOP GRID ================= --}}
    <div class="grid grid-cols-1 lg:flex gap-4">

        {{-- ===== LEFT: MASTER WALLET CARD (saldo + chart menyatu) ===== --}}
        <div class="w-full lg:w-3/5 rounded-2xl bg-linear-to-br from-ca-ink-lift to-ca-ink p-6 text-white shadow-xl shadow-black/10">
            <div class="relative overflow-hidden">
                <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 24px);"></div>

                <div class="relative space-y-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-gold">CA PL &middot; Dompet Utama</p>
                            <p class="mt-1 text-xs text-white/50">{{ $this->caPlBalance['kode_ca'] ?? 'N/A' }} &middot; dibuka {{ Carbon\Carbon::parse($this->caPlBalance['created_at'])->locale('id')->translatedFormat('d M Y') }}</p>
                        </div>
                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">
                            <span class="size-1.5 rounded-full bg-emerald-400"></span> Aktif
                        </span>
                    </div>

                    <div>
                        <p class="text-xs text-white/50">Saldo aktif</p>
                        <h2 class="mt-1 text-4xl font-bold tabular-nums sm:text-5xl">
                            Rp {{ number_format($this->caPlBalance['saldo_akhir'] ?? 0, 0, ',', '.') }}
                        </h2>
                    </div>

                    {{-- TREN PENGELUARAN: sparkline dekoratif menyatu dengan kartu saldo. --}}
                    {{-- wire:key wajib: memaksa morph menyisipkan node fresh saat swap dari --}}
                    {{-- skeleton placeholder, agar Alpine menjalankan x-init & chart ter-render. --}}
                    <div wire:key="ca-chart-wrapper" wire:ignore x-data="{ chart: null }" x-init="
                            chart = new ApexCharts($refs.caChart, {
                                series: @js($this->getChart ?? []),
                                chart: { height: 140, type: 'area', toolbar: { show: false }, background: 'transparent' },
                                dataLabels: { enabled: false },
                                stroke: { curve: 'smooth', width: 3 },
                                colors: ['#D9A054'],
                                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0, stops: [0, 90, 100] } },
                                grid: { show: false, padding: { left: 0, right: 0, top: 0, bottom: 0 } },
                                xaxis: { type: 'category', categories: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'], labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
                                yaxis: { show: false },
                                tooltip: { theme: 'dark' },
                            });
                            chart.render();
                            Livewire.on('ca-chart-updated', (data) => {
                                chart.updateSeries(data.series);
                            });
                        ">
                        <div x-ref="caChart" class="-mx-2 h-32 w-[calc(100%+1rem)]"></div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <button type="button"
                            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-ca-copper px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ca-copper-deep focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ca-gold"
                            x-on:click="$flux.modal('catat-pengeluaran').show(); $wire.dispatch('ca-new-expense')">
                            <flux:icon name="minus-circle" class="size-4" />
                            Catat Pengeluaran
                        </button>
                        <flux:modal.trigger name="topup-laporan">
                            <button type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ca-gold">
                                <flux:icon name="arrow-up-tray" class="size-4" />
                                Top up
                            </button>
                        </flux:modal.trigger>
                    </div>

                    <div class="grid grid-cols-2 gap-4 border-t border-white/10 pt-4">
                        <div>
                            <p class="text-[11px] lg:text-md text-white/50">Masuk periode ini</p>
                            <p class="mt-0.5 text-sm lg:text-lg font-semibold tabular-nums text-emerald-300">
                                Rp {{ number_format($this->caPlBalance['saldo_awal_priode'] ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[11px] lg:text-md text-white/50">Keluar periode ini</p>
                            <p class="mt-0.5 text-sm lg:text-lg font-semibold tabular-nums text-orange-300">
                                Rp {{ number_format($this->caPlBalance['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===== RIGHT: DOMPET KEGIATAN + BREAKDOWN ===== --}}
        <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">

            {{-- DOMPET KEGIATAN (carousel kantong) --}}
            <div class="p-5 space-y-3 border-b border-zinc-200 dark:border-zinc-700">
                <div class="flex justify-between items-center">
                    <h3 class="font-semibold text-zinc-900 dark:text-white">Dompet Kegiatan</h3>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-zinc-400">{{ $this->caKegiatanBalance['jumlah_dompet'] ?? 0 }} dompet</span>
                        @if (($this->caKegiatanBalance['jumlah_dompet'] ?? 0) > 0)
                        <flux:modal.trigger name="semua-dompet-kegiatan">
                            <button type="button" class="text-xs font-medium text-ca-copper hover:text-ca-copper-deep">Lihat semua</button>
                        </flux:modal.trigger>
                        @endif
                    </div>
                </div>

                <div class="no-scrollbar -mx-1 flex snap-x snap-mandatory gap-3 overflow-x-auto px-1 pb-1 lg:mx-0 lg:grid lg:grid-cols-3 lg:snap-none lg:overflow-visible lg:px-0 lg:pb-0">
                    @forelse (collect($this->caKegiatanBalance['data'] ?? [])->take(5) as $i => $item)
                    @php $tone = $this->pocketTone($i); @endphp
                    @include('livewire.cashadvance.partials.kegiatan-card', ['item' => $item, 'tone' => $tone])
                    @empty
                    <p class="w-full py-6 text-center text-sm text-zinc-400">Belum ada dompet kegiatan.</p>
                    @endforelse

                    <flux:modal.trigger name="buat-kegiatan">
                        <button type="button"
                            class="flex w-36 shrink-0 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-zinc-200 p-3.5 text-zinc-400 snap-start transition hover:border-ca-copper hover:text-ca-copper dark:border-zinc-700 lg:w-auto">
                            <span class="flex size-8 items-center justify-center rounded-full border-2 border-current">
                                <flux:icon name="plus" class="size-4" />
                            </span>
                            <span class="text-xs font-semibold">Buat Dompet</span>
                        </button>
                    </flux:modal.trigger>
                </div>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-5 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori &mdash; periode aktif</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($this->caPlBalance['total_pengeluaran'] ?? 0, 0, ',', '.') }}
                </h3>
                @if (!empty($this->caPlBalance['total_pengeluaran']))
                <div class="h-2.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                    @foreach ($this->getKategoriPengeluaran as $i => $row)
                    <div class="{{ $this->pocketTone($i)['dot'] }}" style="width: {{ max($row['persentase'], 2) }}%" wire:key="bar-{{ $row['nama'] }}"></div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($this->getKategoriPengeluaran as $i => $row)
                    <p class="flex items-center gap-1.5" wire:key="legend-{{ $row['nama'] }}">
                        <span class="size-2 rounded-full {{ $this->pocketTone($i)['dot'] }}"></span>
                        {{ $row['nama'] }} &middot; {{ $row['persentase'] }}%
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
    <div class="rounded-2xl border border-zinc-200 dark:border-white/10 bg-white dark:bg-ca-ink">
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 dark:text-white/50">Transaksi Terbaru</p>
                <p class="mt-0.5 text-xs text-zinc-400 dark:text-white/40">{{ $this->periodeLabel() }}</p>
            </div>
            <div class="flex gap-2">
                <div class="flex-1 relative">
                    <flux:input wire:model="search" wire:keydown.enter="performSearch" icon="magnifying-glass" placeholder="Cari deskripsi / kategori (tekan Enter)" wire:loading.attr="disabled" wire:target="performSearch" />
                    <div wire:loading wire:target="performSearch" class="absolute right-3 top-1/2 -translate-y-1/2">
                        <div class="animate-spin h-5 w-5 border-2 border-zinc-300 border-t-zinc-900 dark:border-zinc-600 dark:border-t-white rounded-full"></div>
                    </div>
                </div>
                <flux:dropdown position="bottom" align="end">
                    <span class="relative inline-flex">
                        <flux:button icon="calendar" variant="outline" />
                        @if ($startDate || $endDate)
                        <span class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-ca-copper ring-2 ring-white dark:ring-ca-ink"></span>
                        @endif
                    </span>
                    <flux:menu class="w-56">
                        <flux:menu.item keep-open>
                            <flux:input wire:model.live="startDate" label="Dari tanggal" type="date" />
                        </flux:menu.item>
                        <flux:menu.item keep-open>
                            <flux:input wire:model.live="endDate" label="Sampai tanggal" type="date" />
                        </flux:menu.item>
                        <flux:menu.item wire:click="resetDateFilter">
                            <flux:button class="w-full" size="sm">Reset</flux:button>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:tooltip content="Unduh laporan transaksi periode aktif" placement="top">
                    <flux:button :href="route('cashadvance.laporan.capl', ['kodeCa' => $this->caPlBalance['kode_ca']])" target="_blank" icon="document-arrow-down" variant="ghost" class="text-ca-copper! hover:text-ca-copper-deep!">
                    </flux:button>
                </flux:tooltip>
            </div>
        </div>

        <div wire:loading.class="opacity-50" wire:target="performSearch" class="divide-y divide-zinc-100 dark:divide-white/10 border-t border-zinc-100 dark:border-white/10">
            @forelse ($this->transaksiCaPl ?? [] as $trx)
            @php $isIn = ($trx['jenis'] ?? null) === 'pemasukan'; @endphp
            <div wire:key="trx-{{ $trx['id_transaksi'] }}" class="flex items-center gap-3 px-4 py-3 md:px-6">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $isIn ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper' }}">
                    <flux:icon :name="$isIn ? 'banknotes' : $this->iconForKategori($trx['kategori'] ?? null)" class="size-5" />
                </span>

                {{-- Tanggal: kolom tersendiri di sm+, digabung ke meta deskripsi di mobile --}}
                <div class="hidden w-24 shrink-0 text-sm text-zinc-500 dark:text-white/50 sm:block">
                    {{ \Carbon\Carbon::parse($trx['tanggal'])->translatedFormat('d M Y') }}
                </div>

                {{-- Kategori: kolom tersendiri di sm+ --}}
                <div class="hidden shrink-0 sm:block">
                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-white/10 dark:text-white/70">
                        {{ $isIn ? 'Pemasukan' : ($trx['kategori'] ?? '-') }}
                    </span>
                </div>

                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $trx['deskripsi'] }}</p>
                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 sm:hidden">
                        <span class="shrink-0 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-600 dark:bg-white/10 dark:text-white/70">
                            {{ $isIn ? 'Pemasukan' : ($trx['kategori'] ?? '-') }}
                        </span>
                        <span class="shrink-0 whitespace-nowrap text-xs text-zinc-400 dark:text-white/40">{{ \Carbon\Carbon::parse($trx['created_at'])->translatedFormat('d M Y') }}</span>
                    </div>
                </div>

                {{-- Pengeluaran + sisa saldo. Saldo disembunyikan di mobile (bukan --}}
                {{-- di-nowrap) supaya kolom ini tidak jadi lebar & menyempitkan --}}
                {{-- ruang judul — baris "Saldo Rp ..." adalah string terpanjang di --}}
                {{-- sini, jadi ini yang paling memakan lebar pada layar sempit. --}}
                <div class="shrink-0 text-right">
                    <p class="whitespace-nowrap text-sm font-semibold tabular-nums {{ $isIn ? 'text-emerald-600 dark:text-emerald-300' : 'text-ca-copper-deep dark:text-ca-copper' }}">
                        {{ $isIn ? '+' : '−' }} Rp {{ number_format($trx['jumlah'], 0, ',', '.') }}
                    </p>
                    <p class="hidden whitespace-nowrap text-xs text-zinc-400 dark:text-white/40 sm:block">
                        Saldo Rp {{ number_format($trx['saldo_setelah'], 0, ',', '.') }}
                    </p>
                </div>

                <flux:dropdown position="bottom" align="end">
                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                    <flux:menu>
                        @if ($trx['bukti_url'] ?? false)
                        <flux:menu.item icon="paper-clip"
                            x-on:click="$dispatch('preview-bukti', {!! \Illuminate\Support\Js::from(['url' => $trx['bukti_url'], 'title' => $trx['deskripsi'] ?? 'Bukti Transaksi']) !!}); $flux.modal('bukti-preview').show()">
                            Lihat Bukti
                        </flux:menu.item>
                        @endif
                        <flux:menu.item icon="pencil-square"
                            x-on:click="$flux.modal('catat-pengeluaran').show(); $wire.dispatch('ca-edit-expense', { id: {{ $trx['id_transaksi'] }} })">
                            Edit
                        </flux:menu.item>
                        <flux:menu.item icon="trash" variant="danger"
                            x-on:click="$flux.modal('hapus-pengeluaran').show(); $wire.dispatch('ca-confirm-delete', {!! \Illuminate\Support\Js::from(['id' => $trx['id_transaksi'], 'jumlah' => $trx['jumlah'], 'kategori' => $trx['kategori'] ?? null, 'deskripsi' => $trx['deskripsi'] ?? null, 'tanggal' => $trx['tanggal'] ?? null]) !!})">
                            Hapus
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
            @empty
            <div class="px-6 py-10 text-center text-sm text-zinc-400">Belum ada transaksi pada periode aktif</div>
            @endforelse
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
    <flux:modal name="bukti-preview" class="w-full max-w-2xl"
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
    @endif
</div>
