<?php

use App\Services\CaWriter;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    /** @var array<string, mixed> */
    #[Reactive]
    public array $wallet = [];

    /**
     * Hanya pemilik dompet (atau role scope cashadvance 'all') yang boleh
     * menghapus dompet kegiatan.
     */
    private function canManageWallet(): bool
    {
        return (int) ($this->wallet['user_id'] ?? 0) === (int) Auth::id()
            || Auth::user()->viewScopeFor('cashadvance') === 'all';
    }

    public function deleteWallet(): void
    {
        if (! $this->canManageWallet()) {
            Toaster::error('Hanya pemilik dompet yang dapat menghapus kegiatan.');

            return;
        }

        $kodeCa = $this->wallet['kode_ca'] ?? null;

        if (! $kodeCa) {
            Toaster::error('Dompet tidak valid.');

            return;
        }

        $result = app(CaWriter::class)->deleteDompetKegiatan($kodeCa, $this->wallet);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menghapus dompet kegiatan.'));

            return;
        }

        Toaster::success('Dompet kegiatan berhasil dihapus.');
        $this->redirect(route('cashadvance'), navigate: true);
    }
}; ?>

<flux:modal name="hapus-dompet-kegiatan" class="md:w-110">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-50 ring-4 ring-red-50/50 dark:bg-red-500/10 dark:ring-red-500/5">
                <flux:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
            </div>
            <div class="min-w-0 flex-1 space-y-1">
                <flux:heading size="lg">Hapus Dompet Kegiatan?</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $wallet['judul_kegiatan'] ?? $wallet['kode_ca'] ?? '' }}"</span>
                    beserta seluruh riwayat transaksinya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="deleteWallet" variant="danger" icon="trash" class="flex-1"
                wire:loading.attr="disabled" wire:target="deleteWallet">
                <span wire:loading.remove wire:target="deleteWallet">Hapus</span>
                <span wire:loading wire:target="deleteWallet">Menghapus...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
