<?php

use App\Services\PengajuanBarangDummy;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'Workspace - Buat Pengajuan Barang'])]
class extends Component {
    use WithFileUploads;

    public string $kategori = 'non_project';

    public ?int $project_id = null;

    public string $keperluan = '';

    public string $tanggal_dibutuhkan = '';

    /** @var array<int, array{nama_barang: string, spesifikasi: string, qty: string, satuan: string, estimasi_harga: string}> */
    public array $items = [];

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $lampiran = [];

    public function mount(): void
    {
        $this->tanggal_dibutuhkan = now()->addWeek()->toDateString();
        $this->addItem();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kategori' => 'required|in:project,non_project',
            'project_id' => 'required_if:kategori,project|nullable|integer',
            'keperluan' => 'required|string|max:500',
            'tanggal_dibutuhkan' => 'required|date|after_or_equal:today',
            'items' => 'required|array|min:1',
            'items.*.nama_barang' => 'required|string|max:255',
            'items.*.spesifikasi' => 'nullable|string|max:255',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.satuan' => 'required|string',
            'items.*.estimasi_harga' => 'nullable|numeric|min:0',
            'lampiran.*' => 'file|mimes:jpg,jpeg,png,pdf,xlsx,xls,doc,docx|max:5120',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'project_id.required_if' => 'Pilih project untuk pengajuan kategori project.',
            'items.*.nama_barang.required' => 'Nama barang wajib diisi.',
            'items.*.qty.required' => 'Qty wajib diisi.',
            'items.*.qty.min' => 'Qty minimal 1.',
            'items.*.satuan.required' => 'Pilih satuan.',
        ];
    }

    /** @return array<int, array{id: int, name: string}> */
    #[Computed]
    public function projects(): array
    {
        return app(PengajuanBarangDummy::class)->projects();
    }

    /** @return array<int, string> */
    #[Computed]
    public function satuanOptions(): array
    {
        return app(PengajuanBarangDummy::class)->satuan();
    }

    #[Computed]
    public function totalEstimasi(): int
    {
        return (int) collect($this->items)
            ->sum(fn (array $item): int => (int) ($item['qty'] ?: 0) * (int) ($item['estimasi_harga'] ?: 0));
    }

    public function addItem(): void
    {
        $this->items[] = ['nama_barang' => '', 'spesifikasi' => '', 'qty' => '1', 'satuan' => 'pcs', 'estimasi_harga' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function removeLampiran(int $index): void
    {
        unset($this->lampiran[$index]);
        $this->lampiran = array_values($this->lampiran);
    }

    public function updatedKategori(): void
    {
        if ($this->kategori !== 'project') {
            $this->project_id = null;
            $this->resetValidation('project_id');
        }
    }

    /** Halaman masih static: validasi lalu redirect tanpa persist. */
    public function submit(): void
    {
        $this->validate();

        Toaster::success('Pengajuan barang berhasil diajukan (dummy — belum tersimpan).');
        $this->redirectRoute('pengajuan-barang', navigate: true);
    }
}; ?>

<div class="bg-zinc-50/50 min-h-screen">
    <div class="max-w-4xl mx-auto px-2 py-4 space-y-4 md:space-y-6">

        <div class="flex items-center gap-3">
            <a href="{{ route('pengajuan-barang') }}" wire:navigate>
                <flux:button size="sm" variant="ghost" icon="arrow-left" class="cursor-pointer" />
            </a>
            <div>
                <flux:heading size="lg">Buat Pengajuan Barang</flux:heading>
                <flux:text class="text-sm">Isi detail pengajuan, daftar barang, dan lampiran pendukung.</flux:text>
            </div>
        </div>

        <form wire:submit="submit" class="space-y-4 md:space-y-6">

            {{-- ================= INFO PENGAJUAN ================= --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
                <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Informasi Pengajuan</flux:heading>

                <flux:radio.group wire:model.live="kategori" label="Kategori Pengajuan" variant="segmented">
                    <flux:radio value="non_project" label="Non-Project" />
                    <flux:radio value="project" label="Project" />
                </flux:radio.group>

                @if ($kategori === 'project')
                    <flux:select wire:model="project_id" label="Project" placeholder="Pilih project...">
                        @foreach ($this->projects() as $project)
                            <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:textarea wire:model="keperluan" label="Keperluan" rows="3" placeholder="Jelaskan keperluan pengajuan barang ini..." />

                <flux:input type="date" wire:model="tanggal_dibutuhkan" label="Tanggal Dibutuhkan" class="sm:max-w-xs" />
            </div>

            {{-- ================= DAFTAR BARANG ================= --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Daftar Barang</flux:heading>
                    <flux:button size="sm" variant="outline" icon="plus" wire:click="addItem" class="cursor-pointer">Tambah Item</flux:button>
                </div>

                @error('items')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror

                <div class="space-y-3">
                    @foreach ($items as $index => $item)
                        <div wire:key="item-{{ $index }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 sm:p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <flux:text class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Item #{{ $index + 1 }}</flux:text>
                                @if (count($items) > 1)
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeItem({{ $index }})" class="cursor-pointer text-red-600!" />
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <flux:input wire:model="items.{{ $index }}.nama_barang" label="Nama Barang" placeholder="cth: Kertas A4 80gsm" />
                                <flux:input wire:model="items.{{ $index }}.spesifikasi" label="Spesifikasi (opsional)" placeholder="cth: Sinar Dunia, putih" />
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <flux:input type="number" min="1" wire:model.live="items.{{ $index }}.qty" label="Qty" />
                                <flux:select wire:model="items.{{ $index }}.satuan" label="Satuan">
                                    @foreach ($this->satuanOptions() as $satuan)
                                        <option value="{{ $satuan }}">{{ $satuan }}</option>
                                    @endforeach
                                </flux:select>
                                <flux:input type="number" min="0" wire:model.live="items.{{ $index }}.estimasi_harga" label="Estimasi Harga /satuan" placeholder="0" class="col-span-2 sm:col-span-1" />
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex items-center justify-between rounded-lg bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3">
                    <flux:text class="text-sm font-medium">Total Estimasi</flux:text>
                    <span class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">
                        Rp {{ number_format($this->totalEstimasi(), 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- ================= LAMPIRAN ================= --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-4 sm:p-6 space-y-4">
                <div>
                    <flux:heading size="sm" class="uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Dokumen Lampiran</flux:heading>
                    <flux:text class="text-sm mt-1">Opsional — RAB, penawaran harga, foto, dsb. (jpg/png/pdf/xlsx/doc, maks 5MB per file)</flux:text>
                </div>

                <flux:input type="file" wire:model="lampiran" multiple />
                @error('lampiran.*')
                    <flux:text class="text-sm text-red-600">{{ $message }}</flux:text>
                @enderror

                @if (count($lampiran) > 0)
                    <div class="space-y-2">
                        @foreach ($lampiran as $index => $file)
                            <div wire:key="lampiran-{{ $index }}" class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <flux:icon name="paper-clip" class="size-4 shrink-0 text-zinc-400" />
                                    <span class="truncate text-sm text-zinc-700 dark:text-zinc-300">{{ $file->getClientOriginalName() }}</span>
                                </div>
                                <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeLampiran({{ $index }})" class="cursor-pointer" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- ================= ACTIONS ================= --}}
            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
                <a href="{{ route('pengajuan-barang') }}" wire:navigate class="w-full sm:w-auto">
                    <flux:button variant="ghost" class="w-full cursor-pointer">Batal</flux:button>
                </a>
                <flux:button type="submit" variant="primary" icon="paper-airplane" class="w-full sm:w-auto cursor-pointer" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submit">Ajukan Pengajuan</span>
                    <span wire:loading wire:target="submit">Mengajukan...</span>
                </flux:button>
            </div>
        </form>
    </div>
</div>
