<?php

use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new class extends Component {
    use WithFileUploads;

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

    public $importFile = null;

    #[On('openManageSpectech')]
    public function open(): void
    {
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

        $payload = [
            'project_id' => $this->id,
            'items'      => array_map(fn (array $item): array => [
                'name'          => $item['name'],
                'qty_total'     => $item['quantity'],
                'total_nominal' => $item['price'],
                'type'          => $item['type'],
            ], $this->drafts),
        ];

        // TODO: endpoint bulk belum tersedia di BEPM. Payload sudah disiapkan
        // sesuai kontrak per-item agar tinggal dihubungkan saat API siap.
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
     * Unggah file excel; parsing & validasi baris dilakukan di backend.
     */
    public function import(): void
    {
        $this->validate(
            ['importFile' => 'required|file|mimes:xlsx,xls,csv|max:5120'],
            [
                'importFile.required' => 'Pilih file excel terlebih dahulu.',
                'importFile.mimes'    => 'Format harus .xlsx, .xls, atau .csv.',
                'importFile.max'      => 'Ukuran file maksimal 5MB.',
            ],
        );

        // TODO: endpoint import belum tersedia. Backend menerima file mentah,
        // melakukan parsing baris & membuat spektek secara massal.
        $result = app(ProjectWriter::class)->importSpectech((int) $this->id, [
            'contents' => $this->importFile->get(),
            'name' => $this->importFile->getClientOriginalName(),
        ]);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []) ?: 'Gagal mengimpor file');
            return;
        }

        $this->reset('importFile');
        $this->finishMutation();

        Toaster::success('Spektek berhasil diimpor');
        Flux::modal('manageSpectech')->close();
    }

    protected function finishMutation(): void
    {
        $this->reset('drafts', 'draftName', 'draftPrice', 'draftQuantity', 'draftNote', 'importFile');
        $this->draftType = 'hardware';
        $this->manageTab = 'manual';
        $this->resetErrorBag();

        app(ProjectCache::class)->flushSpectech($this->id);
        $this->dispatch('spectechSaved');
    }

    public function resetManage(): void
    {
        $this->reset('drafts', 'draftName', 'draftPrice', 'draftQuantity', 'draftNote', 'importFile');
        $this->draftType = 'hardware';
        $this->manageTab = 'manual';
        $this->resetErrorBag();
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
                <form wire:submit="addDraft" class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 space-y-4"
                    x-data x-on:draft-added.window="$refs.draftName?.focus()">
                    <flux:field>
                        <flux:label>Tipe spektek</flux:label>
                        <div class="bg-white border border-zinc-200 rounded-xl p-1 grid grid-cols-2 gap-1">
                            @foreach ([
                                ['key' => 'hardware', 'label' => 'Barang', 'icon' => 'cube'],
                                ['key' => 'software', 'label' => 'Aplikasi', 'icon' => 'computer-desktop'],
                            ] as $typeTab)
                                <button type="button"
                                    wire:click="$set('draftType', '{{ $typeTab['key'] }}')"
                                    @class([
                                        'flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition cursor-pointer',
                                        'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' => $draftType === $typeTab['key'],
                                        'text-zinc-600 hover:bg-zinc-50' => $draftType !== $typeTab['key'],
                                    ])>
                                    <flux:icon name="{{ $typeTab['icon'] }}" class="w-4 h-4" />
                                    <span>{{ $typeTab['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                        <flux:error name="draftType" />
                    </flux:field>

                    <flux:field>
                        <flux:label badge="Wajib">Nama spektek</flux:label>
                        <flux:input wire:model="draftName" x-ref="draftName" placeholder="cth. Pipa PVC 4 inch" />
                        <flux:error name="draftName" />
                    </flux:field>

                    <div class="grid grid-cols-3 gap-3">
                        <flux:field class="col-span-2">
                            <flux:label badge="Wajib">Total Harga</flux:label>
                            <x-rupiah-input model="draftPrice" placeholder="0" />
                            <flux:error name="draftPrice" />
                        </flux:field>

                        <flux:field>
                            <flux:label badge="Wajib">Jumlah</flux:label>
                            <flux:input wire:model="draftQuantity" type="number" min="1" placeholder="0" />
                            <flux:error name="draftQuantity" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>Catatan</flux:label>
                        <flux:textarea wire:model="draftNote" rows="2" placeholder="Catatan tambahan (opsional)" />
                        <flux:error name="draftNote" />
                    </flux:field>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="filled" icon="plus" size="sm">
                            Tambah ke daftar
                        </flux:button>
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
                <form wire:submit="import" class="space-y-5">
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 space-y-3">
                        <div class="flex gap-2">
                            <flux:icon.information-circle class="w-5 h-5 text-blue-500 shrink-0 mt-0.5" />
                            <flux:text class="text-xs text-zinc-600 leading-relaxed">
                                Unggah file <span class="font-medium text-zinc-800">.xlsx</span>, <span class="font-medium text-zinc-800">.xls</span>, atau
                                <span class="font-medium text-zinc-800">.csv</span>. Sistem akan membaca & memvalidasi setiap baris secara otomatis.
                            </flux:text>
                        </div>
                    </div>

                    <flux:field>
                        <flux:label badge="Wajib">File excel</flux:label>
                        <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                            class="block w-full text-sm text-zinc-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-red-50 file:text-red-700 hover:file:bg-red-100 file:cursor-pointer cursor-pointer" />
                        <flux:error name="importFile" />

                        <div wire:loading wire:target="importFile" class="mt-2">
                            <flux:text class="text-xs text-zinc-500">Mengunggah file...</flux:text>
                        </div>

                        @if($importFile)
                            <div class="mt-3 flex items-center gap-2 rounded-lg bg-emerald-50 border border-emerald-100 p-3">
                                <flux:icon.document-check class="w-5 h-5 text-emerald-600 shrink-0" />
                                <flux:text class="text-sm text-emerald-800 truncate">
                                    {{ $importFile->getClientOriginalName() }}
                                </flux:text>
                            </div>
                        @endif
                    </flux:field>

                    <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4">
                        <flux:modal.close>
                            <flux:button variant="ghost">Tutup</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="primary" icon="arrow-up-tray"
                            wire:loading.attr="disabled" wire:target="import,importFile">
                            <span wire:loading.remove wire:target="import">Import</span>
                            <span wire:loading wire:target="import">Mengimpor...</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </flux:modal>
</div>
