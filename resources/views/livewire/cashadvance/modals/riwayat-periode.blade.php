<?php

use App\Services\CaCache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array<string, mixed>
     */

     #[On('riwayat-updated')]
     #[Computed]
    public function riwayatDompetCaPl(): array
    {
        return app(CaCache::class)->riwayatDompetCaPl(Auth::id()) ?? [];
    }

    /**
     * URL publik MinIO untuk bukti setor (bukti TF penyelesaian dokumen resmi
     * atau reimbursement management saat top up) — sama pola dengan bukti
     * transaksi di ca-index.
     */
    public function buktiSetorUrl(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return Storage::disk('s3_cashadvance')->temporaryUrl($path, now()->addMinutes(60), ['ResponseContentDisposition' => 'inline; filename="'.basename($path).'"']);
    }
}; ?>

<flux:modal name="riwayat-periode" class="w-full max-w-xl" flyout>
    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Riwayat Periode</p>
            <flux:heading size="lg" class="mt-1">Periode CA PL Tersegel</flux:heading>
            <flux:text class="mt-1">Periode yang sudah ditutup (read-only). Buka laporan untuk cetak/simpan PDF.</flux:text>
        </div>

        <div class="space-y-2 max-h-112 overflow-y-auto -mx-1 px-1">
            @forelse ($this->riwayatDompetCaPl['data'] ?? [] as $dompet)
                <div wire:key="seal-{{ $dompet['id_ca'] }}"
                    class="flex items-center gap-3 rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4 transition hover:border-ca-copper/30 dark:hover:border-ca-copper/40">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800 dark:text-zinc-500">
                        <flux:icon name="lock-closed" class="size-5" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $dompet['judul_kegiatan'] ?? 'CA PL' }}</p>
                            <flux:badge size="sm" color="zinc" class="shrink-0">Ditutup</flux:badge>
                        </div>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $dompet['kode_ca'] }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                            <flux:icon name="calendar" class="size-3.5 shrink-0" />
                            <span class="whitespace-nowrap">{{ \Carbon\Carbon::parse($dompet['tanggal_mulai'])->translatedFormat('d M Y') }}</span>
                            <span class="whitespace-nowrap">&ndash; {{ \Carbon\Carbon::parse($dompet['tanggal_selesai'])->translatedFormat('d M Y') }}</span>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1.5 text-right">
                        <div class="block w-full">
                            <p class="whitespace-nowrap text-right text-sm font-bold tabular-nums {{ $dompet['saldo_akhir'] < 0 ? 'text-red-600' : 'text-zinc-900 dark:text-white' }}">
                                Rp {{ number_format($dompet['saldo_akhir'], 0, ',', '.') }}
                            </p>
                            <p class="mt-0.5 whitespace-nowrap text-right text-xs font-medium text-ca-copper-deep dark:text-ca-copper">
                                &minus; Rp {{ number_format($dompet['total_pengeluaran'], 0, ',', '.') }}
                            </p>
                        </div>
                        <div class="flex flex-wrap justify-end gap-1">
                            @if ($url = $this->buktiSetorUrl($dompet['bukti'] ?? null))
                            <flux:button size="xs" variant="ghost" icon="paper-clip" type="button"
                                x-on:click="$dispatch('preview-bukti', {!! \Illuminate\Support\Js::from(['url' => $url, 'title' => 'Bukti Setor '.$dompet['kode_ca']]) !!}); $flux.modal('bukti-preview').show()">
                                Bukti
                            </flux:button>
                            @endif
                            <flux:button :href="route('cashadvance.laporan.capl', $dompet['kode_ca'])" target="_blank"
                                size="xs" variant="ghost" icon="document-arrow-down" class="text-ca-copper! hover:text-ca-copper-deep!">
                                Laporan
                            </flux:button>
                        </div>
                    </div>
                </div>
            @empty
            <div class="flex flex-col items-center gap-2 py-10 text-center">
                <flux:icon name="archive-box" class="size-8 text-zinc-300 dark:text-zinc-600" />
                <p class="text-sm text-zinc-400">Belum ada periode tersegel.</p>
            </div>
            @endforelse
        </div>
    </div>
</flux:modal>
