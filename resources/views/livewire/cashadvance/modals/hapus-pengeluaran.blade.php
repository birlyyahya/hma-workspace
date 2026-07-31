<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public string $kodeCa = '';

    public ?int $deleteTransactionId = null;

    public function mount(string $kodeCa = ''): void
    {
        $this->kodeCa = $kodeCa;
    }

    /**
     * Simpan id transaksi yang hendak dihapus; modal dibuka dari sisi pemicu.
     */
    #[On('ca-confirm-delete')]
    public function confirm(int $id): void
    {
        $this->deleteTransactionId = $id;
    }

    public function deleteTransaction(): void
    {
        if (! $this->deleteTransactionId) {
            Toaster::error('ID transaksi tidak valid.');

            return;
        }

        $result = app(CaWriter::class)->deleteTransactionCaPL($this->kodeCa, $this->deleteTransactionId);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menghapus pengeluaran.'));

            return;
        }

        $this->reset('deleteTransactionId');
        $this->dispatch('ca-updated');
        Flux::modal('hapus-pengeluaran')->close();
        Toaster::success('Pengeluaran berhasil dihapus.');
    }
}; ?>

<flux:modal name="hapus-pengeluaran" class="w-md">
    <div class="space-y-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-500/10 text-red-600 dark:text-red-400">
                <flux:icon name="trash" class="size-5" />
            </span>
            <div>
                <flux:heading size="lg">Hapus Pengeluaran</flux:heading>
                <flux:text class="mt-1">Apakah Anda yakin ingin menghapus pengeluaran ini? Tindakan ini tidak dapat dibatalkan.</flux:text>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" icon="trash" wire:click="deleteTransaction">Hapus</flux:button>
        </div>
    </div>
</flux:modal>
