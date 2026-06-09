<?php

use App\Services\CaDummy;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    /** @var array<string, mixed> Dompet CA PL + periode aktif */
    public array $wallet = [];

    /** @var array<int, array<string, mixed>> Transaksi periode aktif CA PL */
    public array $transactions = [];

    /** @var array<int, array<string, mixed>> Dompet kegiatan */
    public array $dompetKegiatan = [];

    /** @var array<int, array<string, mixed>> Riwayat periode CA PL yang sudah disegel */
    public array $sealedPeriods = [];

    /** @var array<int, string> */
    public array $categories = [];

    /** Id transaksi yang sedang diedit (null = mode tambah). */
    public ?int $editingId = null;

    public string $search = '';

    public ?string $start_date = null;

    public ?string $end_date = null;

    // --- Form: Catat Pengeluaran (arah selalu keluar) ---
    #[Validate('required|numeric|min:1')]
    public ?string $amount = null;

    #[Validate('required|string')]
    public string $category = '';

    #[Validate('required|string|max:255')]
    public string $description = '';

    #[Validate('required|date')]
    public string $date = '';

    #[Validate('required|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $bukti = null;

    // --- Form: Buat dompet kegiatan ---
    #[Validate('required|string|max:255')]
    public string $keg_name = '';

    #[Validate('nullable|numeric|min:0')]
    public ?string $keg_rab = null;

    #[Validate('required|numeric|min:1')]
    public ?string $keg_dana = null;

    #[Validate('required|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $keg_bukti = null;

    public function mount(CaDummy $dummy): void
    {
        $this->wallet = $dummy->walletPl();
        $this->transactions = $dummy->periodTransactions();
        $this->dompetKegiatan = $dummy->dompetKegiatan();
        $this->sealedPeriods = $dummy->sealedPeriods();
        $this->categories = $dummy->categories();
        $this->date = now()->toDateString();
    }

    /**
     * Transaksi periode aktif dengan saldo berjalan (saldo_setelah) terhitung
     * dari opening balance. Diurutkan kronologis menaik.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function decoratedTransactions(): array
    {
        $running = (int) ($this->wallet['period']['opening_balance'] ?? 0);

        return collect($this->transactions)
            ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
            ->map(function (array $trx) use (&$running): array {
                $running += ($trx['direction'] === 'masuk' ? (int) $trx['amount'] : -(int) $trx['amount']);
                $trx['saldo_setelah'] = $running;

                return $trx;
            })
            ->values()
            ->all();
    }

    /** Saldo berjalan periode aktif. */
    #[Computed]
    public function saldo(): int
    {
        $rows = $this->decoratedTransactions();

        return empty($rows)
            ? (int) ($this->wallet['period']['opening_balance'] ?? 0)
            : (int) end($rows)['saldo_setelah'];
    }

    /** Total pengeluaran periode aktif. */
    #[Computed]
    public function totalKeluar(): int
    {
        return (int) collect($this->transactions)->where('direction', 'keluar')->sum('amount');
    }

    /**
     * Breakdown pengeluaran per kategori periode aktif (pengganti blok "savings").
     *
     * @return array<int, array{label: string, amount: int, percent: float}>
     */
    #[Computed]
    public function kategoriBreakdown(): array
    {
        $total = $this->totalKeluar();

        return collect($this->transactions)
            ->where('direction', 'keluar')
            ->groupBy('category')
            ->map(fn ($items, $label): array => [
                'label' => (string) $label,
                'amount' => (int) $items->sum('amount'),
                'percent' => $total > 0 ? round($items->sum('amount') / $total * 100, 1) : 0.0,
            ])
            ->sortByDesc('amount')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function filteredTransactions(): array
    {
        return collect($this->decoratedTransactions())
            ->when($this->search !== '', fn ($items) => $items->filter(
                fn (array $trx): bool => str_contains(strtolower($trx['description'] ?? ''), strtolower($this->search))
                    || str_contains(strtolower($trx['category'] ?? ''), strtolower($this->search))
            ))
            ->when($this->start_date, fn ($items) => $items->filter(fn (array $trx): bool => $trx['transaction_date'] >= $this->start_date))
            ->when($this->end_date, fn ($items) => $items->filter(fn (array $trx): bool => $trx['transaction_date'] <= $this->end_date))
            ->sortByDesc([['transaction_date', 'desc'], ['id', 'desc']])
            ->values()
            ->all();
    }

    public function isNeedsTopup(): bool
    {
        return ($this->wallet['status'] ?? null) === 'needs_topup';
    }

    public function resetDate(): void
    {
        $this->reset(['start_date', 'end_date']);
    }

    /** Buka modal untuk pengeluaran baru (reset state edit). */
    public function newExpense(): void
    {
        $this->resetExpenseForm();
        Flux::modal('catat-pengeluaran')->show();
    }

    /** Muat transaksi ke form lalu buka modal dalam mode edit. */
    public function editTransaction(int $id): void
    {
        $trx = collect($this->transactions)->firstWhere('id', $id);

        if ($trx === null) {
            return;
        }

        $this->editingId = $id;
        $this->amount = (string) $trx['amount'];
        $this->category = $trx['category'];
        $this->description = $trx['description'];
        $this->date = $trx['transaction_date'];
        $this->bukti = null;
        $this->resetValidation();

        Flux::modal('catat-pengeluaran')->show();
    }

    /** Hapus transaksi (prototype: hard delete; backend asli pakai entri pembalik). */
    public function deleteTransaction(int $id): void
    {
        $this->transactions = collect($this->transactions)->reject(fn (array $t): bool => $t['id'] === $id)->values()->all();

        $this->clearLedgerCache();
        $this->wallet['status'] = $this->saldo() < 0 ? 'needs_topup' : 'active';

        Toaster::success('Transaksi dihapus.');
        $this->dispatch('ca-chart-updated', income: $this->chartIncome(), expense: $this->chartExpense());
    }

    /**
     * Catat / perbarui pengeluaran CA PL. Arah terkunci `keluar`. Bukti wajib saat membuat baru.
     * Jika saldo menembus negatif -> wallet jadi needs_topup (blokir pengeluaran berikutnya).
     */
    public function recordExpense(): void
    {
        if ($this->editingId === null && $this->isNeedsTopup()) {
            Toaster::error('Saldo negatif, wajib top up terlebih dahulu.');

            return;
        }

        $this->validate([
            'amount' => 'required|numeric|min:1',
            'category' => 'required|string',
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'bukti' => ($this->editingId === null ? 'nullable' : 'nullable').'|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($this->editingId !== null) {
            $this->transactions = collect($this->transactions)->map(function (array $t): array {
                if ($t['id'] === $this->editingId) {
                    $t['amount'] = (int) $this->amount;
                    $t['category'] = $this->category;
                    $t['description'] = $this->description;
                    $t['transaction_date'] = $this->date;
                    $t['has_bukti'] = $this->bukti !== null ? true : $t['has_bukti'];
                }

                return $t;
            })->all();
        } else {
            $this->transactions[] = [
                'id' => (int) (collect($this->transactions)->max('id') ?? 100) + 1,
                'direction' => 'keluar',
                'amount' => (int) $this->amount,
                'category' => $this->category,
                'description' => $this->description,
                'transaction_date' => $this->date,
                'has_bukti' => true,
            ];
        }

        $this->clearLedgerCache();

        $isEdit = $this->editingId !== null;
        $this->wallet['status'] = $this->saldo() < 0 ? 'needs_topup' : 'active';

        if ($this->saldo() < 0) {
            Toaster::warning('Tersimpan. Saldo negatif — pengeluaran berikutnya diblokir sampai top up.');
        } else {
            Toaster::success($isEdit ? 'Pengeluaran diperbarui.' : 'Pengeluaran berhasil dicatat.');
        }

        $this->resetExpenseForm();
        Flux::modal('catat-pengeluaran')->close();
        $this->dispatch('ca-chart-updated', income: $this->chartIncome(), expense: $this->chartExpense());
    }

    /**
     * Eksekusi top up: segel periode aktif, buka periode baru opening 2 juta, wallet active.
     * (Prototype: state in-memory; closing balance boleh negatif diserap topup.)
     */
    public function topup(): void
    {
        // Segel periode aktif -> masuk riwayat (read-only).
        array_unshift($this->sealedPeriods, [
            'sequence_no' => (int) $this->wallet['period']['sequence_no'],
            'opening_balance' => (int) $this->wallet['period']['opening_balance'],
            'closing_balance' => $this->saldo(),
            'total_keluar' => $this->totalKeluar(),
            'opened_at' => $this->wallet['period']['opened_at'],
            'sealed_at' => now()->toDateString(),
        ]);

        $this->wallet['period']['sequence_no'] = (int) ($this->wallet['period']['sequence_no'] ?? 1) + 1;
        $this->wallet['period']['opening_balance'] = CaDummy::IMPREST;
        $this->wallet['period']['opened_at'] = now()->toDateString();
        $this->wallet['status'] = 'active';
        $this->transactions = [];

        $this->clearLedgerCache();

        Toaster::success('Top up berhasil. Periode #'.$this->wallet['period']['sequence_no'].' dibuka dengan saldo Rp '.number_format(CaDummy::IMPREST, 0, ',', '.').'.');
        Flux::modal('topup-laporan')->close();
        $this->dispatch('ca-chart-updated', income: $this->chartIncome(), expense: $this->chartExpense());
    }

    /** Buat dompet kegiatan baru + catat dana cair sebagai transaksi masuk. */
    public function createKegiatan(): void
    {
        $this->validate([
            'keg_name' => 'required|string|max:255',
            'keg_rab' => 'nullable|numeric|min:0',
            'keg_dana' => 'required|numeric|min:1',
            'keg_bukti' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $next = (int) (collect($this->dompetKegiatan)->max('id') ?? 10) + 1;

        $this->dompetKegiatan[] = [
            'id' => $next,
            'type' => 'ca_kegiatan',
            'kode' => 'KEG-'.str_pad((string) $next, 3, '0', STR_PAD_LEFT),
            'name' => $this->keg_name,
            'status' => 'active',
            'rab_amount' => $this->keg_rab !== null ? (int) $this->keg_rab : null,
            'current_balance' => (int) $this->keg_dana,
            'opened_at' => now()->toDateString(),
        ];

        $this->reset(['keg_name', 'keg_rab', 'keg_dana', 'keg_bukti']);
        Toaster::success('Dompet kegiatan berhasil dibuat.');
        Flux::modal('buat-kegiatan')->close();
    }

    private function resetExpenseForm(): void
    {
        $this->reset(['amount', 'category', 'description', 'bukti', 'editingId']);
        $this->date = now()->toDateString();
        $this->resetValidation();
    }

    private function clearLedgerCache(): void
    {
        unset($this->decoratedTransactions, $this->saldo, $this->totalKeluar, $this->kategoriBreakdown, $this->filteredTransactions);
    }

    /** @return array<int, int> Total pengeluaran per bulan (Jan..Des). */
    public function chartExpense(): array
    {
        $expense = array_fill(0, 12, 0);

        foreach ($this->transactions as $trx) {
            if (($trx['direction'] ?? null) !== 'keluar') {
                continue;
            }
            $m = (int) date('n', strtotime($trx['transaction_date'])) - 1;
            $expense[$m] += (int) $trx['amount'];
        }

        return array_values($expense);
    }

    /** @return array<int, int> Income per bulan (opening pada bulan dibukanya periode). */
    public function chartIncome(): array
    {
        $income = array_fill(0, 12, 0);
        $m = (int) date('n', strtotime($this->wallet['period']['opened_at'] ?? now())) - 1;
        $income[$m] += (int) ($this->wallet['period']['opening_balance'] ?? 0);

        return array_values($income);
    }
}; ?>

<div class="space-y-4" wire:loading.class="opacity-60">

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
                            @if ($this->isNeedsTopup())
                                <flux:badge size="sm" color="red">needs topup</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">active</flux:badge>
                            @endif
                            <flux:badge size="sm" color="zinc">Periode #{{ $wallet['period']['sequence_no'] }}</flux:badge>
                        </div>

                        <h2 class="text-4xl font-semibold tabular-nums {{ $this->saldo() < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                            Rp {{ number_format($this->saldo(), 0, ',', '.') }}
                        </h2>

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $wallet['name'] }} • Float Rp {{ number_format($wallet['imprest_amount'], 0, ',', '.') }}
                            • Dibuka {{ \Carbon\Carbon::parse($wallet['period']['opened_at'])->translatedFormat('d M Y') }}
                        </p>
                    </div>

                    <div class="flex gap-2">
                        @if ($this->isNeedsTopup())
                            <flux:tooltip content="Saldo negatif, wajib top up dulu">
                                <flux:button size="sm" variant="primary" icon="minus-circle" disabled>
                                    Catat Pengeluaran
                                </flux:button>
                            </flux:tooltip>
                        @else
                            <flux:button size="sm" variant="primary" icon="minus-circle" wire:click="newExpense">
                                Catat Pengeluaran
                            </flux:button>
                        @endif

                        <flux:modal.trigger name="topup-laporan">
                            <flux:button size="sm" variant="outline" icon="arrow-up-tray">Top up</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                {{-- CHART (Alpine x-init: reliable di komponen lazy) --}}
                <div wire:ignore
                    x-data="{ chart: null }"
                    x-init="
                        chart = new ApexCharts($refs.caChart, {
                            series: [
                                { name: 'Income', data: @js($this->chartIncome()) },
                                { name: 'Expense', data: @js($this->chartExpense()) },
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
                        $wire.on('ca-chart-updated', (e) => chart.updateSeries([
                            { name: 'Income', data: e.income },
                            { name: 'Expense', data: e.expense },
                        ]));
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
                        Rp {{ number_format($wallet['period']['opening_balance'], 0, ',', '.') }}
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Saldo awal periode aktif</p>
                </div>

                <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Expense</p>
                        <flux:badge size="sm" color="red">Pengeluaran</flux:badge>
                    </div>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">
                        Rp {{ number_format($this->totalKeluar(), 0, ',', '.') }}
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
                    <span class="text-sm text-zinc-400">{{ count($dompetKegiatan) }} dompet</span>
                </div>

                @forelse (collect($dompetKegiatan)->take(4) as $item)
                    <a wire:key="dompet-{{ $item['kode'] }}"
                        href="{{ route('cashadvance.dompet-show', $item['kode']) }}" wire:navigate
                        class="flex items-center justify-between -mx-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <flux:avatar :name="$item['name']" color="{{ $item['status'] === 'closed' ? 'zinc' : 'blue' }}" size="sm" />
                            <div class="text-left min-w-0">
                                <p class="text-sm font-medium truncate text-zinc-900 dark:text-white">{{ $item['name'] }}</p>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs text-zinc-500">{{ $item['kode'] }}</span>
                                    @if ($item['status'] === 'closed')
                                        <flux:badge size="sm" color="zinc">closed</flux:badge>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <p class="text-sm font-medium tabular-nums {{ $item['current_balance'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                            Rp {{ number_format($item['current_balance'], 0, ',', '.') }}
                        </p>
                    </a>
                @empty
                    <p class="text-sm text-zinc-400 text-center py-2">Belum ada dompet kegiatan</p>
                @endforelse

                <flux:modal.trigger name="buat-kegiatan">
                    <flux:button variant="outline" class="w-full" icon="plus">Create new card</flux:button>
                </flux:modal.trigger>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori — periode aktif</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($this->totalKeluar(), 0, ',', '.') }}
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
                @if (! empty($this->kategoriBreakdown()))
                    <div class="h-10 rounded bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                        @foreach ($this->kategoriBreakdown() as $i => $row)
                            @php $c = $palette[$i % count($palette)]; @endphp
                            <div class="{{ $c['bg'] }} border-l-2 {{ $c['border'] }}"
                                style="width: {{ max($row['percent'], 2) }}%"
                                wire:key="bar-{{ $row['label'] }}"></div>
                        @endforeach
                    </div>

                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                        @foreach ($this->kategoriBreakdown() as $i => $row)
                            <p class="border-l-2 ps-2 {{ $palette[$i % count($palette)]['border'] }}" wire:key="legend-{{ $row['label'] }}">
                                {{ $row['label'] }} • {{ $row['percent'] }}%
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
                <flux:text class="text-xs">Periode #{{ $wallet['period']['sequence_no'] }} (aktif) saja</flux:text>
            </div>
            <div class="flex gap-2">
                <flux:input icon="magnifying-glass" wire:model.live.debounce.300ms="search" placeholder="Cari deskripsi / kategori" />
                <flux:dropdown position="bottom" align="end">
                    <flux:button icon="calendar" variant="outline" />
                    <flux:menu>
                        <flux:menu.item disabled>
                            <flux:input label="Start Date" wire:model.live="start_date" type="date" />
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input label="End Date" wire:model.live="end_date" type="date" />
                        </flux:menu.item>
                        <flux:menu.item>
                            <flux:button class="w-full" size="sm" wire:click="resetDate">Reset</flux:button>
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
                    @forelse ($this->filteredTransactions() as $trx)
                        <tr wire:key="trx-{{ $trx['id'] }}" class="border-t border-zinc-100 dark:border-zinc-800">
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap text-zinc-900 dark:text-white">{{ $trx['description'] }}</td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $trx['category'] }}</td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ \Carbon\Carbon::parse($trx['transaction_date'])->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                                <flux:badge size="sm" :color="$trx['direction'] === 'masuk' ? 'green' : 'red'">
                                    {{ ucfirst($trx['direction']) }}
                                </flux:badge>
                            </td>
                            <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap tabular-nums {{ $trx['direction'] === 'masuk' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $trx['direction'] === 'masuk' ? '+' : '−' }} Rp {{ number_format($trx['amount'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 md:px-6 text-right whitespace-nowrap tabular-nums {{ $trx['saldo_setelah'] < 0 ? 'text-red-600' : '' }}">
                                Rp {{ number_format($trx['saldo_setelah'], 0, ',', '.') }}
                            </td>
                            <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                                @if ($trx['has_bukti'])
                                    <flux:button size="sm" variant="ghost" icon="paper-clip" />
                                @else
                                    <span class="text-zinc-300">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editTransaction({{ $trx['id'] }})" />
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600"
                                        wire:click="deleteTransaction({{ $trx['id'] }})"
                                        wire:confirm="Hapus transaksi ini?" />
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

    {{-- ================= MODAL: CATAT PENGELUARAN ================= --}}
    <flux:modal name="catat-pengeluaran" class="w-lg">
        <form wire:submit="recordExpense" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingId ? 'Edit Pengeluaran' : 'Catat Pengeluaran' }}</flux:heading>
                <flux:text class="mt-1">Arah transaksi terkunci sebagai pengeluaran (keluar).</flux:text>
            </div>

            <flux:input type="number" label="Nominal" prefix="Rp" wire:model="amount" placeholder="0" />
            <flux:select label="Kategori" wire:model="category" placeholder="Pilih kategori">
                @foreach ($categories as $cat)
                    <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:textarea label="Deskripsi" wire:model="description" placeholder="Keterangan pengeluaran" rows="2" />
            <flux:input type="date" label="Tanggal" wire:model="date" />
            <flux:input type="file"
                label="{{ $editingId ? 'Ganti Bukti (opsional — jpg, png, pdf)' : 'Bukti (wajib — jpg, png, pdf)' }}"
                wire:model="bukti" />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="recordExpense,bukti">
                <span wire:loading.remove wire:target="recordExpense">{{ $editingId ? 'Perbarui' : 'Simpan Pengeluaran' }}</span>
                <span wire:loading wire:target="recordExpense">Menyimpan...</span>
            </flux:button>
        </form>
    </flux:modal>

    {{-- ================= MODAL: TOP UP / LAPORAN PERIODE ================= --}}
    <flux:modal name="topup-laporan" class="w-xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Laporan Periode #{{ $wallet['period']['sequence_no'] }}</flux:heading>
                <flux:text class="mt-1">Rekap pertanggungjawaban sebelum top up. Saldo akan direstore ke Rp {{ number_format($wallet['imprest_amount'], 0, ',', '.') }}.</flux:text>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <p class="text-xs text-zinc-500">Total Pengeluaran</p>
                    <p class="text-lg font-semibold tabular-nums text-red-600">Rp {{ number_format($this->totalKeluar(), 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <p class="text-xs text-zinc-500">Closing Balance</p>
                    <p class="text-lg font-semibold tabular-nums {{ $this->saldo() < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                        Rp {{ number_format($this->saldo(), 0, ',', '.') }}
                    </p>
                </div>
            </div>

            @if ($this->saldo() < 0)
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.heading>Saldo negatif (talangan PL)</flux:callout.heading>
                    <flux:callout.text>Nominal Rp {{ number_format(abs($this->saldo()), 0, ',', '.') }} akan otomatis dilunasi saat top up.</flux:callout.text>
                </flux:callout>
            @endif

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
                <span class="text-sm text-zinc-500">Top up untuk restore ke float</span>
                <span class="text-lg font-semibold tabular-nums text-green-600">
                    Rp {{ number_format($wallet['imprest_amount'] - $this->saldo(), 0, ',', '.') }}
                </span>
            </div>

            <div class="flex justify-between gap-2">
                <flux:button :href="route('cashadvance.laporan.capl')" target="_blank" variant="ghost" icon="document-arrow-down">
                    Lihat Laporan PDF
                </flux:button>
                <div class="flex gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="primary" icon="check" wire:click="topup" wire:loading.attr="disabled" wire:target="topup">
                        Verifikasi & Eksekusi Top up
                    </flux:button>
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
                @forelse ($sealedPeriods as $p)
                    <div wire:key="seal-{{ $p['sequence_no'] }}"
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-900 dark:text-white">Periode #{{ $p['sequence_no'] }}</p>
                                <flux:badge size="sm" color="zinc">sealed</flux:badge>
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                {{ \Carbon\Carbon::parse($p['opened_at'])->translatedFormat('d M Y') }}
                                – {{ \Carbon\Carbon::parse($p['sealed_at'])->translatedFormat('d M Y') }}
                            </p>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                • Keluar Rp {{ number_format($p['total_keluar'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium tabular-nums {{ $p['closing_balance'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                                Rp {{ number_format($p['closing_balance'], 0, ',', '.') }}
                            </span>
                            <flux:button :href="route('cashadvance.laporan.capl', $p['sequence_no'])" target="_blank"
                                size="sm" variant="outline" icon="document-arrow-down">Laporan</flux:button>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-zinc-400 text-center py-4">Belum ada periode tersegel.</p>
                @endforelse
            </div>
        </div>
    </flux:modal>

    {{-- ================= MODAL: BUAT DOMPET KEGIATAN ================= --}}
    <flux:modal name="buat-kegiatan" class="w-lg">
        <form wire:submit="createKegiatan" class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Dompet Kegiatan</flux:heading>
                <flux:text class="mt-1">Dana cair dicatat sebagai transaksi masuk pertama.</flux:text>
            </div>

            <flux:input label="Nama Kegiatan" wire:model="keg_name" placeholder="mis. Survei Lokasi Bandung" />
            <flux:input type="number" label="RAB (opsional)" prefix="Rp" wire:model="keg_rab" placeholder="0" />
            <flux:input type="number" label="Dana Cair" prefix="Rp" wire:model="keg_dana" placeholder="0" />
            <flux:input type="file" label="Bukti pencairan (wajib — jpg, png, pdf)" wire:model="keg_bukti" />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="createKegiatan,keg_bukti">
                <span wire:loading.remove wire:target="createKegiatan">Buat Dompet</span>
                <span wire:loading wire:target="createKegiatan">Menyimpan...</span>
            </flux:button>
        </form>
    </flux:modal>
</div>
