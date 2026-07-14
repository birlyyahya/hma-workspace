<?php

use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Flux\Flux;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public int $id;

    public string $manageTab = 'manual';

    /**
     * Daftar spektek yang sedang disusun sebelum dikirim (belum hit API).
     *
     * @var array<int, array{uid: string, name: string, type: string, quantity: int, price: int, note: ?string}>
     */
    public array $drafts = [];

    public string $draftType = 'hardware';
    public string $draftName = '';
    public ?string $draftPrice = null;
    public ?int $draftQuantity = null;
    public ?string $draftNote = null;

    /**
     * Definisi kolom untuk import Excel (dibaca komponen frontend & validasi server).
     *
     * @return array<int, array<string, mixed>>
     */
    public function importColumns(): array
    {
        return [
            ['key' => 'name', 'header' => 'Nama', 'required' => true, 'type' => 'string'],
            ['key' => 'type', 'header' => 'Tipe', 'required' => true, 'type' => 'enum', 'map' => [
                'barang'    => 'hardware',
                'aplikasi'  => 'software',
                'hardware'  => 'hardware',
                'software'  => 'software',
            ]],
            ['key' => 'qty_total', 'header' => 'Jumlah', 'required' => true, 'type' => 'int'],
            ['key' => 'total_nominal', 'header' => 'Total Harga', 'required' => true, 'type' => 'number'],
            ['key' => 'note', 'header' => 'Catatan', 'required' => false, 'type' => 'string'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function importExample(): array
    {
        return [
            ['name' => 'Pipa PVC 4 inch', 'type' => 'Barang', 'qty_total' => 10, 'total_nominal' => 500000, 'note' => 'Merk bebas'],
            ['name' => 'Lisensi Office', 'type' => 'Aplikasi', 'qty_total' => 2, 'total_nominal' => 2000000, 'note' => ''],
        ];
    }

    #[On('openManageSpectech')]
    public function open(string $tab = 'manual'): void
    {
        if (in_array($tab, ['manual', 'import'], true)) {
            $this->manageTab = $tab;
        }

        Flux::modal('manageSpectech')->show();
    }

    /**
     * @return array<string, string>
     */
    protected function draftRules(): array
    {
        return [
            'draftType'     => 'required|in:hardware,software',
            'draftName'     => 'required|string|min:3|max:120',
            'draftQuantity' => 'required|integer|min:1',
            'draftPrice'    => 'required',
            'draftNote'     => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function draftMessages(): array
    {
        return [
            'draftName.required'     => 'Nama spektek wajib diisi.',
            'draftName.min'          => 'Nama spektek minimal 3 karakter.',
            'draftQuantity.required' => 'Jumlah wajib diisi.',
            'draftQuantity.min'      => 'Jumlah minimal 1.',
            'draftPrice.required'    => 'Total harga wajib diisi.',
        ];
    }

    public function addDraft(): void
    {
        $this->validate($this->draftRules(), $this->draftMessages());

        $this->drafts[] = [
            'uid'      => uniqid('draft_', true),
            'name'     => $this->draftName,
            'type'     => $this->draftType,
            'quantity' => (int) $this->draftQuantity,
            'price'    => (int) preg_replace('/[^0-9]/', '', (string) $this->draftPrice),
            'note'     => $this->draftNote,
        ];

        $keepType = $this->draftType;
        $this->reset('draftName', 'draftPrice', 'draftQuantity', 'draftNote');
        $this->draftType = $keepType;
        $this->resetErrorBag();

        $this->dispatch('draft-added');
    }

    public function removeDraft(string $uid): void
    {
        $this->drafts = array_values(array_filter(
            $this->drafts,
            fn (array $item): bool => $item['uid'] !== $uid,
        ));
    }

    public function clearDrafts(): void
    {
        $this->drafts = [];
    }

    /**
     * Kirim seluruh draft sekaligus lewat API bulk.
     */
    public function save(): void
    {
        if (empty($this->drafts)) {
            Toaster::error('Belum ada spektek untuk disimpan');
            return;
        }

        $payload = array_map(fn (array $item): array => [
            'name'          => $item['name'],
            'type'          => $item['type'],
            'qty_total'     => $item['quantity'],
            'qty_recived'   => 0,
            'total_nominal' => $item['price'],
            'note'          => $item['note'],
            'project_id'    => (int) $this->id,
        ], $this->drafts);

        $result = app(ProjectWriter::class)->bulkSpectech((int) $this->id, $payload);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []) ?: 'Gagal menyimpan spektek');
            return;
        }

        $count = count($this->drafts);
        $this->finishMutation();

        Toaster::success($count.' spektek berhasil ditambahkan');
        Flux::modal('manageSpectech')->close();
    }

    /**
     * Terima hasil parsing Excel dari frontend (array object) lalu kirim ke
     * endpoint bulk yang sama dengan input manual. Tetap divalidasi di server
     * sebagai lapisan pertahanan kedua. Return bool agar frontend tahu sukses.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function importParsed(array $rows): bool
    {
        $validator = Validator::make(['rows' => $rows], [
            'rows'                 => 'required|array|min:1',
            'rows.*.name'          => 'required|string|max:120',
            'rows.*.type'          => 'required|in:hardware,software',
            'rows.*.qty_total'     => 'required|integer|min:1',
            'rows.*.total_nominal' => 'required|numeric|min:0',
            'rows.*.note'          => 'nullable|string|max:500',
        ], [], [
            'rows' => 'data',
        ]);

        if ($validator->fails()) {
            Toaster::error('Data tidak valid: '.$validator->errors()->first());
            return false;
        }

        $payload = array_map(fn (array $row): array => [
            'name'          => $row['name'],
            'type'          => $row['type'],
            'qty_total'     => (int) $row['qty_total'],
            'qty_recived'   => 0,
            'total_nominal' => (int) $row['total_nominal'],
            'note'          => $row['note'] ?? null,
            'project_id'    => (int) $this->id,
        ], $rows);

        $result = app(ProjectWriter::class)->bulkSpectech((int) $this->id, $payload);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []) ?: 'Gagal mengimpor spektek');
            return false;
        }

        $count = count($payload);
        $this->finishMutation();

        Toaster::success($count.' spektek berhasil diimpor');
        Flux::modal('manageSpectech')->close();

        return true;
    }

    protected function finishMutation(): void
    {
        $this->reset('drafts', 'draftName', 'draftPrice', 'draftQuantity', 'draftNote');
        $this->draftType = 'hardware';
        $this->manageTab = 'manual';
        $this->resetErrorBag();
        $this->dispatch('excel-import-reset');

        app(ProjectCache::class)->flushSpectech($this->id);
        $this->dispatch('spectechSaved');
    }

    public function resetManage(): void
    {
        $this->reset('drafts', 'draftName', 'draftPrice', 'draftQuantity', 'draftNote');
        $this->draftType = 'hardware';
        $this->manageTab = 'manual';
        $this->resetErrorBag();
        $this->dispatch('excel-import-reset');
    }

    #[Computed]
    public function draftTotal(): int
    {
        return collect($this->drafts)->sum(fn (array $i): int => $i['price']);
    }
}; ?>

<div>
    <flux:modal name="manageSpectech" wire:close="resetManage" class="md:min-w-3xl lg:min-w-5xl">
        <div class="space-y-6">
            {{-- Header --}}
            <div class="space-y-1">
                <flux:heading size="lg">Kelola Spektek</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Susun beberapa item sekaligus, lalu simpan dalam satu langkah — atau impor dari file excel.
                </flux:text>
            </div>

            {{-- Tab switcher --}}
            <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-1 grid grid-cols-2 gap-1">
                @foreach ([
                    ['key' => 'manual', 'label' => 'Input Manual', 'icon' => 'pencil-square'],
                    ['key' => 'import', 'label' => 'Import Excel', 'icon' => 'arrow-up-tray'],
                ] as $tab)
                    <button type="button"
                        wire:click="$set('manageTab', '{{ $tab['key'] }}')"
                        @class([
                            'flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition cursor-pointer',
                            'bg-white text-red-700 ring-1 ring-inset ring-red-200 shadow-sm' => $manageTab === $tab['key'],
                            'text-zinc-600 hover:bg-white/60' => $manageTab !== $tab['key'],
                        ])>
                        <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                        <span>{{ $tab['label'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- ============ MANUAL INPUT ============ --}}
            <div x-show="$wire.manageTab === 'manual'" class="space-y-5">
                {{-- Quick-add form --}}
                <form wire:submit="addDraft" class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 space-y-3"
                    x-data="{ showNote: false }"
                    x-on:draft-added.window="$refs.draftName?.focus(); showNote = false">
                    <div class="grid grid-cols-2 md:grid-cols-12 gap-3">
                        <flux:field class="col-span-2 md:col-span-5">
                            <flux:label badge="Wajib">Nama spektek</flux:label>
                            <flux:input wire:model="draftName" x-ref="draftName" placeholder="cth. Pipa PVC 4 inch" />
                            <flux:error name="draftName" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label>Tipe</flux:label>
                            <flux:select wire:model="draftType">
                                <flux:select.option value="hardware">Barang</flux:select.option>
                                <flux:select.option value="software">Aplikasi</flux:select.option>
                            </flux:select>
                            <flux:error name="draftType" />
                        </flux:field>

                        <flux:field class="md:col-span-2">
                            <flux:label badge="Wajib">Jumlah</flux:label>
                            <flux:input wire:model="draftQuantity" type="number" min="1" placeholder="0" />
                            <flux:error name="draftQuantity" />
                        </flux:field>

                        <flux:field class="col-span-2 md:col-span-3">
                            <flux:label badge="Wajib">Total Harga</flux:label>
                            <x-rupiah-input model="draftPrice" placeholder="0" />
                            <flux:error name="draftPrice" />
                        </flux:field>
                    </div>

                    <div x-show="showNote" x-collapse>
                        <flux:field>
                            <flux:label>Catatan</flux:label>
                            <flux:input wire:model="draftNote" placeholder="Catatan tambahan (opsional)" />
                            <flux:error name="draftNote" />
                        </flux:field>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <button type="button" @click="showNote = !showNote"
                            class="text-xs font-medium text-zinc-500 hover:text-zinc-800 cursor-pointer">
                            <span x-show="!showNote">+ Tambah catatan</span>
                            <span x-show="showNote" x-cloak>&minus; Sembunyikan catatan</span>
                        </button>
                        <div class="flex items-center gap-3">
                            <flux:text class="hidden sm:block text-xs text-zinc-400">
                                Enter untuk tambah cepat
                            </flux:text>
                            <flux:button type="submit" variant="primary" icon="plus" size="sm">
                                Tambah ke daftar
                            </flux:button>
                        </div>
                    </div>
                </form>

                {{-- Draft list --}}
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm" class="font-medium text-zinc-900">
                            Daftar antrean
                            <span class="text-zinc-400 font-normal">({{ count($drafts) }})</span>
                        </flux:heading>
                        @if(count($drafts) > 0)
                            <button type="button" wire:click="clearDrafts"
                                class="text-xs text-zinc-500 hover:text-zinc-800 font-medium cursor-pointer">
                                Bersihkan semua
                            </button>
                        @endif
                    </div>

                    @if(count($drafts) > 0)
                        <div class="border border-zinc-200 rounded-xl overflow-hidden">
                            <div class="max-h-[40vh] overflow-y-auto">
                                <table class="w-full text-sm">
                                    <thead class="bg-zinc-50 sticky top-0 z-10">
                                        <tr class="text-left text-[11px] uppercase tracking-wide text-zinc-500">
                                            <th class="px-4 py-2.5 font-medium">Nama</th>
                                            <th class="px-3 py-2.5 font-medium w-24">Tipe</th>
                                            <th class="px-3 py-2.5 font-medium w-16 text-right">Qty</th>
                                            <th class="px-4 py-2.5 font-medium w-36 text-right">Total</th>
                                            <th class="px-3 py-2.5 font-medium w-10"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100">
                                        @foreach($drafts as $draft)
                                            <tr wire:key="draft-{{ $draft['uid'] }}" class="hover:bg-zinc-50/60">
                                                <td class="px-4 py-2.5 align-top">
                                                    <p class="font-medium text-zinc-900">{{ $draft['name'] }}</p>
                                                    @if(!empty($draft['note']))
                                                        <p class="text-xs text-zinc-500 mt-0.5 truncate max-w-xs">{{ $draft['note'] }}</p>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2.5 align-top">
                                                    <span @class([
                                                        'inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded-full ring-1 ring-inset',
                                                        'bg-blue-50 text-blue-700 ring-blue-600/20' => $draft['type'] === 'hardware',
                                                        'bg-purple-50 text-purple-700 ring-purple-600/20' => $draft['type'] === 'software',
                                                    ])>
                                                        {{ $draft['type'] === 'software' ? 'Aplikasi' : 'Barang' }}
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-right text-zinc-700 font-medium">
                                                    {{ $draft['quantity'] }}
                                                </td>
                                                <td class="px-4 py-2.5 align-top text-right text-zinc-900 font-medium">
                                                    Rp {{ number_format($draft['price'], 0, ',', '.') }}
                                                </td>
                                                <td class="px-3 py-2.5 align-top text-right">
                                                    <flux:button wire:click="removeDraft('{{ $draft['uid'] }}')"
                                                        variant="ghost" size="xs" icon="trash"
                                                        class="text-zinc-400 hover:text-red-600!" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="bg-zinc-50 border-t border-zinc-200">
                                        <tr>
                                            <td colspan="3" class="px-4 py-2.5 text-xs font-medium text-zinc-500 uppercase tracking-wide">
                                                Total Nominal
                                            </td>
                                            <td class="px-4 py-2.5 text-right text-sm font-semibold text-red-600">
                                                Rp {{ number_format($this->draftTotal, 0, ',', '.') }}
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="border border-dashed border-zinc-200 rounded-xl p-8 text-center">
                            <div class="mx-auto w-10 h-10 rounded-full bg-zinc-100 flex items-center justify-center">
                                <flux:icon.queue-list class="w-5 h-5 text-zinc-400" />
                            </div>
                            <flux:text class="text-sm text-zinc-500 mt-3">
                                Belum ada item. Isi formulir di atas lalu tekan <span class="font-medium text-zinc-700">"Tambah ke daftar"</span>.
                            </flux:text>
                        </div>
                    @endif
                </div>

                {{-- Footer actions --}}
                <div class="flex items-center justify-between gap-2 border-t border-zinc-100 pt-4">
                    <flux:text class="text-xs text-zinc-500">
                        {{ count($drafts) }} item akan dikirim sekaligus.
                    </flux:text>
                    <div class="flex gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">Tutup</flux:button>
                        </flux:modal.close>
                        <flux:button wire:click="save" variant="primary" icon="check"
                            :disabled="count($drafts) === 0"
                            wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save">Simpan Semua</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </flux:button>
                    </div>
                </div>
            </div>

            {{-- ============ IMPORT EXCEL ============ --}}
            <div x-show="$wire.manageTab === 'import'" class="space-y-5" x-cloak>
                <x-excel-import
                    :columns="$this->importColumns()"
                    :example="$this->importExample()"
                    template-name="Template Spektek.xlsx"
                    on-import="importParsed"
                    submit-label="Import Spektek"
                />
            </div>
        </div>
    </flux:modal>
</div>
