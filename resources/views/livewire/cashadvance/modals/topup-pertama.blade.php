<?php

use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;
use App\Services\CaWriter;

new class extends Component {
    use WithFileUploads;

    /**
     * Ajukan top up pertama untuk membuka dompet CA PL.
     * Endpoint writer belum tersedia — validasi & alur UI sudah siap,
     * tinggal delegasikan payload ke CaWriter saat API-nya ada.
     */
    public function submitInitialTopup(): void
    {

        $payload = [
            'user_id' => Auth::user()->id,
            'username' => Auth::user()->username,
            'tahun_anggaran' => now()->format('Y'),
            'tanggal_mulai' => now()->format('Y-m-d')
        ];

        $result = app(CaWriter::class)->topupCaPL($payload);

        if(!$result['ok']){
             Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan pengeluaran.'));

            return;
        }

        Flux::modal('topup-pertama')->close();
        $this->dispatch('ca-updated');
        Toaster::info('Pengajuan top up terkirim, Sedang diarahkan ke dashboard!');
    }
}; ?>

<flux:modal name="topup-pertama" class="w-lg">
    <form wire:submit.prevent="submitInitialTopup" class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Cash Advance</p>
            <flux:heading size="lg" class="mt-1">Top up Pertama</flux:heading>
            <flux:text class="mt-1">Dana ini menjadi saldo awal (float) dompet CA PL Anda.</flux:text>
        </div>

        <div>
            <span wire:loading wire:target="topupBukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
        </div>

        <flux:button type="submit" variant="primary" class="w-full bg-ca-copper! hover:bg-ca-copper-deep!"
            wire:loading.attr="disabled" wire:target="topupBukti,submitInitialTopup">
            <span wire:loading.remove wire:target="topupBukti,submitInitialTopup">Ajukan Top up</span>
            <span wire:loading wire:target="topupBukti" class="animate-pulse">Menunggu unggahan&hellip;</span>
            <span wire:loading wire:target="submitInitialTopup" class="animate-pulse">Mengirim&hellip;</span>
        </flux:button>
    </form>
</flux:modal>
