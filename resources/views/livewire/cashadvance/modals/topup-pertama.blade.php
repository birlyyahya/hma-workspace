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

<flux:modal name="topup-pertama" class="w-lg" @close="$wire.resetForm()">
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

        <div>
            <flux:input wire:model="buktiSetor" type="file" accept=".jpg,.jpeg,.png,.pdf"
                label="Bukti TF Setoran (jpg, png, pdf &mdash; maks 2MB)" />
            <span wire:loading wire:target="buktiSetor" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
            <flux:error name="buktiSetor" />
        </div>

        <flux:button type="submit" variant="primary" class="w-full bg-ca-copper! hover:bg-ca-copper-deep!"
            wire:loading.attr="disabled" wire:target="buktiSetor,submitInitialTopup">
            <span wire:loading.remove wire:target="buktiSetor,submitInitialTopup">Ajukan Top up</span>
            <span wire:loading wire:target="buktiSetor" class="animate-pulse">Menunggu unggahan&hellip;</span>
            <span wire:loading wire:target="submitInitialTopup" class="animate-pulse">Mengirim&hellip;</span>
        </flux:button>
    </form>
</flux:modal>
