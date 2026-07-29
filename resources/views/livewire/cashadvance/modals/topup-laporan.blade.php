<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    /** @var array<string, mixed> */
    #[Reactive]
    public array $balance = [];

    public function topup(){
        $payload = [
            'user_id' => Auth::id(),
            'username' => Auth::user()->username,
            'tahun_anggaran' => now()->format('Y'),
            'tanggal_mulai' => now()->tomorrow()->format('Y-m-d')
        ];

        $response = app(CaWriter::class)->topup($payload);
        if($response['ok']){
            $this->dispatch('ca-updated');

            Flux::modal('topup-laporan')->close();

            Toaster::success('Topup Berhasil');
        }
        Toaster::error($response['body']['message']);
    }
}; ?>

<flux:modal name="topup-laporan" class="w-xl">
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Laporan CA PL #{{ $balance['kode_ca'] }}</flux:heading>
            <flux:text class="mt-1">Rekap pertanggungjawaban sebelum top up. Saldo akan direstore ke Rp 0.</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <p class="text-xs text-zinc-500">Total Pengeluaran</p>
                <p class="text-lg font-semibold tabular-nums text-red-600">Rp {{ number_format($balance['total_pengeluaran'] ?? 0, 0, ',', '.') ?? 0 }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                <p class="text-xs text-zinc-500">Closing Balance</p>
                <p class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">Rp {{ number_format($balance['saldo_akhir'] ?? 0, 0, ',', '.') ?? 0 }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 flex items-center justify-between">
            <span class="text-sm text-zinc-500">Top up untuk restore ke float</span>
            <span class="text-lg font-semibold tabular-nums text-green-600">Rp 2.000.000</span>
        </div>

        <div class="flex justify-between gap-2">
            <flux:button :href="route('cashadvance.laporan.capl', $balance['kode_ca'])" target="_blank" variant="ghost" icon="document-arrow-down">
                Lihat Laporan PDF
            </flux:button>
            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click='topup' icon="check">Verifikasi &amp; Eksekusi Top up</flux:button>
            </div>
        </div>
    </div>
</flux:modal>
