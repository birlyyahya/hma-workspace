<?php

use App\Services\CaCache;
use Livewire\Volt\Component;

new class extends Component {
    public string $search = '';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function dompetKegiatan(): array
    {
        $response = app(CaCache::class)->listWalletKegiatan(Auth::id(), ['search' => $this->search]);

        return $response['data'] ?? [];
    }
}; ?>

<flux:modal name="semua-dompet-kegiatan" class="w-xl" flyout>
    <div class="space-y-6">
        <div>
            <flux:heading size="lg">Semua Dompet Kegiatan</flux:heading>
            <flux:text class="mt-1">Seluruh dompet kegiatan milik Anda, aktif maupun yang sudah ditutup.</flux:text>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama kegiatan / kode CA" />

        <div class="space-y-2 max-h-[28rem] overflow-y-auto -mx-1 px-1">
            @forelse ($this->dompetKegiatan() as $item)
            @php $isClosed = ! ($item['is_active'] ?? true); @endphp
            <a wire:key="all-dompet-{{ $item['kode_ca'] }}" href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}" wire:navigate
                class="group flex items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 hover:border-zinc-300 dark:hover:border-zinc-600 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition">
                <flux:avatar :name="$item['judul_kegiatan']" :color="$isClosed ? 'zinc' : 'blue'" size="sm" />

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-medium truncate text-zinc-900 dark:text-white">{{ $item['judul_kegiatan'] }}</p>
                        <flux:badge size="sm" :color="$isClosed ? 'zinc' : 'green'">{{ $isClosed ? 'Closed' : 'Active' }}</flux:badge>
                    </div>
                    <p class="mt-0.5 flex items-center gap-1 text-xs text-zinc-500">
                        <flux:icon name="calendar" class="size-3.5 shrink-0" />
                        {{ isset($item['tanggal_mulai']) ? \Carbon\Carbon::parse($item['tanggal_mulai'])->translatedFormat('d M Y') : '—' }}
                        – {{ isset($item['tanggal_selesai']) ? \Carbon\Carbon::parse($item['tanggal_selesai'])->translatedFormat('d M Y') : 'now' }}
                    </p>
                </div>

                <div class="text-right shrink-0">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-400">Sisa Saldo</p>
                    <p class="text-sm font-semibold tabular-nums {{ ($item['saldo_akhir'] ?? 0) < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                        Rp {{ number_format($item['saldo_akhir'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </a>
            @empty
            <div class="flex flex-col items-center gap-2 py-10 text-center">
                <flux:icon name="folder-open" class="size-8 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm text-zinc-400">Tidak ada dompet kegiatan yang cocok.</p>
            </div>
            @endforelse
        </div>
    </div>
</flux:modal>
