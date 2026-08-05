<?php

use App\Models\User;
use App\Services\CaWriter;
use Flux\Flux;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

    /** @var array<string, mixed> */
    #[Reactive]
    public array $wallet = [];

    public string $judul_kegiatan = '';

    public ?int $tahun_anggaran = null;

    public string $tanggal_mulai = '';

    public ?int $saldo_awal_priode = null;

    public $bukti = null;

    public function mount(): void
    {
        $this->loadForm();
    }

    /**
     * Hydrate field form dari prop wallet. Dipanggil saat mount & tiap kali
     * modal dibuka (via event) supaya nilai selalu mengikuti data terbaru,
     * bukan snapshot mount pertama.
     */
    #[On('ca-edit-dompet-open')]
    public function loadForm(): void
    {
        $this->judul_kegiatan = $this->wallet['judul_kegiatan'] ?? '';
        $this->tahun_anggaran = (int) ($this->wallet['tahun_anggaran'] ?? now()->year);
        $this->tanggal_mulai = ! empty($this->wallet['tanggal_mulai'])
            ? \Carbon\Carbon::parse($this->wallet['tanggal_mulai'])->format('Y-m-d')
            : now()->format('Y-m-d');
        $this->saldo_awal_priode = (int) ($this->wallet['saldo_awal_priode'] ?? 0);
        $this->reset('bukti');
        $this->resetValidation();
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function rules(): array
    {
        return [
            'judul_kegiatan' => ['required', 'string', 'min:3'],
            'tahun_anggaran' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_mulai' => ['required', 'date'],
            'saldo_awal_priode' => ['required', 'integer', 'min:0'],
            'bukti' => ['nullable', 'file', 'mimes:jpg,png,pdf', 'max:2048'],
        ];
    }

    /**
     * Hanya pemilik dompet (atau role scope cashadvance 'all') yang boleh
     * mengubah detail dompet — kolaborator hanya boleh catat transaksi.
     */
    private function canManageWallet(): bool
    {
        return (int) ($this->wallet['user_id'] ?? 0) === (int) Auth::id()
            || Auth::user()->viewScopeFor('cashadvance') === 'all';
    }

    public function save(): void
    {
        if (! $this->canManageWallet()) {
            Toaster::error('Hanya pemilik dompet yang dapat mengubah detail kegiatan.');

            return;
        }

        if (! ($this->wallet['is_active'] ?? true)) {
            Toaster::error('Dompet yang sudah ditutup tidak dapat diubah.');

            return;
        }

        $this->validate();

        $ownerId = (int) ($this->wallet['user_id'] ?? Auth::id());

        $payload = [
            'user_id' => $ownerId,
            'username' => $this->wallet['username'] ?? User::find($ownerId)?->name ?? Auth::user()->name,
            'judul_kegiatan' => $this->judul_kegiatan,
            'tahun_anggaran' => $this->tahun_anggaran,
            'tanggal_mulai' => $this->tanggal_mulai,
            'saldo_awal_priode' => $this->saldo_awal_priode,
        ];

        if ($this->bukti) {
            $payload['bukti'] = [
                'contents' => $this->bukti->get(),
                'name' => $this->bukti->getClientOriginalName(),
            ];
        }

        $result = app(CaWriter::class)->updateDompetKegiatan($this->wallet['kode_ca'], $payload, $this->wallet);

        if (! $result['ok']) {
            Toaster::error($result['error'] ?? ($result['body']['message'] ?? 'Gagal memperbarui dompet kegiatan.'));

            return;
        }

        $this->reset('bukti');
        Flux::modal('edit-dompet-kegiatan')->close();
        Toaster::success('Detail dompet kegiatan berhasil diperbarui.');
        $this->dispatch('ca-updated');
    }
}; ?>

<flux:modal name="edit-dompet-kegiatan" class="w-full max-w-lg" @close="$wire.loadForm()">
    <form wire:submit.prevent="save" class="space-y-6">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-ca-copper/10 text-ca-copper-deep dark:bg-ca-copper/15 dark:text-ca-copper">
                <flux:icon name="pencil-square" class="size-5" />
            </span>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-ca-copper">Dompet Kegiatan</p>
                <flux:heading size="lg" class="mt-1">Edit Detail Kegiatan</flux:heading>
                <flux:text class="mt-1">{{ $wallet['kode_ca'] ?? '' }}</flux:text>
            </div>
        </div>

        <flux:field>
            <flux:label>Nama Kegiatan</flux:label>
            <flux:input wire:model="judul_kegiatan" placeholder="mis. Survei Lokasi Bandung" />
            <flux:error name="judul_kegiatan" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Tahun Anggaran</flux:label>
                <flux:input type="number" wire:model="tahun_anggaran" min="2000" max="2100" placeholder="{{ now()->year }}" />
                <flux:error name="tahun_anggaran" />
            </flux:field>

            <flux:field>
                <flux:label>Tanggal Mulai</flux:label>
                <flux:input type="date" wire:model="tanggal_mulai" />
                <flux:error name="tanggal_mulai" />
            </flux:field>
        </div>

        {{-- Nominal dana awal — mengubah nilai ini menyesuaikan ulang saldo --}}
        {{-- berjalan dompet, jadi diberi penanda peringatan yang jelas. --}}
        <div class="space-y-4 rounded-2xl border border-ca-copper/20 bg-ca-copper/5 p-4">
            <div class="flex items-start gap-2 text-xs text-ca-copper-deep dark:text-ca-copper">
                <flux:icon name="exclamation-triangle" class="size-4 shrink-0" />
                <span>Mengubah nominal di bawah menyesuaikan ulang saldo berjalan dompet. Pastikan angkanya benar.</span>
            </div>

            <flux:field>
                <flux:label>Saldo Awal Periode</flux:label>
                <x-rupiah-input model="saldo_awal_priode" placeholder="2.500.000" />
                <flux:error name="saldo_awal_priode" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Ganti bukti pencairan (opsional &mdash; jpg, png, pdf &mdash; maks 2MB)</flux:label>
            {{-- Nama file native <input type=file> tidak bisa di-truncate via CSS (bukan --}}
            {{-- text node biasa) — sembunyikan input asli, tampilkan nama file lewat span --}}
            {{-- sendiri yang bisa di-truncate. dir=rtl + text-left = ellipsis di depan, --}}
            {{-- akhir nama file (termasuk ekstensi) tetap terlihat. --}}
            <label class="flex w-full min-w-0 max-w-full cursor-pointer items-center gap-3 overflow-hidden rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                <span class="shrink-0 rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100">
                    Pilih File
                </span>
                <span class="block min-w-0 flex-1 overflow-hidden text-left text-ellipsis whitespace-nowrap text-zinc-600 dark:text-zinc-300" dir="rtl">
                    {{ $bukti?->getClientOriginalName() ?? 'Biarkan kosong untuk mempertahankan bukti lama' }}
                </span>
                <input wire:model="bukti" type="file" accept=".jpg,.jpeg,.png,.pdf" class="sr-only" />
            </label>
            <flux:error name="bukti" />
            <span wire:loading wire:target="bukti" class="mt-1 block text-xs text-zinc-500 animate-pulse">Mengunggah bukti&hellip;</span>
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" type="button">Batal</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="check" class="bg-ca-copper! hover:bg-ca-copper-deep!"
                wire:loading.attr="disabled" wire:target="bukti,save">
                <span wire:loading.remove wire:target="bukti,save">Simpan Perubahan</span>
                <span wire:loading wire:target="bukti" class="animate-pulse">Menunggu unggahan&hellip;</span>
                <span wire:loading wire:target="save" class="animate-pulse">Menyimpan&hellip;</span>
            </flux:button>
        </div>
    </form>
</flux:modal>
