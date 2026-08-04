<?php

use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    public string $kodeCa = '';
    public string $judul_kegiatan = '';
    public ?int $rab = null;
    public ?int $dana_cair = null;
    public $bukti = null;

    public function mount(string $kodeCa = ''): void
    {
        $this->kodeCa = $kodeCa;
    }

    protected function rules(): array
    {
        return [
            'judul_kegiatan' => ['required', 'string', 'min:3'],
            'rab' => ['nullable', 'integer', 'min:1'],
            'dana_cair' => ['required', 'integer', 'min:1'],
            'bukti' => ['required', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $payload = [
            'user_id' => Auth::id(),
            'username' => Auth::user()->name,
            'judul_kegiatan' => $this->judul_kegiatan,
            'tahun_anggaran' => now()->year,
            'tanggal_mulai' => now()->format('Y-m-d'),
            'total_penerimaan' => $this->dana_cair,
        ];

        if ($this->bukti) {
            $payload['bukti'] = [
                'contents' => $this->bukti->get(),
                'name' => $this->bukti->getClientOriginalName(),
            ];
        }

        $result = app(CaWriter::class)->createDompetKegiatan($payload);

        if (!$result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal membuat dompet kegiatan.'));
            return;
        }

        $this->reset();
        Flux::modal('buat-kegiatan')->close();
        Toaster::success('Dompet kegiatan berhasil dibuat.');
        $this->dispatch('ca-updated');
    }

    public function resetForm(): void
    {
        $this->reset(['judul_kegiatan', 'rab', 'dana_cair', 'bukti']);
        $this->resetValidation();
    }
}; ?>

<flux:modal name="buat-kegiatan" class="w-lg" @close="$wire.resetForm()">
    <form wire:submit.prevent="save" class="space-y-6">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Dompet Kegiatan</p>
            <flux:heading size="lg" class="mt-1">Buat Dompet Kegiatan</flux:heading>
            <flux:text class="mt-1">Dana cair dicatat sebagai transaksi masuk pertama.</flux:text>
        </div>

        <flux:input wire:model="judul_kegiatan" label="Nama Kegiatan" placeholder="mis. Survei Lokasi Bandung" />
        <flux:error name="judul_kegiatan" />

        <flux:field>
            <flux:label>RAB (Opsional)</flux:label>
            <x-rupiah-input model="rab" placeholder="8.000.000.000" />
            <flux:error name="rab" />
        </flux:field>

        <flux:field>
            <flux:label>Dana Cair</flux:label>
            <x-rupiah-input model="dana_cair" placeholder="8.000.000.000" />
            <flux:error name="dana_cair" />
        </flux:field>

        <flux:field>
            <flux:label>Bukti pencairan (wajib &mdash; jpg, png, pdf &mdash; maks 2MB)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <label class="flex w-full min-w-0 max-w-full cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    Pilih File
                </span>
                <span class="block min-w-0 flex-1 overflow-hidden text-left text-ellipsis whitespace-nowrap text-zinc-600 dark:text-zinc-300" dir="rtl">
                    {{ $bukti?->getClientOriginalName() ?? 'Belum ada file dipilih' }}
                </span>
                <input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
            </label>
            <flux:error name="bukti" />
            <span wire:loading wire:target="bukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">Mengunggah bukti…</span>
        </flux:field>

        <flux:button type="submit" variant="primary" class="w-full bg-ca-copper! hover:bg-ca-copper-deep!" wire:loading.attr="disabled" wire:target="bukti,save">
            <span wire:loading.remove wire:target="bukti,save">Buat Dompet</span>
            <span wire:loading wire:target="bukti" class="animate-pulse">Menunggu unggahan…</span>
            <span wire:loading wire:target="save" class="animate-pulse">Membuat…</span>
        </flux:button>
    </form>
</flux:modal>
