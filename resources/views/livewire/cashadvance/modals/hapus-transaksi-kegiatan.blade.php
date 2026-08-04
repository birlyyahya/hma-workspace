<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public string $kodeCa = '';

    public ?int $deleteTransactionId = null;

    /**
     * Snapshot transaksi yang hendak dihapus, dikirim dari baris pemicu
     * (sudah ada di memori sana) supaya activity log deletion mencatat apa
     * yang hilang, bukan cuma id-nya.
     *
     * @var array<string, mixed>
     */
    public array $deleteTransactionOriginal = [];

    public function mount(string $kodeCa = ''): void
    {
        $this->kodeCa = $kodeCa;
    }

    /**
     * Simpan transaksi yang hendak dihapus; modal dibuka dari sisi pemicu.
     */
    #[On('ca-kegiatan-confirm-delete')]
    public function confirm(int $id, ?string $jenis = null, ?float $jumlah = null, ?string $kategori = null, ?string $deskripsi = null, ?string $tanggal = null): void
    {
        $this->deleteTransactionId = $id;
        $this->deleteTransactionOriginal = compact('jenis', 'jumlah', 'kategori', 'deskripsi', 'tanggal');
    }

    public function deleteTransaction(): void
    {
        if (! $this->deleteTransactionId) {
            Toaster::error('ID transaksi tidak valid.');

            return;
        }

        $result = app(CaWriter::class)->deleteTransaksiKegiatan($this->kodeCa, $this->deleteTransactionId, $this->deleteTransactionOriginal);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menghapus transaksi.'));

            return;
        }

        $this->reset('deleteTransactionId', 'deleteTransactionOriginal');
        $this->dispatch('ca-updated');
        Flux::modal('hapus-transaksi-kegiatan')->close();
        Toaster::success('Transaksi berhasil dihapus.');
    }
}; ?>

<flux:modal name="hapus-transaksi-kegiatan" class="w-md">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Hapus Transaksi</flux:heading>
            <flux:text class="mt-1">Apakah Anda yakin ingin menghapus transaksi ini? Tindakan ini tidak dapat dibatalkan.</flux:text>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" icon="trash" wire:click="deleteTransaction">Hapus</flux:button>
        </div>
    </div>
</flux:modal>
