<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public $totalPenerimaan = null;

    public $buktiSetor = null;

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'totalPenerimaan' => ['required', 'integer', 'min:1'],
            'buktiSetor' => ['required', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    /**
     * Ajukan top up pertama untuk membuka dompet CA PL.
     */
    public function submitInitialTopup(): void
    {
        $this->validate();

        $payload = [
            'user_id' => Auth::user()->id,
            'username' => Auth::user()->username,
            'tahun_anggaran' => now()->format('Y'),
            'tanggal_mulai' => now()->format('Y-m-d'),
            'total_penerimaan' => (int) $this->totalPenerimaan,
            'bukti_setor' => [
                'contents' => $this->buktiSetor->get(),
                'name' => $this->buktiSetor->getClientOriginalName(),
            ],
        ];

        $result = app(CaWriter::class)->topupCaPL($payload);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal menyimpan pengeluaran.'));

            return;
        }

        $this->resetForm();
        Flux::modal('topup-pertama')->close();
        $this->dispatch('ca-updated');
        Toaster::info('Pengajuan top up terkirim, Sedang diarahkan ke dashboard!');
    }

    public function resetForm(): void
    {
        $this->reset(['totalPenerimaan', 'buktiSetor']);
        $this->resetValidation();
    }
}; ?>

<flux:modal name="topup-pertama" class="w-full max-w-lg" @close="$wire.resetForm()">
    <form wire:submit.prevent="submitInitialTopup" class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Cash Advance</p>
            <flux:heading size="lg" class="mt-1">Top up Pertama</flux:heading>
            <flux:text class="mt-1">Dana ini menjadi saldo awal (float) dompet CA PL Anda.</flux:text>
        </div>

        <flux:field>
            <flux:label>Nominal Top Up</flux:label>
            <x-rupiah-input model="totalPenerimaan" placeholder="8.000.000.000" />
            <flux:error name="totalPenerimaan" />
        </flux:field>

        <flux:field>
            <flux:label>Bukti TF Setoran (jpg, png, pdf &mdash; maks 2MB)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <label class="flex w-full min-w-0 max-w-full cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    Pilih File
                </span>
                <span class="block min-w-0 flex-1 overflow-hidden text-left text-ellipsis whitespace-nowrap text-zinc-600 dark:text-zinc-300" dir="rtl">
                    {{ $buktiSetor?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                </span>
                <input wire:model="buktiSetor" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
            </label>
            <flux:error name="buktiSetor" />
            <span wire:loading wire:target="buktiSetor" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full bg-ca-copper! hover:bg-ca-copper-deep!"
            wire:loading.attr="disabled" wire:target="buktiSetor,submitInitialTopup">
            <span wire:loading.remove wire:target="buktiSetor,submitInitialTopup">Ajukan Top up</span>
            <span wire:loading wire:target="buktiSetor" class="animate-pulse">Menunggu unggahan&hellip;</span>
            <span wire:loading wire:target="submitInitialTopup" class="animate-pulse">Mengirim&hellip;</span>
        </flux:button>
    </form>
</flux:modal>
