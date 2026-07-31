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
    public array $balance = [];

    public $totalPenerimaan = null;

    public $buktiSetor = null;

    /**
     * Saldo akhir periode berjalan minus berarti dana perlu direimburse oleh
     * management, bukan disetor oleh Project Leader — label & keterangan
     * unggahan menyesuaikan kondisi ini.
     */
    public function isReimbursement(): bool
    {
        return (float) ($this->balance['saldo_akhir'] ?? 0) < 0;
    }

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

    public function topup(): void
    {
        $this->validate();

        $payload = [
            'user_id' => Auth::id(),
            'username' => Auth::user()->username,
            'tahun_anggaran' => now()->format('Y'),
            'tanggal_mulai' => now()->tomorrow()->format('Y-m-d'),
            'total_penerimaan' => (int) $this->totalPenerimaan,
            'bukti_setor' => [
                'contents' => $this->buktiSetor->get(),
                'name' => $this->buktiSetor->getClientOriginalName(),
            ],
        ];

        $response = app(CaWriter::class)->topup($payload);

        if (! $response['ok']) {
            Toaster::error($response['body']['message'] ?? 'Gagal melakukan top up.');

            return;
        }

        $this->resetForm();
        $this->dispatch('ca-updated');
        $this->dispatch('riwayat-updated');

        Flux::modal('topup-laporan')->close();

        Toaster::success('Topup Berhasil');
    }

    public function resetForm(): void
    {
        $this->reset(['totalPenerimaan', 'buktiSetor']);
        $this->resetValidation();
    }
}; ?>

<flux:modal name="topup-laporan" class="w-xl" @close="$wire.resetForm()">
    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Top Up CA PL</p>
            <flux:heading size="lg" class="mt-1">Laporan CA PL #{{ $balance['kode_ca'] }}</flux:heading>
            <flux:text class="mt-1">Rekap pertanggungjawaban sebelum top up. Saldo akan direstore ke Rp 0.</flux:text>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Total Pengeluaran</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-ca-copper-deep dark:text-ca-copper">Rp {{ number_format($balance['total_pengeluaran'] ?? 0, 0, ',', '.') ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-zinc-200 dark:border-zinc-700 p-4">
                <p class="text-xs text-zinc-500 dark:text-zinc-400">Closing Balance</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-zinc-900 dark:text-white">Rp {{ number_format($balance['saldo_akhir'] ?? 0, 0, ',', '.') ?? 0 }}</p>
            </div>
        </div>

        <flux:field>
            <flux:label>Nominal Top Up (restore ke float)</flux:label>
            <x-rupiah-input model="totalPenerimaan" placeholder="8.000.000.000" />
            <flux:error name="totalPenerimaan" />
        </flux:field>

        @if ($this->isReimbursement())
        <div class="flex items-start gap-2 rounded-2xl border border-ca-copper/20 bg-ca-copper/5 p-3 text-xs text-ca-copper-deep dark:text-ca-copper">
            <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
            <span>Saldo akhir periode ini minus, sehingga dana perlu direimburse oleh management ke rekening Anda.</span>
        </div>
        @endif

        <flux:field>
            <flux:label>{{ $this->isReimbursement() ? 'Bukti TF Penyelesaian Reimbursement dari Management' : 'Bukti TF Penyelesaian Dokumen Resmi' }}</flux:label>
            <flux:input wire:model="buktiSetor" type="file" accept=".jpg,.jpeg,.png,.pdf" />
            <flux:text class="mt-1 text-xs">
                @if ($this->isReimbursement())
                    Unggah bukti transfer reimbursement dari management (jpg, png, pdf &mdash; maks 2MB).
                @else
                    Unggah bukti transfer penyelesaian dokumen resmi sebagai syarat top up (jpg, png, pdf &mdash; maks 2MB).
                @endif
            </flux:text>
            <span wire:loading wire:target="buktiSetor" class="mt-1 block text-xs text-zinc-500 animate-pulse">
                Mengunggah bukti&hellip;
            </span>
            <flux:error name="buktiSetor" />
        </flux:field>

        <div class="flex justify-between gap-2">
            <flux:button :href="route('cashadvance.laporan.capl', $balance['kode_ca'])" target="_blank" variant="ghost" icon="document-arrow-down" class="text-ca-copper! hover:text-ca-copper-deep!">
                Lihat Laporan PDF
            </flux:button>
            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click='topup' icon="check" class="bg-ca-copper! hover:bg-ca-copper-deep!"
                    wire:loading.attr="disabled" wire:target="buktiSetor,topup">
                    <span wire:loading.remove wire:target="buktiSetor,topup">Verifikasi &amp; Eksekusi Top up</span>
                    <span wire:loading wire:target="buktiSetor" class="animate-pulse">Menunggu unggahan&hellip;</span>
                    <span wire:loading wire:target="topup" class="animate-pulse">Memproses&hellip;</span>
                </flux:button>
            </div>
        </div>
    </div>
</flux:modal>
