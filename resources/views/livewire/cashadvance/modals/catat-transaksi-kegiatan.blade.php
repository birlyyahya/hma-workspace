<?php

use App\Services\CaCache;
use App\Services\CaWriter;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public string $kodeCa = '';

    /** @var array<int, string> */
    public array $categories = [];

    /** @var array<int, string> */
    public array $categoriesPemasukan = [];

    public ?int $editingId = null;
    public string $jenis = 'pengeluaran';
    public $jumlah;
    public $kategori;
    public $deskripsi;
    public $tanggal;
    public $bukti;

    /**
     * Snapshot transaksi asal sebelum diedit, dipakai CaWriter untuk mencatat
     * diff before/after pada activity log (lihat startEdit/saveExpense).
     *
     * @var array<string, mixed>
     */
    public array $original = [];

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, string>  $categoriesPemasukan
     */
    public function mount(string $kodeCa = '', array $categories = [], array $categoriesPemasukan = []): void
    {
        $this->kodeCa = $kodeCa;
        $this->categories = $categories;
        $this->categoriesPemasukan = $categoriesPemasukan;
    }

    /**
     * Daftar kategori yang ditawarkan berganti mengikuti arah transaksi:
     * kategori belanja untuk pengeluaran, kategori sumber dana untuk pemasukan.
     *
     * @return array<int, string>
     */
    public function kategoriOptions(): array
    {
        return $this->jenis === 'penerimaan' ? $this->categoriesPemasukan : $this->categories;
    }

    /**
     * Kategori terpilih bisa jadi tak berlaku lagi di daftar baru begitu arah
     * transaksi diganti (mis. "Transport" saat pindah ke arah Masuk).
     */
    public function updatedJenis(): void
    {
        if (! in_array($this->kategori, $this->kategoriOptions(), true)) {
            $this->kategori = null;
        }
    }

    /**
     * Siapkan form untuk pencatatan baru dan buka modal. Tanggal default ke
     * waktu sekarang agar pengguna tak perlu mengisi manual.
     */
    #[On('ca-kegiatan-new-transaksi')]
    public function prepareNew(): void
    {
        $this->resetForm();
        $this->tanggal = now()->format('Y-m-d');
    }

    /**
     * Isi form dari transaksi terpilih untuk diedit.
     */
    #[On('ca-kegiatan-edit-transaksi')]
    public function startEdit(int $id): void
    {
        $trx = collect($this->transaksiKegiatan()['data'] ?? [])->firstWhere('id_transaksi', $id);

        if (! $trx) {
            return;
        }

        $this->editingId = $id;
        $this->jenis = $trx['jenis'] ?? 'pengeluaran';
        $this->jumlah = (int) $trx['jumlah'];
        $this->kategori = $trx['kategori'] ?? '';
        $this->deskripsi = $trx['deskripsi'] ?? '';
        $this->tanggal = \Carbon\Carbon::parse($trx['tanggal'])->format('Y-m-d');
        $this->bukti = null;
        $this->original = $trx;
    }

    /**
     * @return array<string, mixed>
     */
    public function transaksiKegiatan(): array
    {
        return app(CaCache::class)->transaksiWalletKegiatan($this->kodeCa) ?? [];
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
            'jenis' => ['required', 'in:penerimaan,pengeluaran'],
            'jumlah' => ['required', 'integer', 'min:1'],
            'kategori' => ['required', 'string', Rule::in($this->kategoriOptions())],
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
            'jenis' => $this->jenis,
        ];

        if ($this->bukti) {
            $payload['bukti'] = [
                'contents' => $this->bukti->get(),
                'name' => $this->bukti->getClientOriginalName(),
            ];
        }

        $isEdit = $this->editingId !== null;

        $result = $isEdit
            ? app(CaWriter::class)->updateTransaksiKegiatan($this->kodeCa, $this->editingId, $payload, $this->original)
            : app(CaWriter::class)->createTransaksiKegiatan($this->kodeCa, $payload);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan transaksi.'));

            return;
        }

        $this->resetForm();
        Flux::modal('catat-transaksi-kegiatan')->close();
        Toaster::success($isEdit ? 'Transaksi diperbarui.' : 'Transaksi tersimpan.');
        $this->dispatch('ca-updated');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'jenis', 'jumlah', 'kategori', 'deskripsi', 'tanggal', 'bukti', 'original']);
        $this->jenis = 'pengeluaran';
        $this->resetValidation();
    }
}; ?>

<flux:modal name="catat-transaksi-kegiatan" class="w-lg" @close="$wire.resetForm()">
    <form wire:submit.prevent="saveExpense" class="space-y-6">
        <div>
            <flux:heading size="lg">
                <span x-show="! $wire.editingId">Catat Transaksi</span>
                <span x-show="$wire.editingId" x-cloak>Edit Transaksi</span>
            </flux:heading>
            <flux:text class="mt-1">Dana cair dicatat sebagai pemasukan, belanja sebagai pengeluaran.</flux:text>
        </div>

        <flux:radio.group wire:model.live="jenis" label="Arah" variant="segmented">
            <flux:radio value="pengeluaran" label="Keluar" />
            <flux:radio value="penerimaan" label="Masuk" />
        </flux:radio.group>

        <flux:field>
            <flux:label>Jumlah</flux:label>
            <x-rupiah-input model="jumlah" placeholder="8.000.000.000" />
            <flux:error name="jumlah" />
        </flux:field>

        <flux:select wire:model="kategori" label="Kategori" placeholder="Pilih kategori">
            <flux:select.option>Pilih Kategori</flux:select.option>
            @foreach ($this->kategoriOptions() as $cat)
                <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="deskripsi" label="Deskripsi" placeholder="Keterangan transaksi" rows="2" />

        <flux:input wire:model="tanggal" type="date" label="Tanggal" />
        <flux:error name="tanggal" />

        <flux:field>
            <flux:label>Bukti (jpg, png, pdf &mdash; maks 2MB)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <label class="flex w-full min-w-0 max-w-full cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    Pilih File
                </span>
                <span class="block min-w-0 flex-1 overflow-hidden text-left text-ellipsis whitespace-nowrap text-zinc-600 dark:text-zinc-300" dir="rtl">
                    {{ $bukti?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                </span>
                <input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
            </label>
            <flux:error name="bukti" />
            <span wire:loading wire:target="bukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
            <flux:text x-show="$wire.editingId" x-cloak class="mt-1 text-xs">
                Kosongkan bila tidak mengganti bukti.
            </flux:text>
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full"
            wire:loading.attr="disabled" wire:target="bukti,saveExpense">
            <span wire:loading.remove wire:target="bukti,saveExpense">Simpan Transaksi</span>
            <span wire:loading wire:target="bukti" class="animate-pulse">Menunggu unggahan&hellip;</span>
            <span wire:loading wire:target="saveExpense" class="animate-pulse">Menyimpan&hellip;</span>
        </flux:button>
    </form>
</flux:modal>
