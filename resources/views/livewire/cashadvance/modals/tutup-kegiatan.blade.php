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

    /**
     * Cuma pemilik dompet (atau role scope cashadvance 'all') yang boleh
     * menutup kegiatan — kolaborator hanya diizinkan catat transaksi.
     */
    private function canManageWallet(): bool
    {
        return (int) ($this->wallet['user_id'] ?? 0) === (int) Auth::id()
            || Auth::user()->viewScopeFor('cashadvance') === 'all';
    }

    public function close(): void
    {
        if (! $this->canManageWallet()) {
            Toaster::error('Hanya pemilik dompet yang dapat menutup kegiatan.');

            return;
        }

        $this->validate();

        $payload = ['id' => $this->wallet['id']];

        if ($this->bukti_setor) {
            $payload['bukti_setor'] = [
                'contents' => $this->bukti_setor->get(),
                'name' => $this->bukti_setor->getClientOriginalName(),
            ];
        }

        $result = app(CaWriter::class)->closeKegiatan($payload, [
            'kode_ca' => $this->wallet['kode_ca'] ?? null,
            'judul_kegiatan' => $this->wallet['judul_kegiatan'] ?? null,
            'saldo_akhir' => $this->wallet['saldo_akhir'] ?? 0,
        ]);

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
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper">
                <flux:icon name="lock-closed" class="size-5" />
            </span>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Dompet Kegiatan</p>
                <flux:heading size="lg" class="mt-1">Tutup &amp; Segel Dompet</flux:heading>
                <flux:text class="mt-1">Setelah disegel, dompet ini tidak menerima transaksi baru lagi.</flux:text>
            </div>
        </div>

        {{-- Ringkasan penutupan memakai gaya kartu dompet utama (ca-index) supaya --}}
        {{-- pengguna langsung mengenali ini sebagai state akhir dompet yang sama. --}}
        <div class="relative overflow-hidden rounded-2xl bg-linear-to-br from-ca-ink-lift to-ca-ink p-5 text-white">
            <div class="pointer-events-none absolute inset-0 opacity-40" style="background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.06) 0 1px, transparent 1px 24px);"></div>

            <div class="relative space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <p class="truncate text-[11px] font-semibold uppercase tracking-wider text-ca-gold">{{ $wallet['judul_kegiatan'] ?? $wallet['kode_ca'] ?? 'Dompet Kegiatan' }}</p>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-semibold text-white/70">
                        <flux:icon name="lock-closed" class="size-3" /> Akan disegel
                    </span>
                </div>

                <div>
                    <p class="text-xs text-white/50">Saldo akhir</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums">Rp {{ number_format($wallet['saldo_akhir'] ?? 0, 0, ',', '.') }}</p>
                </div>

                <div class="grid grid-cols-2 gap-4 border-t border-white/10 pt-4">
                    <div>
                        <p class="text-[11px] text-white/50">Total pemasukan</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-emerald-300">Rp {{ number_format($wallet['total_penerimaan'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-[11px] text-white/50">Total pengeluaran</p>
                        <p class="mt-0.5 text-sm font-semibold tabular-nums text-orange-300">Rp {{ number_format($wallet['total_pengeluaran'] ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-start gap-2 rounded-2xl border border-ca-copper/20 bg-ca-copper/5 p-3 text-xs text-ca-copper-deep dark:text-ca-copper">
            <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
            <span>Tindakan ini tidak dapat dibatalkan. Pastikan seluruh pengeluaran sudah tercatat sebelum menutup.</span>
        </div>

        <flux:field>
            <flux:label>Bukti setor balik (opsional)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <label class="flex w-full min-w-0 max-w-full cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    Pilih File
                </span>
                <span class="block min-w-0 flex-1 overflow-hidden text-left text-ellipsis whitespace-nowrap text-zinc-600 dark:text-zinc-300" dir="rtl">
                    {{ $bukti_setor?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                </span>
                <input wire:model="bukti_setor" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
            </label>
            <flux:error name="bukti_setor" />
            <span wire:loading wire:target="bukti_setor" class="mt-1 block text-xs text-zinc-500 animate-pulse">Mengunggah bukti&hellip;</span>
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">Batal</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" icon="lock-closed" wire:click="close" class="bg-ca-copper! hover:bg-ca-copper-deep!"
                wire:loading.attr="disabled" wire:target="bukti_setor,close">
                <span wire:loading.remove wire:target="bukti_setor,close">Tutup &amp; Segel</span>
                <span wire:loading wire:target="bukti_setor" class="animate-pulse">Menunggu unggahan&hellip;</span>
                <span wire:loading wire:target="close" class="animate-pulse">Menutup&hellip;</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
