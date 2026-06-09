<?php

use App\Services\CaDummy;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Detail Dompet'])]
class extends Component {
    use WithFileUploads;

    public string $kodeCa = '';

    /** @var array<string, mixed> Info dompet kegiatan */
    public array $dompet = [];

    /** @var array<int, array<string, mixed>> Transaksi dompet (sepanjang umur, satu periode) */
    public array $transactions = [];

    /** @var array<int, string> */
    public array $categories = [];

    // --- Form: Catat Transaksi (kegiatan boleh masuk/keluar) ---
    #[Validate('required|in:masuk,keluar')]
    public string $direction = 'keluar';

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

    // --- Settlement ---
    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $settle_bukti = null;

    /** Id transaksi yang sedang diedit (null = mode tambah). */
    public ?int $editingId = null;

    public function mount(string $kodeCa, CaDummy $dummy): void
    {
        $this->kodeCa = $kodeCa;
        $this->dompet = $dummy->kegiatanByKode($kodeCa);
        $this->transactions = $dummy->kegiatanTransactions($kodeCa);
        $this->categories = $dummy->categories();
        $this->date = now()->toDateString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function decoratedTransactions(): array
    {
        $running = 0;

        return collect($this->transactions)
            ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
            ->map(function (array $trx) use (&$running): array {
                $running += ($trx['direction'] === 'masuk' ? (int) $trx['amount'] : -(int) $trx['amount']);
                $trx['saldo_setelah'] = $running;

                return $trx;
            })
            ->sortByDesc([['transaction_date', 'desc'], ['id', 'desc']])
            ->values()
            ->all();
    }

    #[Computed]
    public function saldo(): int
    {
        return (int) collect($this->transactions)->sum(fn (array $t): int => $t['direction'] === 'masuk' ? (int) $t['amount'] : -(int) $t['amount']);
    }

    #[Computed]
    public function totalMasuk(): int
    {
        return (int) collect($this->transactions)->where('direction', 'masuk')->sum('amount');
    }

    #[Computed]
    public function totalKeluar(): int
    {
        return (int) collect($this->transactions)->where('direction', 'keluar')->sum('amount');
    }

    public function isClosed(): bool
    {
        return ($this->dompet['status'] ?? null) === 'closed';
    }

    public function newTransaksi(): void
    {
        $this->resetForm();
        Flux::modal('catat-transaksi')->show();
    }

    public function editTransaksi(int $id): void
    {
        $trx = collect($this->transactions)->firstWhere('id', $id);

        if ($trx === null) {
            return;
        }

        $this->editingId = $id;
        $this->direction = $trx['direction'];
        $this->amount = (string) $trx['amount'];
        $this->category = $trx['category'];
        $this->description = $trx['description'];
        $this->date = $trx['transaction_date'];
        $this->bukti = null;
        $this->resetValidation();

        Flux::modal('catat-transaksi')->show();
    }

    public function hapusTransaksi(int $id): void
    {
        if ($this->isClosed()) {
            return;
        }

        $this->transactions = collect($this->transactions)->reject(fn (array $t): bool => $t['id'] === $id)->values()->all();
        unset($this->decoratedTransactions, $this->saldo, $this->totalMasuk, $this->totalKeluar);

        Toaster::success('Transaksi dihapus.');
    }

    public function catatTransaksi(): void
    {
        if ($this->isClosed()) {
            Toaster::error('Dompet sudah ditutup (closed) — tidak menerima transaksi lagi.');

            return;
        }

        $this->validate([
            'direction' => 'required|in:masuk,keluar',
            'amount' => 'required|numeric|min:1',
            'category' => 'required|string',
            'description' => 'required|string|max:255',
            'date' => 'required|date',
            'bukti' => ($this->editingId === null ? 'required' : 'nullable').'|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        if ($this->editingId !== null) {
            $this->transactions = collect($this->transactions)->map(function (array $t): array {
                if ($t['id'] === $this->editingId) {
                    $t['direction'] = $this->direction;
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
                'id' => (int) (collect($this->transactions)->max('id') ?? 200) + 1,
                'direction' => $this->direction,
                'amount' => (int) $this->amount,
                'category' => $this->category,
                'description' => $this->description,
                'transaction_date' => $this->date,
                'has_bukti' => true,
            ];
        }

        unset($this->decoratedTransactions, $this->saldo, $this->totalMasuk, $this->totalKeluar);

        $isEdit = $this->editingId !== null;
        $this->resetForm();

        Toaster::success($isEdit ? 'Transaksi diperbarui.' : 'Transaksi berhasil dicatat.');
        Flux::modal('catat-transaksi')->close();
    }

    private function resetForm(): void
    {
        $this->reset(['amount', 'category', 'description', 'bukti', 'editingId']);
        $this->direction = 'keluar';
        $this->date = now()->toDateString();
        $this->resetValidation();
    }

    /**
     * Settlement (tutup kegiatan) — 3 cabang berdasarkan saldo akhir:
     *  > 0  : setor balik sisa (transaksi keluar) -> saldo 0
     *  = 0  : langsung tutup
     *  < 0  : negatif dibiarkan, jadi reimbursement (snapshot)
     */
    public function settle(): void
    {
        if ($this->isClosed()) {
            return;
        }

        $saldo = $this->saldo();

        if ($saldo > 0) {
            $this->transactions[] = [
                'id' => (int) (collect($this->transactions)->max('id') ?? 200) + 1,
                'direction' => 'keluar',
                'amount' => $saldo,
                'category' => 'Setor Balik',
                'description' => 'Setor balik sisa dana kegiatan',
                'transaction_date' => now()->toDateString(),
                'has_bukti' => $this->settle_bukti !== null,
            ];
            Toaster::success('Sisa Rp '.number_format($saldo, 0, ',', '.').' disetor balik. Kegiatan ditutup.');
        } elseif ($saldo < 0) {
            $this->dompet['reimbursement'] = abs($saldo);
            Toaster::warning('Saldo negatif Rp '.number_format(abs($saldo), 0, ',', '.').' dicatat sebagai reimbursement. Kegiatan ditutup.');
        } else {
            Toaster::success('Saldo nol. Kegiatan ditutup.');
        }

        $this->dompet['status'] = 'closed';
        $this->dompet['closed_at'] = now()->toDateString();

        unset($this->decoratedTransactions, $this->saldo, $this->totalMasuk, $this->totalKeluar);

        $this->reset(['settle_bukti']);
        Flux::modal('tutup-kegiatan')->close();
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
                        <flux:heading class="font-bold text-xl">{{ $dompet['name'] ?? $kodeCa }}</flux:heading>
                        @if ($this->isClosed())
                            <flux:badge size="sm" color="zinc">closed</flux:badge>
                        @else
                            <flux:badge size="sm" color="green">active</flux:badge>
                        @endif
                    </div>
                    <flux:text>
                        {{ $kodeCa }}
                        @if (! empty($dompet['rab_amount']))
                            • RAB Rp {{ number_format($dompet['rab_amount'], 0, ',', '.') }}
                        @endif
                    </flux:text>
                </div>
            </div>

            @if (! empty($dompet))
                <div class="flex flex-wrap gap-2">
                    @if (! $this->isClosed())
                        <flux:button variant="primary" icon="plus" size="sm" wire:click="newTransaksi">Catat Transaksi</flux:button>
                        <flux:modal.trigger name="tutup-kegiatan">
                            <flux:button variant="outline" icon="lock-closed" size="sm">Tutup Kegiatan</flux:button>
                        </flux:modal.trigger>
                    @endif
                    <flux:button :href="route('cashadvance.laporan.kegiatan', $kodeCa)" target="_blank"
                        variant="outline" icon="document-arrow-down" size="sm">Export PDF</flux:button>
                </div>
            @endif
        </div>

        @if (empty($dompet))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <flux:callout.heading>Dompet tidak ditemukan</flux:callout.heading>
            </flux:callout>
        @else
            {{-- REIMBURSEMENT SNAPSHOT --}}
            @if (! empty($dompet['reimbursement']))
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.heading>Reimbursement</flux:callout.heading>
                    <flux:callout.text>Talangan PL Rp {{ number_format($dompet['reimbursement'], 0, ',', '.') }} menunggu penyelesaian di luar sistem.</flux:callout.text>
                </flux:callout>
            @endif

            {{-- SUMMARY --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Saldo Berjalan</p>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums {{ $this->saldo() < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                        Rp {{ number_format($this->saldo(), 0, ',', '.') }}
                    </h3>
                    @if (! empty($dompet['rab_amount']))
                        <p class="text-xs text-zinc-400 mt-1">Realisasi vs RAB Rp {{ number_format($dompet['rab_amount'], 0, ',', '.') }}</p>
                    @endif
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Masuk</p>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-green-600">Rp {{ number_format($this->totalMasuk(), 0, ',', '.') }}</h3>
                </div>
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">Total Keluar</p>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-red-600">Rp {{ number_format($this->totalKeluar(), 0, ',', '.') }}</h3>
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
                                @if (! $this->isClosed())
                                    <th class="px-3 py-3 md:px-6 font-normal text-center whitespace-nowrap">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->decoratedTransactions() as $trx)
                                <tr wire:key="trx-{{ $trx['id'] }}" class="border-t border-zinc-100 dark:border-zinc-800">
                                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ \Carbon\Carbon::parse($trx['transaction_date'])->translatedFormat('d M Y') }}</td>
                                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                                        <flux:badge size="sm" :color="$trx['direction'] === 'masuk' ? 'green' : 'red'">{{ ucfirst($trx['direction']) }}</flux:badge>
                                    </td>
                                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $trx['category'] }}</td>
                                    <td class="px-3 py-3 md:px-6">{{ $trx['description'] }}</td>
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
                                    @if (! $this->isClosed())
                                        <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                                            <div class="flex items-center justify-center gap-1">
                                                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editTransaksi({{ $trx['id'] }})" />
                                                <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600"
                                                    wire:click="hapusTransaksi({{ $trx['id'] }})"
                                                    wire:confirm="Hapus transaksi ini?" />
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->isClosed() ? 7 : 8 }}" class="px-6 py-8 text-center text-zinc-400">Belum ada transaksi pada dompet ini</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- ===== MODAL: CATAT TRANSAKSI ===== --}}
        <flux:modal name="catat-transaksi" class="w-lg">
            <form wire:submit="catatTransaksi" class="space-y-6">
                <flux:heading size="lg">{{ $editingId ? 'Edit Transaksi' : 'Catat Transaksi' }}</flux:heading>

                <flux:radio.group wire:model="direction" label="Arah" variant="segmented">
                    <flux:radio value="keluar" label="Keluar" />
                    <flux:radio value="masuk" label="Masuk" />
                </flux:radio.group>

                <flux:input type="number" label="Nominal" prefix="Rp" wire:model="amount" placeholder="0" />
                <flux:select label="Kategori" wire:model="category" placeholder="Pilih kategori">
                    @foreach ($categories as $cat)
                        <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:textarea label="Deskripsi" wire:model="description" placeholder="Keterangan transaksi" rows="2" />
                <flux:input type="date" label="Tanggal" wire:model="date" />
                <flux:input type="file"
                    label="{{ $editingId ? 'Ganti Bukti (opsional — jpg, png, pdf)' : 'Bukti (wajib — jpg, png, pdf)' }}"
                    wire:model="bukti" />

                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="catatTransaksi,bukti">
                    <span wire:loading.remove wire:target="catatTransaksi">{{ $editingId ? 'Perbarui' : 'Simpan' }}</span>
                    <span wire:loading wire:target="catatTransaksi">Menyimpan...</span>
                </flux:button>
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
                    <span class="text-lg font-semibold tabular-nums {{ $this->saldo() < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                        Rp {{ number_format($this->saldo(), 0, ',', '.') }}
                    </span>
                </div>

                @if ($this->saldo() > 0)
                    <flux:callout variant="secondary" icon="arrow-uturn-left">
                        <flux:callout.heading>Setor balik sisa</flux:callout.heading>
                        <flux:callout.text>Sisa Rp {{ number_format($this->saldo(), 0, ',', '.') }} akan dicatat sebagai pengeluaran "Setor Balik" sehingga saldo menjadi 0.</flux:callout.text>
                    </flux:callout>
                    <flux:input type="file" label="Bukti setor balik (opsional)" wire:model="settle_bukti" />
                @elseif ($this->saldo() < 0)
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <flux:callout.heading>Reimbursement</flux:callout.heading>
                        <flux:callout.text>Saldo negatif Rp {{ number_format(abs($this->saldo()), 0, ',', '.') }} akan disnapshot sebagai reimbursement (diselesaikan di luar sistem).</flux:callout.text>
                    </flux:callout>
                @else
                    <flux:callout variant="success" icon="check-circle">
                        <flux:callout.heading>Saldo nol</flux:callout.heading>
                        <flux:callout.text>Kegiatan dapat langsung ditutup.</flux:callout.text>
                    </flux:callout>
                @endif

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="ghost">Batal</flux:button>
                    </flux:modal.close>
                    <flux:button variant="danger" icon="lock-closed" wire:click="settle" wire:loading.attr="disabled" wire:target="settle">
                        Tutup & Segel
                    </flux:button>
                </div>
            </div>
        </flux:modal>

    </div>
</div>
