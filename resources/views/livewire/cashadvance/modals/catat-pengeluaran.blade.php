<?php

use App\Services\CaCache;
use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public string $kodeCa = '';

    /** @var array<int, string> */
    public array $categories = [];

    public ?int $editingId = null;
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
     */
    public function mount(string $kodeCa = '', array $categories = []): void
    {
        $this->kodeCa = $kodeCa;
        $this->categories = $categories;
    }

    /**
     * Siapkan form untuk pencatatan baru dan buka modal. Tanggal default ke
     * waktu sekarang agar pengguna tak perlu mengisi manual.
     */
    #[On('ca-new-expense')]
    public function prepareNew(): void
    {
        $this->resetForm();
        $this->tanggal = now()->format('Y-m-d');
    }

    /**
     * Isi form dari transaksi terpilih untuk diedit. Dilakukan di server
     * (bukan @js di atribut komponen) karena kutip ter-escape pada atribut
     * component tag membuat precompiler Livewire salah membaca tanda kurung.
     */
    #[On('ca-edit-expense')]
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
        $this->tanggal = \Carbon\Carbon::parse($trx['tanggal'])->format('Y-m-d');
        $this->bukti = null;
        $this->original = $trx;
    }

    /**
     * @return array<string, mixed>
     */
    public function transaksiCaPl(): array
    {
        return app(CaCache::class)->transaksiCaPlActive($this->kodeCa) ?? [];
    }

    /**
     * Validasi ulang begitu file baru selesai diunggah, agar pesan error lama
     * (mis. "lebih dari 2MB") langsung hilang saat pengguna mengunggah ulang
     * dengan file yang valid, tanpa harus menekan tombol simpan dulu.
     */
    public function updatedBukti(): void
    {
        $this->validateOnly('bukti');
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

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'bukti.required' => 'Unggah bukti transaksi terlebih dahulu.',
            'bukti.file' => 'Berkas bukti tidak valid.',
            'bukti.mimes' => 'Bukti harus berupa file JPG, PNG, atau PDF.',
            'bukti.max' => 'Ukuran bukti maksimal 2 MB. Kompres atau pilih file lain.',
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

        $isEdit = $this->editingId !== null;

        $result = $isEdit
            ? app(CaWriter::class)->updateTransactionCaPl($this->kodeCa, $this->editingId, $payload, $this->original)
            : app(CaWriter::class)->createTransactionCaPl($this->kodeCa, $payload);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan pengeluaran.'));

            return;
        }

        $this->resetForm();
        $this->dispatch('ca-updated');
        Flux::modal('catat-pengeluaran')->close();
        Toaster::success($isEdit ? 'Pengeluaran diperbarui.' : 'Pengeluaran tersimpan.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'jumlah', 'kategori', 'deskripsi', 'tanggal', 'bukti', 'original']);
        $this->resetValidation();
    }
}; ?>

<flux:modal name="catat-pengeluaran" class="w-full max-w-lg" @close="$wire.resetForm()">
    {{-- uploading: dinaikkan ke form supaya tombol Simpan ikut tahu status --}}
    {{-- upload dari tombol kamera (yang di luar wire:model, lihat catatan di --}}
    {{-- field Bukti) — tanpa ini pengguna bisa menekan Simpan sebelum foto --}}
    {{-- selesai terunggah dan $bukti masih kosong. --}}
    <form wire:submit.prevent="saveExpense" class="space-y-6" x-data="{ uploading: false }"
        x-on:livewire-upload-start="uploading = true"
        x-on:livewire-upload-finish="uploading = false"
        x-on:livewire-upload-error="uploading = false"
        x-on:livewire-upload-cancel="uploading = false">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">CA PL</p>
            <flux:heading size="lg" class="mt-1">
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
            <flux:select.option>Pilih Kategori</flux:select.option>
            @foreach ($categories as $cat)
                <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="deskripsi" label="Deskripsi" placeholder="Keterangan pengeluaran" rows="2" />

        <flux:input wire:model="tanggal" type="date" label="Tanggal" />
        <flux:error name="tanggal" />

        <flux:field>
            <flux:label>Bukti (jpg, png, pdf &mdash; maks 2MB)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <div class="flex w-full min-w-0 items-stretch gap-2">
                <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                    <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                        Pilih File
                    </span>
                    <span class="min-w-0 flex-1 truncate text-left text-zinc-600 dark:text-zinc-300" dir="rtl">
                        {{ $bukti?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                    </span>
                    <input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
                </label>

                {{-- capture="environment" membuka kamera belakang langsung di HP (bukan --}}
                {{-- galeri/file browser). Sengaja TIDAK pakai wire:model di sini (beda --}}
                {{-- dari input Pilih File) — dua input dengan wire:model sama pernah --}}
                {{-- bikin klik ke-intercept JS Livewire, dan sebagian browser mobile --}}
                {{-- (terutama Safari iOS) hanya membuka kamera langsung untuk klik asli --}}
                {{-- pengguna, bukan klik yang di-trigger via JS. $wire.upload() dipanggil --}}
                {{-- manual dari event change murni supaya capture tetap jalan. --}}
                {{-- Set "uploading" langsung di sini + pakai callback finish/error/cancel --}}
                {{-- dari $wire.upload: event livewire-upload-* untuk upload programatik --}}
                {{-- terpancar di root komponen (leluhur form), jadi TIDAK menetes turun --}}
                {{-- ke listener form seperti pada input wire:model — makanya loading --}}
                {{-- kamera harus di-drive manual, bukan mengandalkan event yang menetes. --}}
                <label :class="uploading ? 'pointer-events-none opacity-60' : ''"
                    class="flex shrink-0 cursor-pointer items-center justify-center rounded-lg border border-zinc-200 bg-white px-3 text-zinc-500 transition hover:border-ca-copper/40 hover:text-ca-copper dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400"
                    title="Ambil foto langsung">
                    <flux:icon name="camera" class="size-5" x-show="! uploading" />
                    <flux:icon name="arrow-path" class="size-5 animate-spin" x-show="uploading" x-cloak />
                    <input type="file" accept="image/*" capture="environment" class="sr-only"
                        x-on:change="
                            if (! $event.target.files.length) return;
                            uploading = true;
                            $wire.upload('bukti', $event.target.files[0],
                                () => uploading = false,
                                () => uploading = false,
                                () => {},
                                () => uploading = false,
                            );
                        " />
                </label>
            </div>
            <flux:error name="bukti" />
            {{-- livewire-upload-* dipancarkan untuk upload manapun (wire:model MAUPUN --}}
            {{-- $wire.upload manual), jadi satu flag "uploading" ini cukup untuk kedua --}}
            {{-- input bukti di atas — tidak perlu wire:loading wire:target="bukti" lagi. --}}
            <span x-show="uploading" x-cloak class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
            <flux:text x-show="$wire.editingId" x-cloak class="mt-1 text-xs">
                Kosongkan bila tidak mengganti bukti.
            </flux:text>
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full bg-ca-copper! hover:bg-ca-copper-deep!"
            x-bind:disabled="uploading" wire:loading.attr="disabled" wire:target="saveExpense">
            <span x-show="! uploading" wire:loading.remove wire:target="saveExpense">Simpan Pengeluaran</span>
            <span x-show="uploading" x-cloak class="animate-pulse">Menunggu unggahan&hellip;</span>
            <span wire:loading wire:target="saveExpense" class="animate-pulse">Menyimpan&hellip;</span>
        </flux:button>
    </form>
</flux:modal>
