<?php

use App\Services\CaCache;
use Flux\Flux;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public string $kodeCa = '';

    #[Validate('required|date')]
    public string $tanggal = '';

    #[Validate('required|numeric|min:1')]
    public ?string $jumlah = null;

    #[Validate('required|string|max:255')]
    public string $deskripsi = '';

    #[Validate('nullable|file|mimes:jpg,jpeg,png,pdf|max:5120')]
    public $bukti = null;

    public function mount(string $kodeCa): void
    {
        $this->kodeCa = $kodeCa;
        $this->tanggal = now()->toDateString();
    }

    public function simpan(string $jenis, CaCache $ca): void
    {
        $this->validate();

        $response = $ca->addTransaksi($this->kodeCa, [
            'tanggal' => $this->tanggal,
            'jenis' => $jenis,
            'jumlah' => $this->jumlah,
            'deskripsi' => $this->deskripsi,
        ], $this->bukti);

        if (! ($response['success'] ?? false)) {
            Toaster::error($response['message'] ?? 'Gagal menambah transaksi');

            return;
        }

        Toaster::success('Transaksi berhasil ditambahkan');

        $this->reset(['jumlah', 'deskripsi', 'bukti']);
        $this->tanggal = now()->toDateString();

        Flux::modal(($jenis === 'pengeluaran' ? 'send-' : 'receive-') . $this->kodeCa)->close();

        $this->dispatch('transaksi-added', kodeCa: $this->kodeCa);
    }
}; ?>

<div class="flex gap-2">

    <flux:modal.trigger :name="'send-' . $kodeCa">
        <flux:button size="sm" variant="primary" class="cursor-pointer" iconTrailing="arrow-up-right">
            Send
        </flux:button>
    </flux:modal.trigger>

    <flux:modal.trigger :name="'receive-' . $kodeCa">
        <flux:button size="sm" variant="outline" class="cursor-pointer" iconTrailing="arrow-down-left">
            Receive
        </flux:button>
    </flux:modal.trigger>

    {{-- SEND (pengeluaran) --}}
    <flux:modal :name="'send-' . $kodeCa" class="w-lg">
        <form wire:submit="simpan('pengeluaran')" class="space-y-6">
            <flux:heading size="lg">Send Money</flux:heading>

            <flux:input type="date" label="Tanggal" wire:model="tanggal" />
            <flux:input type="number" label="Jumlah" prefix="Rp" wire:model="jumlah" placeholder="0" />
            <flux:textarea label="Deskripsi" wire:model="deskripsi" placeholder="Keterangan transaksi" rows="2" />
            <flux:input type="file" label="Bukti (jpg, png, pdf)" wire:model="bukti" />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="simpan">
                <span wire:loading.remove wire:target="simpan">Send Money</span>
                <span wire:loading wire:target="simpan">Menyimpan...</span>
            </flux:button>
        </form>
    </flux:modal>

    {{-- RECEIVE (penerimaan) --}}
    <flux:modal :name="'receive-' . $kodeCa" class="w-lg">
        <form wire:submit="simpan('penerimaan')" class="space-y-6">
            <flux:heading size="lg">Receive Money</flux:heading>

            <flux:input type="date" label="Tanggal" wire:model="tanggal" />
            <flux:input type="number" label="Jumlah" prefix="Rp" wire:model="jumlah" placeholder="0" />
            <flux:textarea label="Deskripsi" wire:model="deskripsi" placeholder="Keterangan transaksi" rows="2" />
            <flux:input type="file" label="Bukti (jpg, png, pdf)" wire:model="bukti" />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="simpan">
                <span wire:loading.remove wire:target="simpan">Confirm</span>
                <span wire:loading wire:target="simpan">Menyimpan...</span>
            </flux:button>
        </form>
    </flux:modal>

</div>
