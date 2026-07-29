<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    /** @var array<string, mixed> */
    #[Reactive]
    public array $wallet = [];

    public $bukti_setor;

    protected function rules(): array
    {
        return [
            'bukti_setor' => ['nullable', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    public function close(): void
    {
        $this->validate();

        $payload = ['id' => $this->wallet['id']];

        if ($this->bukti_setor) {
            $payload['bukti_setor'] = [
                'contents' => $this->bukti_setor->get(),
                'name' => $this->bukti_setor->getClientOriginalName(),
            ];
        }

        $result = app(CaWriter::class)->closeKegiatan($payload);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menutup kegiatan.'));

            return;
        }

        $this->reset('bukti_setor');
        Flux::modal('tutup-kegiatan')->close();
        Toaster::success('Kegiatan berhasil ditutup.');
        $this->dispatch('ca-updated');
    }
}; ?>

<flux:modal name="tutup-kegiatan" class="w-lg">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Tutup Kegiatan</flux:heading>
            <flux:text class="mt-1">Setelah ditutup, dompet tidak menerima transaksi lagi.</flux:text>
        </div>

        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
            <span class="text-sm text-zinc-500">Saldo Akhir</span>
            <span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                Rp {{ number_format($wallet['saldo_akhir'] ?? 0, 0, ',', '.') }}
            </span>
        </div>

        <div>
            <flux:input wire:model="bukti_setor" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Bukti setor balik (opsional)" />
            <flux:error name="bukti_setor" />
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="danger" icon="lock-closed" wire:click="close">Tutup &amp; Segel</flux:button>
        </div>
    </div>
</flux:modal>
