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

    /**
     * Palet warna "kantong" yang dirotasi per indeks, konsisten dengan carousel
     * dompet kegiatan di ca-index.
     *
     * @return array{chip: string, text: string}
     */
    private function pocketTone(int $index): array
    {
        $palette = [
            ['chip' => 'bg-ca-pocket-1/10 dark:bg-ca-pocket-1/15', 'text' => 'text-ca-pocket-1'],
            ['chip' => 'bg-ca-pocket-2/10 dark:bg-ca-pocket-2/15', 'text' => 'text-ca-pocket-2'],
            ['chip' => 'bg-ca-pocket-3/10 dark:bg-ca-pocket-3/15', 'text' => 'text-ca-pocket-3'],
            ['chip' => 'bg-ca-pocket-4/10 dark:bg-ca-pocket-4/15', 'text' => 'text-ca-pocket-4'],
            ['chip' => 'bg-ca-pocket-5/10 dark:bg-ca-pocket-5/15', 'text' => 'text-ca-pocket-5'],
        ];

        return $palette[$index % count($palette)];
    }
}; ?>

<flux:modal name="semua-dompet-kegiatan" class="w-xl" flyout>
    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Dompet Kegiatan</p>
            <flux:heading size="lg" class="mt-1">Semua Dompet Kegiatan</flux:heading>
            <flux:text class="mt-1">Seluruh dompet kegiatan milik Anda, aktif maupun yang sudah ditutup.</flux:text>
        </div>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari nama kegiatan / kode CA" />

        <div class="space-y-2 max-h-112 overflow-y-auto -mx-1 px-1">
            @forelse ($this->dompetKegiatan() as $i => $item)
            @php
                $isClosed = ! ($item['is_active'] ?? true);
                $tone = $this->pocketTone($i);
            @endphp
            <a wire:key="all-dompet-{{ $item['kode_ca'] }}" href="{{ route('cashadvance.dompet-show', $item['kode_ca']) }}" wire:navigate
                class="group flex items-center gap-3 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-3.5 transition hover:border-ca-copper/30 dark:hover:border-ca-copper/40">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-full {{ $isClosed ? 'bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500' : $tone['chip'].' '.$tone['text'] }}">
                    <flux:icon :name="$isClosed ? 'lock-closed' : 'briefcase'" class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <p class="text-sm font-semibold truncate text-zinc-900 dark:text-white">{{ $item['judul_kegiatan'] }}</p>
                        <flux:badge size="sm" :color="$isClosed ? 'zinc' : 'green'">{{ $isClosed ? 'Ditutup' : 'Aktif' }}</flux:badge>
                        @if ((int) ($item['user_id'] ?? 0) !== (int) Auth::id())
                        <flux:badge size="sm" color="zinc" variant="pill">Kolaborator</flux:badge>
                        @endif
                    </div>
                    <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $item['kode_ca'] }}</p>
                    <p class="mt-1 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="calendar" class="size-3.5 shrink-0" />
                        {{ isset($item['tanggal_mulai']) ? \Carbon\Carbon::parse($item['tanggal_mulai'])->translatedFormat('d M Y') : '—' }}
                        &ndash; {{ isset($item['tanggal_selesai']) ? \Carbon\Carbon::parse($item['tanggal_selesai'])->translatedFormat('d M Y') : 'sekarang' }}
                    </p>
                </div>

                <div class="text-right shrink-0">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-400">Sisa Saldo</p>
                    <p class="text-sm font-bold tabular-nums {{ ($item['saldo_akhir'] ?? 0) < 0 ? 'text-red-600' : ($isClosed ? 'text-zinc-400' : $tone['text']) }}">
                        Rp {{ number_format($item['saldo_akhir'] ?? 0, 0, ',', '.') }}
                    </p>
                </div>

                <flux:icon name="chevron-right" class="size-4 shrink-0 text-zinc-300 transition group-hover:text-ca-copper dark:text-zinc-600" />
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
