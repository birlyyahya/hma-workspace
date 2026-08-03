<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public ?int $deleteCollaboratorId = null;

    public ?string $deleteCollaboratorName = null;

    /**
     * user_id pemilik dompet yang kolaboratornya hendak dihapus; ikut
     * dikirim dari pemicu supaya modal ini (tanpa akses ke $wallet penuh)
     * tetap bisa memverifikasi hanya pemilik yang boleh menghapus.
     */
    public ?int $ownerId = null;

    /**
     * Simpan kolaborator yang hendak dihapus; modal dibuka dari sisi pemicu
     * (baris desktop di dompet-show, atau daftar "Sudah Ditambahkan" di modal
     * tambah-kolaborator).
     */
    #[On('ca-confirm-delete-collaborator')]
    public function confirm(int $id, string $name, int $ownerId): void
    {
        $this->deleteCollaboratorId = $id;
        $this->deleteCollaboratorName = $name;
        $this->ownerId = $ownerId;
    }

    public function removeCollaborator(): void
    {
        if (! $this->deleteCollaboratorId) {
            Toaster::error('Kolaborator tidak valid.');

            return;
        }

        if ((int) $this->ownerId !== (int) Auth::id() && Auth::user()->viewScopeFor('cashadvance') !== 'all') {
            Toaster::error('Hanya pemilik dompet yang dapat menghapus kolaborator.');

            return;
        }

        $result = app(CaWriter::class)->deleteCollaborator($this->deleteCollaboratorId);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menghapus kolaborator.'));

            return;
        }

        $this->reset('deleteCollaboratorId', 'deleteCollaboratorName', 'ownerId');
        $this->dispatch('ca-updated');
        Flux::modal('hapus-kolaborator')->close();
        Toaster::success('Kolaborator berhasil dihapus.');
    }
}; ?>

<flux:modal name="hapus-kolaborator" class="md:w-110">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-full bg-red-50 ring-4 ring-red-50/50 dark:bg-red-500/10 dark:ring-red-500/5">
                <flux:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
            </div>
            <div class="min-w-0 flex-1 space-y-1">
                <flux:heading size="lg">Hapus Kolaborator?</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">"{{ $deleteCollaboratorName }}"</span>
                    akan dikeluarkan dari dompet ini. Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="removeCollaborator" variant="danger" icon="trash" class="flex-1"
                wire:loading.attr="disabled" wire:target="removeCollaborator">
                <span wire:loading.remove wire:target="removeCollaborator">Hapus</span>
                <span wire:loading wire:target="removeCollaborator">Menghapus...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
