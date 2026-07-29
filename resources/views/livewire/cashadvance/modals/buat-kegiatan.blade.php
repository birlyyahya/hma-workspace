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
            <flux:heading size="lg">Buat Dompet Kegiatan</flux:heading>
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

        <flux:input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" label="Bukti pencairan (wajib &mdash; jpg, png, pdf &mdash; maks 2MB)" />
        <span wire:loading wire:target="bukti" class="text-xs text-zinc-500 animate-pulse">Mengunggah bukti…</span>
        <flux:error name="bukti" />

        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled" wire:target="bukti,save">
            <span wire:loading.remove wire:target="bukti,save">Buat Dompet</span>
            <span wire:loading wire:target="bukti" class="animate-pulse">Menunggu unggahan…</span>
            <span wire:loading wire:target="save" class="animate-pulse">Membuat…</span>
        </flux:button>
    </form>
</flux:modal>
