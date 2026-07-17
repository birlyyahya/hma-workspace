<?php

use App\Services\CaCache;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array<string, mixed>
     */
    public function riwayatDompetCaPl(): array
    {
        return app(CaCache::class)->riwayatDompetCaPl(Auth::id()) ?? [];
    }
}; ?>

<flux:modal name="riwayat-periode" class="w-xl" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Riwayat Periode CA PL</flux:heading>
            <flux:text class="mt-1">Periode yang sudah disegel (read-only). Buka laporan untuk cetak/simpan PDF.</flux:text>
        </div>

        <div class="space-y-2">
            @forelse ($this->riwayatDompetCaPl()['data'] ?? [] as $dompet)
                <div wire:key="seal-{{ $dompet['id'] }}"
                    class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-zinc-900 dark:text-white">Periode #{{ $dompet['kode_ca'] }}</p>
                            <flux:badge size="sm" color="zinc">Closed</flux:badge>
                        </div>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            {{ \Carbon\Carbon::parse($dompet['tanggal_mulai'])->translatedFormat('d M Y') }}
                            – {{ \Carbon\Carbon::parse($dompet['created_at'])->translatedFormat('d M Y') }}
                        </p>
                        <p class="text-xs text-zinc-500 mt-0.5">
                            • Keluar Rp {{ number_format($dompet['total_pengeluaran'], 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-medium tabular-nums {{ $dompet['saldo_akhir'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                            Rp {{ number_format($dompet['saldo_akhir'], 0, ',', '.') }}
                        </span>
                        <flux:button :href="route('cashadvance.laporan.capl', $dompet['id'])" target="_blank"
                            size="sm" variant="outline" icon="document-arrow-down">Laporan</flux:button>
                    </div>
                </div>
            @empty
            <p class="text-sm text-zinc-400 text-center py-4">Belum ada periode tersegel.</p>
            @endforelse
        </div>
    </div>
</flux:modal>
