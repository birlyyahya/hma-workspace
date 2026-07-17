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
        $this->tanggal = \Carbon\Carbon::parse($trx['created_at'])->format('Y-m-d');
        $this->bukti = null;
    }

    /**
     * @return array<string, mixed>
     */
    public function transaksiCaPl(): array
    {
        return app(CaCache::class)->transaksiCaPlActive($this->kodeCa) ?? [];
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

        $isEdit = $this->editingId !== null;

        $result = $isEdit
            ? app(CaWriter::class)->updateTransactionCaPl($this->kodeCa, $this->editingId, $payload)
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
        $this->reset(['editingId', 'jumlah', 'kategori', 'deskripsi', 'tanggal', 'bukti']);
        $this->resetValidation();
    }
}; ?>

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
            <flux:select.option>Pilih Kategori</flux:select.option>
            @foreach ($categories as $cat)
                <flux:select.option value="{{ $cat }}">{{ $cat }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:textarea wire:model="deskripsi" label="Deskripsi" placeholder="Keterangan pengeluaran" rows="2" />

        <flux:input wire:model="tanggal" type="date" label="Tanggal" />
        <flux:error name="tanggal" />

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
