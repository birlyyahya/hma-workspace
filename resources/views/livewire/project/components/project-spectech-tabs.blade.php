<?php

use App\Livewire\Forms\SpectechForm;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public SpectechForm $form;

    public $totalproject;
    public $progress;
    public array $spectech = [];
    public bool $loadingSpectech = false;
    public $id;
    public int $ppn = 0;

    public ?int $deletingId = null;
    public ?string $deletingName = null;

    public string $activeType = 'hardware';
    public bool $bulkMode = false;
    public array $selectedIds = [];

    /**
     * @var array<int, array{qty_recived: int, note: string}>
     */
    public array $bulkChanges = [];

    public string $search = '';
    public string $statusFilter = 'all';

    public function placeholder()
    {
        return view('components.placeholder.ph_project_spectech_tabs');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mapItem(array $data): array
    {
        return [
            'id'            => $data['id'],
            'name'          => $data['name'],
            'qty_total'     => $data['qty_total'],
            'qty_recived'   => $data['qty_recived'],
            'total_nominal' => $data['total_nominal'],
            'qty_nominal'   => $data['qty_nominal'],
            'percentage'    => $data['percentage'],
            'note'          => $data['note'],
            'images'        => $data['images'] ?? [],
            // Field type belum tersedia dari API; default hardware sampai backend menambahkan kolom ini.
            'type'          => $data['type'] ?? 'hardware',
        ];
    }

    protected function afterMutation(): void
    {
        Cache::forget('project_data_show_'.$this->id);
        $this->dispatch('projectLoad');
    }

    protected function logApiFailure(string $context, array $response): void
    {
        Log::error('Spectech API failed: '.$context, [
            'status' => $response['status']  ?? null,
            'body'   => $response['message'] ?? 'No message',
            'error'  => $response['errors']  ?? 'No error',
        ]);
    }

    public function confirmDelete(int $id): void
    {
        $item = collect($this->spectech)->firstWhere('id', $id);

        if (! $item) {
            Toaster::error('Spectech tidak ditemukan');
            return;
        }

        $this->deletingId = $id;
        $this->deletingName = $item['name'];
        Flux::modal('deleteSpectech')->show();
    }

    public function deleteSpectech(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $id = $this->deletingId;

        try {
            Http::delete(rtrim((string) config('services.api_project'), '/').'/activity-categories/'.$id);

            $this->spectech = collect($this->spectech)
                ->reject(fn ($item) => (int) $item['id'] === $id)
                ->values()
                ->all();

            $this->afterMutation();
            Toaster::success('Spectech berhasil dihapus');
        } catch (\Throwable $e) {
            Toaster::error('Gagal menghapus spectech');
            Log::error('Failed to delete spectech', ['id' => $id, 'error' => $e->getMessage()]);
        }

        $this->reset('deletingId', 'deletingName');
        Flux::modal('deleteSpectech')->close();
    }

    public function create(): void
    {
        $response = $this->form->store((int) $this->id);

        if (($response['status'] ?? null) !== 201) {
            Toaster::error(getErrorMessages($response['errors'] ?? []));
            $this->logApiFailure('create', $response->json() ?? []);
            return;
        }

        $this->spectech[] = $this->mapItem($response['data']);
        $this->form->reset();
        $this->afterMutation();

        Toaster::success('Spectech berhasil ditambahkan');
        Flux::modal('addSpectech')->close();
    }

    public function editSpectech(int $id): void
    {
        $item = collect($this->spectech)->firstWhere('id', $id);

        if (! $item) {
            Toaster::error('Spectech tidak ditemukan');
            return;
        }

        $this->form->setUpdate($item);
        Flux::modal('editSpectech')->show();
    }

    public function update(): void
    {
        $response = $this->form->update();

        if (($response['status'] ?? null) !== 200) {
            Toaster::error(getErrorMessages($response['errors'] ?? []));
            $this->logApiFailure('update', $response->json() ?? []);
            return;
        }

        $updated = $this->mapItem($response['data']);
        $this->spectech = collect($this->spectech)
            ->map(fn ($item) => (int) $item['id'] === (int) $updated['id'] ? $updated : $item)
            ->all();

        $this->form->reset();
        $this->afterMutation();

        Toaster::success('Spectech berhasil diperbarui');
        Flux::modal('editSpectech')->close();
    }

    #[On('updateProgress')]
    public function updateProgress($progress): void
    {
        $this->progress = $progress;
    }

    public function resetForm(): void
    {
        $this->form->reset();
        $this->resetErrorBag();
    }

    public function setType(string $type): void
    {
        if (! in_array($type, ['hardware', 'software'], true)) {
            return;
        }

        $this->activeType = $type;
        $this->selectedIds = [];
    }

    public function toggleBulkMode(): void
    {
        $this->bulkMode = ! $this->bulkMode;
        $this->selectedIds = [];
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_filter(
                $this->selectedIds,
                fn ($x) => (int) $x !== $id,
            ));

            return;
        }

        $this->selectedIds[] = $id;
    }

    public function selectAllVisible(): void
    {
        $this->selectedIds = collect($this->filteredSpectech)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    public function toggleSelectAllVisible(): void
    {
        $visibleIds = collect($this->filteredSpectech)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->all();

        $current = array_map('intval', $this->selectedIds);
        $allSelected = ! empty($visibleIds) && empty(array_diff($visibleIds, $current));

        $this->selectedIds = $allSelected ? [] : $visibleIds;
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function openBulkEdit(): void
    {
        if (empty($this->selectedIds)) {
            Toaster::error('Pilih minimal satu spektek');
            return;
        }

        $this->bulkChanges = [];
        $byId = collect($this->spectech)->keyBy('id');

        foreach ($this->selectedIds as $id) {
            $item = $byId->get($id);

            if (! $item) {
                continue;
            }

            $this->bulkChanges[(int) $id] = [
                'qty_recived' => (int) ($item['qty_recived'] ?? 0),
                'note'        => (string) ($item['note'] ?? ''),
            ];
        }

        Flux::modal('bulkEditSpectech')->show();
    }

    public function applyBulkUpdate(): void
    {
        // TODO: integrasi API bulk update belum tersedia. Untuk sementara perubahan
        // hanya diterapkan ke local state agar UI dapat diuji secara end-to-end.
        $count = count($this->bulkChanges);

        $this->spectech = collect($this->spectech)->map(function (array $item) {
            $id = (int) $item['id'];

            if (! isset($this->bulkChanges[$id])) {
                return $item;
            }

            $changes = $this->bulkChanges[$id];
            $qtyTotal = (int) ($item['qty_total'] ?? 0);
            $qtyRecv = max(0, min((int) $changes['qty_recived'], $qtyTotal));

            $item['qty_recived'] = $qtyRecv;
            $item['note'] = (string) $changes['note'];
            $item['percentage'] = $qtyTotal > 0 ? round(($qtyRecv / $qtyTotal) * 100, 2) : 0;

            return $item;
        })->all();

        $this->resetBulk();
        $this->afterMutation();

        Toaster::success($count.' spektek berhasil diperbarui');
        Flux::modal('bulkEditSpectech')->close();
    }

    public function confirmBulkDelete(): void
    {
        if (empty($this->selectedIds)) {
            Toaster::error('Pilih minimal satu spektek');
            return;
        }

        Flux::modal('bulkDeleteSpectech')->show();
    }

    public function bulkDelete(): void
    {
        // TODO: integrasi API bulk delete belum tersedia.
        $ids = array_map('intval', $this->selectedIds);
        $count = count($ids);

        $this->spectech = collect($this->spectech)
            ->reject(fn ($item) => in_array((int) $item['id'], $ids, true))
            ->values()
            ->all();

        $this->resetBulk();
        $this->afterMutation();

        Toaster::success($count.' spektek berhasil dihapus');
        Flux::modal('bulkDeleteSpectech')->close();
    }

    public function resetBulk(): void
    {
        $this->bulkMode = false;
        $this->selectedIds = [];
        $this->bulkChanges = [];
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'statusFilter');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function statusOf(array $item): string
    {
        $qtyRecv = (int) ($item['qty_recived'] ?? 0);
        $percentage = (float) ($item['percentage'] ?? 0);

        if ($percentage >= 100) {
            return 'completed';
        }

        return $qtyRecv > 0 ? 'in_progress' : 'not_started';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function filteredSpectech(): array
    {
        $needle = trim(mb_strtolower($this->search));

        return collect($this->spectech)
            ->filter(fn ($item) => ($item['type'] ?? 'hardware') === $this->activeType)
            ->when($needle !== '', fn ($items) => $items->filter(
                fn ($item) => str_contains(mb_strtolower((string) ($item['name'] ?? '')), $needle),
            ))
            ->when($this->statusFilter !== 'all', fn ($items) => $items->filter(
                fn ($item) => $this->statusOf($item) === $this->statusFilter,
            ))
            ->values()
            ->all();

    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return trim($this->search) !== '' || $this->statusFilter !== 'all';
    }

    /**
     * @return array{hardware: int, software: int}
     */
    #[Computed]
    public function typeCounts(): array
    {
        $items = collect($this->spectech);

        return [
            'hardware' => $items->filter(fn ($i) => ($i['type'] ?? 'hardware') === 'hardware')->count(),
            'software' => $items->filter(fn ($i) => ($i['type'] ?? 'hardware') === 'software')->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function selectedSpectech(): array
    {
        $ids = array_map('intval', $this->selectedIds);

        return collect($this->spectech)
            ->filter(fn ($item) => in_array((int) $item['id'], $ids, true))
            ->values()
            ->all();
    }

    #[Computed]
    public function nilaiDiterima(): float
    {
        $total = collect($this->spectech)->sum(
            fn ($item) => (float) $item['qty_nominal'] * (float) $item['qty_recived']
        );

        return $total * (1 + ($this->ppn / 100));
    }

    #[Computed]
    public function totalItems(): int
    {
        return count($this->spectech);
    }

}; ?>

<div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ============ LEFT: SPECTECH LIST ============ --}}
        <div class="space-y-4 lg:col-span-3">
            {{-- List header --}}
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <flux:heading size="lg" class="font-semibold text-zinc-900">Daftar Spektek</flux:heading>
                    <flux:text class="text-sm text-zinc-500">
                        @if($bulkMode && count($selectedIds) > 0)
                            <span class="text-red-600 font-medium">{{ count($selectedIds) }} dipilih</span>
                            <span class="text-zinc-400">·</span>
                        @endif
                        {{ $this->totalItems }} item terdaftar
                    </flux:text>
                </div>
                <div class="flex items-center gap-2">
                    @if($bulkMode)
                        <flux:button wire:click="toggleBulkMode" variant="ghost" size="sm" icon="x-mark">
                            Selesai
                        </flux:button>
                    @else
                        <flux:button class="!hidden" wire:click="toggleBulkMode" variant="ghost" size="sm" icon="check-circle"
                            :disabled="$this->totalItems === 0">
                            Pilih
                        </flux:button>
                        <flux:modal.trigger name="addSpectech">
                            <flux:button variant="primary" icon="plus" size="sm">Tambah</flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            </div>

            {{-- Tabs horizontal: Hardware / Software --}}
            <div class="bg-white border border-zinc-200 rounded-xl p-1 grid grid-cols-2 gap-1">
                @foreach ([
                    ['key' => 'hardware', 'label' => 'Spektek Barang', 'icon' => 'cube', 'count' => $this->typeCounts['hardware']],
                    ['key' => 'software', 'label' => 'Spektek Aplikasi', 'icon' => 'computer-desktop', 'count' => $this->typeCounts['software']],
                ] as $tab)
                    <button type="button"
                        wire:click="setType('{{ $tab['key'] }}')"
                        @class([
                            'flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium transition cursor-pointer',
                            'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' => $activeType === $tab['key'],
                            'text-zinc-600 hover:bg-zinc-50' => $activeType !== $tab['key'],
                        ])>
                        <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                        <span>{{ $tab['label'] }}</span>
                        <span @class([
                            'inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-[11px] font-semibold rounded-full',
                            'bg-red-100 text-red-700' => $activeType === $tab['key'],
                            'bg-zinc-100 text-zinc-600' => $activeType !== $tab['key'],
                        ])>{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Search + Status filter --}}
            <div class="flex items-center gap-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Cari nama spektek..."
                    size="sm"
                    class="flex-1"
                    clearable
                />
                <flux:select wire:model.live="statusFilter" size="sm" class="w-44 shrink-0">
                    <flux:select.option value="all">Semua status</flux:select.option>
                    <flux:select.option value="not_started">Belum Mulai</flux:select.option>
                    <flux:select.option value="in_progress">Berjalan</flux:select.option>
                    <flux:select.option value="completed">Selesai</flux:select.option>
                </flux:select>
                @if($this->hasActiveFilters)
                    <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark"
                        tooltip="Bersihkan filter" />
                @endif
            </div>

            {{-- Bulk select-all toolbar --}}
            @if($bulkMode && !$this->loadingSpectech && count($this->filteredSpectech) > 0)
                @php $visibleCount = count($this->filteredSpectech); @endphp
                <div class="flex items-center justify-between bg-zinc-50 border border-zinc-200 rounded-lg px-4 py-2.5">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-zinc-700">
                        <input type="checkbox"
                            class="rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                            x-on:click.prevent="$wire.toggleSelectAllVisible()"
                            x-bind:checked="$wire.selectedIds.length === {{ $visibleCount }} && {{ $visibleCount }} > 0"
                        />
                        <span>Pilih semua di tab ini</span>
                    </label>
                    @if(count($selectedIds) > 0)
                        <button type="button" wire:click="clearSelection"
                            class="text-xs text-zinc-500 hover:text-zinc-800 font-medium cursor-pointer">
                            Bersihkan pilihan
                        </button>
                    @endif
                </div>
            @endif

            {{-- Loading state --}}
            @if($this->loadingSpectech)
                @for ($i = 0; $i < 3; $i++)
                    <div class="bg-white border border-zinc-200 rounded-xl p-6 animate-pulse">
                        <div class="flex items-start justify-between">
                            <div class="space-y-2 w-full">
                                <div class="h-4 bg-zinc-200 rounded w-1/3"></div>
                                <div class="h-3 bg-zinc-100 rounded w-1/2"></div>
                            </div>
                            <div class="h-6 w-6 bg-zinc-100 rounded"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-3 mt-5">
                            <div class="h-16 bg-zinc-100 rounded-lg"></div>
                            <div class="h-16 bg-zinc-100 rounded-lg"></div>
                            <div class="h-16 bg-zinc-100 rounded-lg"></div>
                        </div>
                        <div class="mt-5 h-3 bg-zinc-100 rounded-full"></div>
                    </div>
                @endfor
            @else
                @forelse ($this->filteredSpectech as $data)
                    @php
                        $qtyRecv = (int) ($data['qty_recived'] ?? 0);
                        $qtyTotal = (int) ($data['qty_total'] ?? 0);
                        $percentage = (float) ($data['percentage'] ?? 0);
                        $isComplete = $percentage >= 100;
                        $isStarted = $qtyRecv > 0;
                        $statusLabel = $isComplete ? 'Selesai' : ($isStarted ? 'Berjalan' : 'Belum Mulai');
                        $statusColor = $isComplete
                            ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                            : ($isStarted ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-zinc-50 text-zinc-600 ring-zinc-500/20');
                        $isSelected = in_array((int) $data['id'], array_map('intval', $selectedIds), true);
                    @endphp

                    <div wire:key="spectech-card-{{ $data['id'] }}"
                         @class([
                            'group bg-white border rounded-xl p-6 transition',
                            'border-red-300 ring-1 ring-red-200 shadow-sm' => $bulkMode && $isSelected,
                            'border-zinc-200 hover:border-zinc-300 hover:shadow-sm' => !($bulkMode && $isSelected),
                            'cursor-pointer' => $bulkMode,
                         ])
                         @if($bulkMode) wire:click="toggleSelect({{ $data['id'] }})" @endif>
                        {{-- Header --}}
                        <div class="flex items-start justify-between gap-4">
                            @if($bulkMode)
                                <div class="pt-1 shrink-0">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                        x-bind:checked="$wire.selectedIds.map(Number).includes({{ (int) $data['id'] }})"
                                        x-on:click.stop.prevent="$wire.toggleSelect({{ (int) $data['id'] }})"
                                    />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-base font-semibold text-zinc-900 truncate">
                                        {{ $data['name'] }}
                                    </h3>
                                    {{-- <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full ring-1 ring-inset {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span> --}}
                                </div>
                                <p class="mt-1 text-xs text-zinc-500">
                                    Harga satuan: <span class="font-medium text-zinc-700">Rp {{ number_format($data['qty_nominal'] ?? 0, 0, ',', '.') }}</span>
                                </p>
                            </div>

                            @unless($bulkMode)
                                <flux:dropdown wire:key="spectech-menu-{{ $data['id'] }}">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-zinc-400" />
                                    <flux:navmenu>
                                        <flux:navmenu.item icon="pencil-square" wire:click="editSpectech({{ $data['id'] }})">Edit</flux:navmenu.item>
                                        <flux:navmenu.item icon="trash" variant="danger"
                                            wire:click="confirmDelete({{ $data['id'] }})">Hapus</flux:navmenu.item>
                                    </flux:navmenu>
                                </flux:dropdown>
                            @endunless
                        </div>

                        {{-- Stats grid --}}
                        <div class="grid grid-cols-2 gap-3 mt-5">
                            <div class="rounded-lg bg-zinc-50 p-3">
                                <p class="text-[11px] uppercase tracking-wide text-zinc-500">Quantity</p>
                                <p class="mt-1 text-sm font-semibold text-zinc-900">
                                    {{ $qtyTotal }}
                                    {{-- <span class="text-zinc-400 font-normal"></span> --}}
                                </p>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-3">
                                <p class="text-[11px] uppercase tracking-wide text-zinc-500">Total Nominal</p>
                                <p class="mt-1 text-sm font-semibold text-zinc-900 truncate">
                                    Rp {{ number_format(($data['total_nominal'] ?? 0), 0, ',', '.') }}
                                </p>
                            </div>
                        </div>

                        {{-- Progress --}}
                        {{-- <div class="mt-5">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-medium text-zinc-500">Progress Penerimaan</span>
                                <span class="text-xs font-semibold {{ $isComplete ? 'text-emerald-700' : 'text-zinc-800' }}">
                                    {{ number_format($percentage, 0) }}%
                                </span>
                            </div>
                            <div class="w-full h-2 bg-zinc-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $isComplete ? 'bg-emerald-600' : 'bg-red-600' }} rounded-full transition-all"
                                     style="width: {{ min($percentage, 100) }}%"></div>
                            </div>
                        </div> --}}

                        {{-- Note --}}
                        @if(!empty($data['note']))
                            <div class="mt-4 flex gap-2 rounded-lg bg-amber-50/60 border border-amber-100 p-3">
                                <flux:icon.information-circle class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                <p class="text-xs text-amber-900 leading-relaxed">{{ $data['note'] }}</p>
                            </div>
                        @endif

                        {{-- Images --}}
                        @if(!empty($data['images']) && is_array($data['images']))
                            <div class="mt-4 flex gap-2 flex-wrap">
                                @foreach (array_slice($data['images'], 0, 4) as $img)
                                    <img src="{{ is_array($img) ? ($img['url'] ?? '') : $img }}"
                                         class="w-14 h-14 rounded-lg object-cover ring-1 ring-zinc-200" alt="" />
                                @endforeach
                                @if(count($data['images']) > 4)
                                    <div class="w-14 h-14 rounded-lg bg-zinc-100 ring-1 ring-zinc-200 flex items-center justify-center text-xs text-zinc-600 font-medium">
                                        +{{ count($data['images']) - 4 }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    @if($this->hasActiveFilters)
                        {{-- No-results state --}}
                        <div class="bg-white border border-dashed border-zinc-200 rounded-xl p-12 text-center">
                            <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center">
                                <flux:icon.magnifying-glass class="w-6 h-6 text-zinc-400" />
                            </div>
                            <flux:heading size="md" class="mt-4 text-zinc-900">Tidak ada hasil</flux:heading>
                            <flux:text class="text-sm text-zinc-500 mt-1">
                                Tidak ada spektek yang cocok dengan pencarian atau filter saat ini.
                            </flux:text>
                            <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark" class="mt-4">
                                Bersihkan filter
                            </flux:button>
                        </div>
                    @else
                        {{-- Empty state --}}
                        <div class="bg-white border border-dashed border-zinc-200 rounded-xl p-12 text-center">
                            <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 flex items-center justify-center">
                                <flux:icon name="{{ $activeType === 'software' ? 'computer-desktop' : 'cube' }}" class="w-6 h-6 text-zinc-400" />
                            </div>
                            <flux:heading size="md" class="mt-4 text-zinc-900">
                                Belum ada {{ $activeType === 'software' ? 'Spektek Aplikasi' : 'Spektek Barang' }}
                            </flux:heading>
                            <flux:text class="text-sm text-zinc-500 mt-1">
                                Tambahkan item {{ $activeType === 'software' ? 'aplikasi' : 'barang' }} pertama untuk mulai melacak progress pekerjaan.
                            </flux:text>
                            <flux:modal.trigger name="addSpectech">
                                <flux:button variant="primary" icon="plus" size="sm" class="mt-4">Tambah Spektek</flux:button>
                            </flux:modal.trigger>
                        </div>
                    @endif
                @endforelse
            @endif

            {{-- Sticky bulk action bar --}}
            @if($bulkMode && count($selectedIds) > 0)
                <div class="sticky bottom-4 z-30">
                    <div class="mx-auto max-w-2xl bg-zinc-900 text-white rounded-xl shadow-lg ring-1 ring-black/5 px-4 py-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-sm">
                            <flux:icon.check-circle class="w-5 h-5 text-emerald-400" />
                            <span class="font-medium">{{ count($selectedIds) }} spektek dipilih</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:button wire:click="confirmBulkDelete" variant="ghost" size="sm" icon="trash"
                                class="text-red-300! hover:text-red-200!">
                                Hapus
                            </flux:button>
                            <flux:button wire:click="openBulkEdit" variant="primary" size="sm" icon="pencil-square">
                                Edit Massal
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ============ RIGHT: SUMMARY ============ --}}
        <div class="hidden space-y-4">
            {{-- Progress widget --}}
            <div class="bg-white rounded-xl p-6 border border-zinc-200 space-y-5 md:sticky md:top-12 top-4">
                <div class="flex items-center justify-between gap-3">
                    <flux:heading size="md" class="font-semibold text-zinc-900">
                        Progress Spektek
                    </flux:heading>
                    <flux:field class="w-24">
                        <flux:input.group>
                            <flux:input wire:model.live.debounce.500ms="ppn" placeholder="PPN" size="sm" />
                            <flux:input.group.suffix>%</flux:input.group.suffix>
                        </flux:input.group>
                    </flux:field>
                </div>

                {{-- Progress center --}}
                <div class="text-center py-2">
                    <div class="text-4xl font-bold text-zinc-900 tracking-tight">
                        {{ number_format($this->progress ?? 0, 0) }}<span class="text-2xl text-zinc-400">%</span>
                    </div>
                    <flux:text class="text-zinc-500 text-xs mt-1">Progress Spektek Keseluruhan</flux:text>
                </div>

                {{-- Progress bar --}}
                <div class="w-full h-2.5 bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full bg-linear-to-r from-red-500 to-red-600 rounded-full transition-all"
                         style="width: {{ min($this->progress ?? 0, 100) }}%"></div>
                </div>

                {{-- Stats --}}
                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-zinc-100">
                    <div>
                        <flux:text class="text-[11px] uppercase tracking-wide text-zinc-500">Nilai Diterima</flux:text>
                        <p class="text-sm font-semibold text-red-600 mt-0.5">
                            Rp {{ number_format($this->nilaiDiterima, 0, ',', '.') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <flux:text class="text-[11px] uppercase tracking-wide text-zinc-500">Total Proyek</flux:text>
                        <p class="text-sm font-semibold text-zinc-900 mt-0.5">
                            Rp {{ number_format($totalproject ?? 0, 0, ',', '.') }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Formula explainer --}}
            <div x-data="{ open: false }" class="bg-white border border-zinc-200 rounded-xl overflow-hidden">
                <button type="button" class="w-full p-4 flex items-center justify-between text-left hover:bg-zinc-50 transition" @click="open = !open">
                    <div class="flex items-center gap-2">
                        <flux:icon.calculator class="w-4 h-4 text-zinc-500" />
                        <flux:heading size="sm" class="font-medium text-zinc-900">Cara Perhitungan</flux:heading>
                    </div>
                    <svg :class="{'rotate-180': open}" class="w-4 h-4 text-zinc-500 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div x-show="open" x-collapse class="px-4 pb-4 space-y-4 border-t border-zinc-100">
                    <flux:text class="text-zinc-500 text-xs leading-relaxed pt-3">
                        Progress dihitung dari nilai barang yang telah diterima dibanding total nilai proyek.
                        Nilai sudah termasuk <span class="font-medium text-zinc-700">PPN {{ $ppn }}%</span>.
                    </flux:text>

                    <div class="bg-zinc-50 rounded-lg p-3 text-center">
                        <flux:text class="text-[11px] uppercase tracking-wide text-zinc-500">Rumus</flux:text>
                        <p class="text-sm font-semibold text-zinc-800 mt-1">
                            (Nilai Diterima ÷ Total Proyek) × 100%
                        </p>
                    </div>

                    <div class="space-y-2">
                        <flux:text class="text-xs font-medium text-zinc-700">Contoh</flux:text>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="bg-zinc-50 rounded-lg p-2.5">
                                <p class="text-zinc-500 text-[10px]">Diterima</p>
                                <p class="font-semibold text-zinc-800">Rp 35.000.000</p>
                            </div>
                            <div class="bg-zinc-50 rounded-lg p-2.5">
                                <p class="text-zinc-500 text-[10px]">Total</p>
                                <p class="font-semibold text-zinc-800">Rp 100.000.000</p>
                            </div>
                        </div>
                        <div class="bg-red-50 border border-red-100 rounded-lg p-2.5 text-center">
                            <p class="text-red-700 text-[11px]">Hasil Progress</p>
                            <p class="text-base font-bold text-red-700">35%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


{{-- ============ ADD SPECTECH MODAL ============ --}}
<flux:modal name="addSpectech" wire:close="resetForm" class="md:w-120">
    <form wire:submit="create" class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="lg">Tambah Spektek</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Tambahkan item spektek baru ke proyek ini.
            </flux:text>
        </div>

        <div class="space-y-4">
            {{-- Tipe spektek: Hardware / Software --}}
            <flux:field>
                <flux:label badge="Wajib" >Tipe spektek</flux:label>
                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-1 grid grid-cols-2 gap-1">
                    @foreach ([
                        ['key' => 'hardware', 'label' => 'Barang', 'icon' => 'cube'],
                        ['key' => 'software', 'label' => 'Aplikasi', 'icon' => 'computer-desktop'],
                    ] as $typeTab)
                        <button type="button"
                            wire:click="$set('form.type', '{{ $typeTab['key'] }}')"
                            @class([
                                'flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition cursor-pointer',
                                'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' => $form->type === $typeTab['key'],
                                'text-zinc-600 hover:bg-white' => $form->type !== $typeTab['key'],
                            ])>
                            <flux:icon name="{{ $typeTab['icon'] }}" class="w-4 h-4" />
                            <span>{{ $typeTab['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="form.type" />
            </flux:field>

            <flux:field>
                <flux:label badge="Wajib">Nama spektek</flux:label>
                <flux:input wire:model="form.name" placeholder="cth. Pipa PVC 4 inch" autofocus />
                <flux:error name="form.name" />
            </flux:field>

            <div class="grid grid-cols-3 gap-3">
                <flux:field class="col-span-2">
                    <flux:label badge="Wajib">Total Harga</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix>Rp</flux:input.group.prefix>
                        <flux:input mask:dynamic="$money($input, ',', '.', 3)" wire:model="form.price" placeholder="0" />
                    </flux:input.group>
                    <flux:error name="form.price" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Wajib">Jumlah</flux:label>
                    <flux:input wire:model="form.quantity" type="number" min="1" placeholder="0" />
                    <flux:error name="form.quantity" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>Catatan</flux:label>
                <flux:textarea wire:model="form.notes" rows="3" placeholder="Catatan tambahan (opsional)" />
                <flux:error name="form.notes" />
            </flux:field>
        </div>

        <div class="flex gap-2 pt-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="plus" class="flex-1"
                wire:loading.attr="disabled" wire:target="create">
                <span wire:loading.remove wire:target="create">Simpan</span>
                <span wire:loading wire:target="create">Menyimpan...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>

{{-- ============ EDIT SPECTECH MODAL ============ --}}
<flux:modal name="editSpectech" wire:close="resetForm" class="md:w-120 lg:min-w-5xl">
    <form wire:submit="update" class="space-y-6"
        x-data="{ isComing: false}"
        x-effect="isComing = (Number($wire.form.received_quantity) || 0) > 0">
        <div class="space-y-1">
            <flux:heading size="lg">Edit Spektek</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Perbarui detail spektek & jumlah barang yang sudah diterima.
            </flux:text>
        </div>

         <flux:field>
                <flux:label badge="Wajib" >Tipe spektek</flux:label>
                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-1 grid grid-cols-2 gap-1">
                    @foreach ([
                        ['key' => 'hardware', 'label' => 'Barang', 'icon' => 'cube'],
                        ['key' => 'software', 'label' => 'Aplikasi', 'icon' => 'computer-desktop'],
                    ] as $typeTab)
                        <button type="button"
                            wire:click="$set('form.type', '{{ $typeTab['key'] }}')"
                            @class([
                                'flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition cursor-pointer',
                                'bg-red-50 text-red-700 ring-1 ring-inset ring-red-200' => $form->type === $typeTab['key'],
                                'text-zinc-600 hover:bg-white' => $form->type !== $typeTab['key'],
                            ])>
                            <flux:icon name="{{ $typeTab['icon'] }}" class="w-4 h-4" />
                            <span>{{ $typeTab['label'] }}</span>
                        </button>
                    @endforeach
                </div>
                <flux:error name="form.type" />
            </flux:field>

        <div class="space-y-4">
            <flux:field>
                <flux:label badge="Wajib">Nama Spektek</flux:label>
                <flux:input wire:model="form.name" placeholder="cth. Pipa PVC 4 inch" />
                <flux:error name="form.name" />
            </flux:field>

            <div class="grid grid-cols-3 gap-3">
                <flux:field class="col-span-2">
                    <flux:label badge="Wajib">Total Harga</flux:label>
                    <flux:input.group>
                        <flux:input.group.prefix>Rp</flux:input.group.prefix>
                        <flux:input mask:dynamic="$money($input, ',', '.', 3)" wire:model="form.price" placeholder="0" />
                    </flux:input.group>
                    <flux:error name="form.price" />
                </flux:field>

                <flux:field>
                    <flux:label badge="Wajib">Jumlah</flux:label>
                    <flux:input wire:model="form.quantity" type="number" min="1" placeholder="0" />
                    <flux:error name="form.quantity" />
                </flux:field>
            </div>

            {{-- Penerimaan Barang --}}
            {{-- <div class="rounded-lg border border-zinc-200 bg-zinc-50/50 p-4 space-y-3">
                <flux:checkbox x-model="isComing"
                    @change="if (!isComing) $wire.set('form.received_quantity', 0)"
                    label="Sudah ada barang diterima?" />

                <div x-show="isComing" x-collapse>
                    <flux:field>
                        <flux:label>Jumlah Diterima</flux:label>
                        <flux:input wire:model="form.received_quantity" type="number" min="0"
                            :max="$form->quantity" placeholder="0" />
                        <flux:description>
                            Maksimal {{ $form->quantity ?? 0 }} (sesuai total qty).
                        </flux:description>
                        <flux:error name="form.received_quantity" />
                    </flux:field>
                </div>
            </div> --}}

            <flux:field>
                <flux:label>Catatan</flux:label>
                <flux:textarea wire:model="form.notes" rows="3" placeholder="Catatan tambahan (opsional)" />
                <flux:error name="form.notes" />
            </flux:field>
        </div>

        <div class="flex gap-2 pt-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary" icon="check" class="flex-1"
                wire:loading.attr="disabled" wire:target="update">
                <span wire:loading.remove wire:target="update">Perbarui</span>
                <span wire:loading wire:target="update">Memperbarui...</span>
            </flux:button>
        </div>
    </form>
</flux:modal>

{{-- ============ BULK EDIT MODAL ============ --}}
<flux:modal name="bulkEditSpectech" class="md:min-w-3xl lg:min-w-5xl">
    <form wire:submit="applyBulkUpdate" class="space-y-6">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                <flux:heading size="lg">Edit Massal Spektek</flux:heading>
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full bg-red-50 text-red-700 ring-1 ring-inset ring-red-200">
                    {{ count($selectedIds) }} item
                </span>
            </div>
            <flux:text class="text-sm text-zinc-500">
                Atur jumlah diterima &amp; catatan untuk seluruh item terpilih dalam satu langkah.
            </flux:text>
        </div>

        {{-- Inline editable table --}}
        <div class="border border-zinc-200 rounded-xl overflow-hidden">
            <div class="max-h-[60vh] overflow-y-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 sticky top-0 z-10">
                        <tr class="text-left text-[11px] uppercase tracking-wide text-zinc-500">
                            <th class="px-4 py-3 font-medium">Nama Spektek</th>
                            <th class="px-3 py-3 font-medium w-24 text-right">Total</th>
                            <th class="px-3 py-3 font-medium w-32">Diterima</th>
                            <th class="px-4 py-3 font-medium w-[40%]">Catatan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @foreach($this->selectedSpectech as $row)
                            @php $rid = (int) $row['id']; @endphp
                            <tr wire:key="bulk-row-{{ $rid }}" class="hover:bg-zinc-50/60">
                                <td class="px-4 py-3 align-top">
                                    <p class="font-medium text-zinc-900 truncate">{{ $row['name'] }}</p>
                                    <p class="text-xs text-zinc-500 mt-0.5">
                                        Rp {{ number_format($row['qty_nominal'] ?? 0, 0, ',', '.') }} / unit
                                    </p>
                                </td>
                                <td class="px-3 py-3 align-top text-right text-zinc-700 font-medium">
                                    {{ $row['qty_total'] ?? 0 }}
                                </td>
                                <td class="px-3 py-3 align-top">
                                    <flux:input
                                        type="number"
                                        size="sm"
                                        min="0"
                                        :max="$row['qty_total'] ?? 0"
                                        wire:model="bulkChanges.{{ $rid }}.qty_recived"
                                        placeholder="0"
                                    />
                                </td>
                                <td class="px-4 py-3 align-top">
                                    <flux:input
                                        size="sm"
                                        wire:model="bulkChanges.{{ $rid }}.note"
                                        placeholder="Catatan (opsional)"
                                    />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <flux:text class="text-xs text-zinc-500">
                Perubahan akan diterapkan ke {{ count($selectedIds) }} item sekaligus.
            </flux:text>
            <div class="flex gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check"
                    wire:loading.attr="disabled" wire:target="applyBulkUpdate">
                    <span wire:loading.remove wire:target="applyBulkUpdate">Simpan Semua</span>
                    <span wire:loading wire:target="applyBulkUpdate">Menyimpan...</span>
                </flux:button>
            </div>
        </div>
    </form>
</flux:modal>

{{-- ============ BULK DELETE CONFIRMATION MODAL ============ --}}
<flux:modal name="bulkDeleteSpectech" class="md:w-110">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 flex items-center justify-center ring-4 ring-red-50/50">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
            </div>
            <div class="space-y-1 flex-1 min-w-0">
                <flux:heading size="lg">Hapus {{ count($selectedIds) }} spektek?</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Seluruh item terpilih beserta datanya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="bulkDelete" variant="danger" icon="trash" class="flex-1"
                wire:loading.attr="disabled" wire:target="bulkDelete">
                <span wire:loading.remove wire:target="bulkDelete">Hapus Semua</span>
                <span wire:loading wire:target="bulkDelete">Menghapus...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>

{{-- ============ DELETE CONFIRMATION MODAL ============ --}}
<flux:modal name="deleteSpectech" class="md:w-110">
    <div class="space-y-5">
        <div class="flex items-start gap-4">
            <div class="shrink-0 w-11 h-11 rounded-full bg-red-50 flex items-center justify-center ring-4 ring-red-50/50">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-red-600" />
            </div>
            <div class="space-y-1 flex-1 min-w-0">
                <flux:heading size="lg">Hapus Spectech?</flux:heading>
                <flux:text class="text-sm text-zinc-500">
                    Item <span class="font-medium text-zinc-800">"{{ $deletingName }}"</span>
                    akan dihapus permanen beserta seluruh datanya. Tindakan ini tidak dapat dibatalkan.
                </flux:text>
            </div>
        </div>

        <div class="flex gap-2">
            <flux:modal.close>
                <flux:button variant="ghost" class="flex-1">Batal</flux:button>
            </flux:modal.close>
            <flux:button wire:click="deleteSpectech" variant="danger" icon="trash" class="flex-1"
                wire:loading.attr="disabled" wire:target="deleteSpectech">
                <span wire:loading.remove wire:target="deleteSpectech">Hapus</span>
                <span wire:loading wire:target="deleteSpectech">Menghapus...</span>
            </flux:button>
        </div>
    </div>
</flux:modal>
</div>
