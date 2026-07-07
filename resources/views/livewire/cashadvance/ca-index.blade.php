<?php

use App\Services\CaCache;
use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public array $caPlBalance = [];
    public array $chart = [];
    public array $caKegiatanBalance = [];

    /** @var array<int, string> */
    public array $categories = [];

    // Form Catat / Edit Pengeluaran
    public ?int $editingId = null;
    public $jumlah;
    public $kategori;
    public $deskripsi;
    public $tanggal;
    public $bukti;

    public $deleteTransactionId = null;

    // Form Top Up Pertama (aktivasi CA)
    public $topupJumlah;
    public $topupBukti;
    public $topupCatatan;

    // dompet Kegiatan
    public int $rab;


    public function mount(): void
    {
        $this->refreshDashboard();
    }

    /**
     * Pengguna dianggap sudah memiliki CA bila dashboard API mengembalikan
     * kode dompet CA PL. Bila belum, UI onboarding top up ditampilkan.
     */
    public function hasCa(): bool
    {
        return filled($this->caPlBalance['kode_ca'] ?? null);
    }

    /**
     * Ajukan top up pertama untuk membuka dompet CA PL.
     * Endpoint writer belum tersedia — validasi & alur UI sudah siap,
     * tinggal delegasikan payload ke CaWriter saat API-nya ada.
     */
    public function submitInitialTopup(): void
    {
        $this->validate([
            'topupJumlah' => ['required', 'integer', 'min:1'],
            'topupBukti' => ['required', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
            'topupCatatan' => ['nullable', 'string'],
        ]);

        Flux::modal('topup-pertama')->close();
        Toaster::info('Pengajuan top up terkirim — menunggu integrasi API CA.');
    }

    public function transaksiCaPl(): array
    {
        return app(CaCache::class)->transaksiCaPlActive($this->caPlBalance['kode_ca'] ?? '') ?? [];
    }

    /**
     * Aturan validasi. Bukti wajib saat mencatat baru, opsional saat mengedit
     * (agar pengguna tak perlu unggah ulang bila hanya mengubah nominal/kategori).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'jumlah' => ['required', 'integer', 'min:1'],
            'kategori' => ['required', 'string'],
            'deskripsi' => ['nullable', 'string'],
            'tanggal' => ['required', 'date'],
            'bukti' => [$this->editingId ? 'nullable' : 'required', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    public function saveExpense(): void
    {
        $this->validate();

        $payload = [
            'jumlah' => (int) $this->jumlah,
            'kategori' => $this->kategori,
            'deskripsi' => $this->deskripsi,
            'tanggal' => $this->tanggal,
            'jenis' => 'pengeluaran',
            'user_id' => Auth::id(),
        ];

        if ($this->bukti) {
            $payload['bukti'] = [
                'contents' => $this->bukti->get(),
                'name' => $this->bukti->getClientOriginalName(),
            ];
        }

        $kode = $this->caPlBalance['kode_ca'] ?? '';
        $isEdit = $this->editingId !== null;

        $result = $isEdit
            ? app(CaWriter::class)->updateTransactionCaPl($kode, $this->editingId, $payload)
            : app(CaWriter::class)->createTransactionCaPl($kode, $payload);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan pengeluaran.'));

            return;
        }

        $this->refreshDashboard();
        $this->resetForm();
        Flux::modal('catat-pengeluaran')->close();
        Toaster::success($isEdit ? 'Pengeluaran diperbarui.' : 'Pengeluaran tersimpan.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'jumlah', 'kategori', 'deskripsi', 'tanggal', 'bukti']);
        $this->resetValidation();
    }

    /**
     * Isi form dari transaksi terpilih untuk diedit. Dilakukan di server
     * (bukan @js di atribut komponen) karena kutip ter-escape pada atribut
     * component tag membuat precompiler Livewire salah membaca tanda kurung.
     */
    public function startEdit(int $id): void
    {
        $trx = collect($this->transaksiCaPl()['data'] ?? [])->firstWhere('id_transaksi', $id);

        if (! $trx) {
            return;
        }

        $this->editingId = $id;
        $this->jumlah = (int) $trx['jumlah'];
        $this->kategori = $trx['kategori'] ?? '';
        $this->deskripsi = $trx['deskripsi'] ?? '';
        $this->tanggal = \Carbon\Carbon::parse($trx['created_at'])->toDateString();
        $this->bukti = null;
    }

    private function refreshDashboard(): void
    {
        $response = app(CaCache::class)->dashboard(Auth::id());
        $this->caPlBalance = $response['card_left'] ?? [];
        $this->chart = $response['chart'] ?? [];
        $this->caKegiatanBalance = $response['card_right'] ?? [];
        $this->categories = $response['categories'] ?? ['Transport', 'Akomodasi', 'Konsumsi', 'ATK', 'Komunikasi', 'Lain-lain'];
    }

    public function deleteTransactionCaPL(){
        if (! $this->deleteTransactionId) {
            Toaster::error('ID transaksi tidak valid.');
            return;
        }

        $kode = $this->caPlBalance['kode_ca'] ?? '';
        $result = app(CaWriter::class)->deleteTransactionCaPl($kode, $this->deleteTransactionId);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menghapus pengeluaran.'));
            return;
        }

        $this->refreshDashboard();
        $this->resetForm();
        Flux::modal('hapus-pengeluaran')->close();
        Toaster::success('Pengeluaran berhasil dihapus.');
    }

    public function riwayatDompetCaPl(){
        return app(CaCache::class)->riwayatDompetCaPl(Auth::id()) ?? [];
    }
}; ?>

<div class="space-y-4">

    @if (! $this->hasCa())
    {{-- ================= ONBOARDING: BELUM MEMILIKI CA ================= --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900">
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

    {{-- ================= MODAL: TOP UP PERTAMA ================= --}}
    <flux:modal name="topup-pertama" class="w-lg">
        <form wire:submit.prevent="submitInitialTopup" class="space-y-6">
            <div>
                <flux:heading size="lg">Top up Pertama</flux:heading>
                <flux:text class="mt-1">Dana ini menjadi saldo awal (float) dompet CA PL Anda.</flux:text>
            </div>

            <flux:input wire:model="topupJumlah" type="number" label="Nominal" prefix="Rp" placeholder="0" />
            <flux:textarea wire:model="topupCatatan" label="Catatan (opsional)" placeholder="Keterangan pengajuan" rows="2" />

            <div>
                <flux:input wire:model="topupBukti" type="file" accept=".jpg,.jpeg,.png,.pdf"
                    label="Bukti transfer (jpg, png, pdf &mdash; maks 2MB)" />
                <span wire:loading wire:target="topupBukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                    Mengunggah bukti&hellip;
                </span>
            </div>

            <flux:button type="submit" variant="primary" class="w-full"
                wire:loading.attr="disabled" wire:target="topupBukti,submitInitialTopup">
                <span wire:loading.remove wire:target="topupBukti,submitInitialTopup">Ajukan Top up</span>
                <span wire:loading wire:target="topupBukti" class="animate-pulse">Menunggu unggahan&hellip;</span>
                <span wire:loading wire:target="submitInitialTopup" class="animate-pulse">Mengirim&hellip;</span>
            </flux:button>
        </form>
    </flux:modal>
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
                            Rp {{ number_format($this->caPlBalance['sisa_saldo'] ?? 0, 0, ',', '.') }}
                        </h2>

                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            Dibuka &mdash;
                        </p>
                    </div>

                    <div class="flex gap-2">
                         <flux:modal.trigger name="catat-pengeluaran">
                             <flux:button size="sm" variant="primary" icon="minus-circle"
                                 x-on:click="$wire.editingId = null; $wire.jumlah = null; $wire.kategori = ''; $wire.deskripsi = ''; $wire.tanggal = ''; $wire.bukti = null;">
                                 Catat Pengeluaran
                             </flux:button>
                         </flux:modal.trigger>

                        <flux:modal.trigger name="topup-laporan">
                            <flux:button size="sm" variant="outline" icon="arrow-up-tray">Top up</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                {{-- CHART (data statik; wiring API dilakukan manual) --}}
                <div wire:ignore x-data="{ chart: null }" x-init="
                        chart = new ApexCharts($refs.caChart, {
                             series: @js($this->chart['series'] ?? []),
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
                        Rp {{ number_format($this->caPlBalance['income'] ?? 0, 0, ',', '.') }}
                    </h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Saldo awal periode aktif</p>
                </div>

                <div class="p-6 border-t sm:border-t-0 border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">Expense</p>
                        <flux:badge size="sm" color="red">Pengeluaran</flux:badge>
                    </div>
                    <h3 class="text-2xl font-semibold mt-2 tabular-nums text-zinc-900 dark:text-white">
                        Rp {{ number_format($this->caPlBalance['expense'] ?? 0, 0, ',', '.') }}
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
                @forelse (collect($this->caKegiatanBalance['data'] ?? [])->take(4) as $item)
                <a wire:key="dompet-{{ $item['kode_ca'] }}" href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}" wire:navigate class="flex items-center justify-between -mx-2 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <flux:avatar :name="$item['judul_kegiatan']" color="{{ $item['status'] ?? 'closed' === 'closed' ? 'zinc' : 'blue' }}" size="sm" />
                        <div class="text-left min-w-0">
                            <p class="text-sm font-medium truncate text-zinc-900 dark:text-white">{{ $item['judul_kegiatan'] }}</p>
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs text-zinc-500">{{ $item['kode_ca'] }}</span>
                                @if ($item['status'] ?? 'closed' === 'closed')
                                <flux:badge size="sm" color="zinc">closed</flux:badge>
                                @endif
                            </div>
                        </div>
                    </div>
                    <p class="text-sm font-medium tabular-nums {{ $item['sisa_saldo'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                        Rp {{ number_format($item['sisa_saldo'], 0, ',', '.') }}
                    </p>
                </a>
                @empty
                <p class="text-sm text-zinc-400">Belum ada dompet kegiatan.</p>
                @endforelse
                <flux:modal.trigger name="buat-kegiatan">
                    <flux:button variant="outline" class="w-full" icon="plus">Create new card</flux:button>
                </flux:modal.trigger>
            </div>

            {{-- BREAKDOWN PER KATEGORI --}}
            <div class="p-6 space-y-4">
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Pengeluaran per kategori &mdash; periode aktif</p>
                <h3 class="text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">
                    Rp {{ number_format($this->caKegiatanBalance['total_pengeluaran'] ?? 0, 0, ',', '.') }}
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
                @if (!empty($this->caKegiatanBalance['total_pengeluaran']))
                <div class="h-10 rounded bg-zinc-100 dark:bg-zinc-800 overflow-hidden flex">
                    @foreach ($this->caKegiatanBalance['kategori_pengeluaran'] as $i => $row)
                    @php $c = $palette[$i % count($palette)]; @endphp
                    <div class="{{ $c['bg'] }} border-l-2 {{ $c['border'] }}" style="width: {{ max($row['persentase'], 2) }}%" wire:key="bar-{{ $row['nama'] }}"></div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-x-4 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                    @foreach ($this->caKegiatanBalance['kategori_pengeluaran'] as $i => $row)
                    <p class="border-l-2 ps-2 {{ $palette[$i % count($palette)]['border'] }}" wire:key="legend-{{ $row['nama'] }}">
                        {{ $row['nama'] ? 'Lainnya' : $row['nama'] }} • {{ $row['persentase'] }}%
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
                    @forelse ($this->transaksiCaPl()['data'] ?? [] as $trx)
                    <tr wire:key="trx-{{ $trx['dompet_id'] }}" class="border-t border-zinc-100 dark:border-zinc-800">
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
                            @if ($trx['bukti'] ?? false)
                            <flux:button size="sm" variant="ghost" icon="paper-clip" />
                            @else
                            <span class="text-zinc-300">-</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 md:px-6 text-center whitespace-nowrap">
                            <div class="flex items-center justify-center gap-1">
                                <flux:modal.trigger name="catat-pengeluaran">
                                    <flux:button size="sm" variant="ghost" icon="pencil-square" type="button"
                                        wire:click="startEdit({{ $trx['id_transaksi'] }})" />
                                </flux:modal.trigger>
                                <flux:modal.trigger name="hapus-pengeluaran">
                                    <flux:button size="sm" variant="ghost" icon="trash" class="text-red-600" x-on:click="$wire.set('deleteTransactionId', {{ $trx['id_transaksi'] }})" />
                                </flux:modal.trigger>
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

    {{-- ================= MODAL: CATAT / EDIT PENGELUARAN ================= --}}
    <flux:modal name="catat-pengeluaran" class="w-lg" @close="$wire.resetForm()">
        <form wire:submit.prevent="saveExpense" class="space-y-6">
            <div>
                <flux:heading size="lg">
                    <span x-show="! $wire.editingId">Catat Pengeluaran</span>
                    <span x-show="$wire.editingId" x-cloak>Edit Pengeluaran</span>
                </flux:heading>
                <flux:text class="mt-1">Arah transaksi terkunci sebagai pengeluaran (keluar).</flux:text>
            </div>

            <flux:field>
                <flux:label>Jumlah</flux:label>
                <x-rupiah-input model="jumlah" placeholder="8.000.000.000" />
                <flux:error name="jumlah" />
            </flux:field>

            <flux:select wire:model="kategori" label="Kategori" placeholder="Pilih kategori">
                @foreach ($categories as $cat)
                    <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="deskripsi" label="Deskripsi" placeholder="Keterangan pengeluaran" rows="2" />
            <div x-data="{ tanggal: new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
            .toISOString()
            .slice(0,16) }">
                <flux:input
                    x-model="tanggal"
                    x-init="$wire.set('tanggal', tanggal)"
                    type="datetime-local"
                    label="Tanggal"
                />
            </div>

            <div>
                <flux:input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf"
                    label="Bukti (jpg, png, pdf &mdash; maks 2MB)" />
                <span wire:loading wire:target="bukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                    Mengunggah bukti&hellip;
                </span>
                <flux:text x-show="$wire.editingId" x-cloak class="mt-1 text-xs">
                    Kosongkan bila tidak mengganti bukti.
                </flux:text>
            </div>

            <flux:button type="submit" variant="primary" class="w-full"
                wire:loading.attr="disabled" wire:target="bukti,saveExpense">
                <span wire:loading.remove wire:target="bukti,saveExpense">Simpan Pengeluaran</span>
                <span wire:loading wire:target="bukti" class="animate-pulse">Menunggu unggahan&hellip;</span>
                <span wire:loading wire:target="saveExpense" class="animate-pulse">Menyimpan&hellip;</span>
            </flux:button>
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
                    <p class="text-lg font-semibold tabular-nums text-red-600">Rp {{ $this->caPlBalance['sisa_saldo'] ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <p class="text-xs text-zinc-500">Closing Balance</p>
                    <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">Rp {{ $this->caPlBalance['expense'] ?? 0 }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
                <span class="text-sm text-zinc-500">Top up untuk restore ke float</span>
                <span class="text-lg font-semibold tabular-nums text-green-600">Rp 2.000.000</span>
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
                @forelse ($this->riwayatDompetCaPl()['data'] ?? [] as $dompet)
                    <div wire:key="seal-{{ $dompet['id'] }}"
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-zinc-900 dark:text-white">Periode #{{ $dompet['kode_ca'] }}</p>
                                <flux:badge size="sm" color="zinc">Closed</flux:badge>
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                {{ \Carbon\Carbon::parse($dompet['tanggal_mulai'])->translatedFormat('d M Y') }}
                                – {{ \Carbon\Carbon::parse($dompet['created_at'])->translatedFormat('d M Y') }}
                            </p>
                            <p class="text-xs text-zinc-500 mt-0.5">
                                • Keluar Rp {{ number_format($dompet['total_pengeluaran'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium tabular-nums {{ $dompet['saldo_akhir'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                                Rp {{ number_format($dompet['saldo_akhir'], 0, ',', '.') }}
                            </span>
                            <flux:button :href="route('cashadvance.laporan.capl', $dompet['id'])" target="_blank"
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
        <form class="space-y-6">
            <div>
                <flux:heading size="lg">Buat Dompet Kegiatan</flux:heading>
                <flux:text class="mt-1">Dana cair dicatat sebagai transaksi masuk pertama.</flux:text>
            </div>

            <flux:input label="Nama Kegiatan" placeholder="mis. Survei Lokasi Bandung" />
            <flux:field>
                <flux:label>RAB (Opsional)</flux:label>
                <x-rupiah-input model="rab" placeholder="8.000.000.000" />
                <flux:error name="rab" />
            </flux:field>

            <flux:field>
                <flux:label>Dana Cair</flux:label>
                <x-rupiah-input model="dana_cair" placeholder="8.000.000.000" />
                <flux:error name="dana_cair" />
            </flux:field>
            <flux:input type="file" label="Bukti pencairan (wajib &mdash; jpg, png, pdf)" />

            <flux:button type="submit" variant="primary" class="w-full">Buat Dompet</flux:button>
        </form>
    </flux:modal>

    <flux:modal name="hapus-pengeluaran" class="w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Hapus Pengeluaran</flux:heading>
                <flux:text class="mt-1">Apakah Anda yakin ingin menghapus pengeluaran ini? Tindakan ini tidak dapat dibatalkan.</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" icon="trash" wire:click='deleteTransactionCaPL'>Hapus</flux:button>
            </div>
        </div>
    </flux:modal>
    @endif
</div>
