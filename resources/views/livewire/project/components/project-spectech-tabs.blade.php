<?php

use App\Livewire\Forms\SpectechForm;
use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Flux\Flux;
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

    public string $sortBy = 'default';
    public string $sortDir = 'asc';
    public int $visibleLimit = 25;

    public ?int $expandedId = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $subItems = [];

    public ?int $subEditId = null;
    public string $subName = '';
    public ?int $subQuantity = null;
    public ?string $subPrice = null;
    public string $subType = 'hardware';
    public ?int $deletingSubId = null;

    public ?string $detailName = null;
    public ?string $detailHtml = null;

    public function placeholder()
    {
        return view('components.placeholder.ph_project_spectech_tabs');
    }

    public function mount(): void
    {
        $this->loadSpectech();
    }

    public function loadSpectech(): void
    {
        $this->loadingSpectech = true;
        $data = app(ProjectCache::class)->spectechFor((int) $this->id);

        $this->spectech = array_map(fn (array $item): array => $this->mapItem($item), $data);

        $this->loadingSpectech = false;
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
            'qty_recived'   => $data['qty_received'],
            'total_nominal' => $data['total_nominal'],
            'qty_nominal'   => $data['qty_nominal'],
            'percentage'    => $data['progress_percentage'],
            'note'          => $data['note'],
            'detail'        => $data['detail'] ?? '',
            'images'        => $data['images'] ?? [],
            // Field type belum tersedia dari API; default hardware sampai backend menambahkan kolom ini.
            'type'          => $data['type'] ?? 'hardware',
        ];
    }

    protected function afterMutation(): void
    {
        app(ProjectCache::class)->flushSpectech((int) $this->id);
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

        $result = app(ProjectWriter::class)->deleteSpectechCategory((int) $id, (int) $this->id);

        if ($result['ok']) {
            $this->afterMutation();
            $this->loadSpectech();
            Toaster::success('Spectech berhasil dihapus');
        } else {
            Toaster::error('Gagal menghapus spectech');
        }

        $this->reset('deletingId', 'deletingName');
        Flux::modal('deleteSpectech')->close();
    }

    public function create(): void
    {
        $result = $this->form->store((int) $this->id);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []));
            $this->logApiFailure('create', $result['body']);
            return;
        }

        $this->form->reset();
        $this->afterMutation();
        $this->loadSpectech();

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
        $result = $this->form->update((int) $this->id);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []));
            $this->logApiFailure('update', $result['body']);
            return;
        }

        $this->form->reset();
        $this->afterMutation();
        $this->loadSpectech();

        Toaster::success('Spectech berhasil diperbarui');
        Flux::modal('editSpectech')->close();
    }

    #[On('updateProgress')]
    public function updateProgress($progress): void
    {
        $this->progress = $progress;
    }

    #[On('spectechSaved')]
    public function refreshAfterManage(): void
    {
        app(ProjectCache::class)->flushSpectech((int) $this->id);
        $this->loadSpectech();
        $this->dispatch('projectLoad');
    }

    public function resetForm(): void
    {
        $this->form->reset();
        $this->resetErrorBag();
    }

    public function openAdd(): void
    {
        $this->resetForm();
        $this->form->type = $this->activeType;
        Flux::modal('addSpectech')->show();
    }

    public function setType(string $type): void
    {
        if (! in_array($type, ['hardware', 'software'], true)) {
            return;
        }

        $this->activeType = $type;
        $this->selectedIds = [];
        $this->reset('visibleLimit');
        $this->collapseSub();
    }

    public function sortByColumn(string $column): void
    {
        if (! in_array($column, ['name', 'qty_total', 'total_nominal'], true)) {
            return;
        }

        if ($this->sortBy !== $column) {
            $this->sortBy = $column;
            $this->sortDir = 'asc';

            return;
        }

        if ($this->sortDir === 'asc') {
            $this->sortDir = 'desc';

            return;
        }

        $this->reset('sortBy', 'sortDir');
    }

    public function showMore(): void
    {
        $this->visibleLimit += 25;
    }

    public function updatedSearch(): void
    {
        $this->reset('visibleLimit');
    }

    public function updatedStatusFilter(): void
    {
        $this->reset('visibleLimit');
    }

    public function toggleExpand(int $id): void
    {
        if ($this->bulkMode) {
            return;
        }

        if ($this->expandedId === $id) {
            $this->collapseSub();

            return;
        }

        $this->expandedId = $id;
        $this->resetSubForm();
        $this->loadSubItems();
    }

    public function collapseSub(): void
    {
        $this->expandedId = null;
        $this->subItems = [];
        $this->resetSubForm();
    }

    protected function loadSubItems(): void
    {
        if ($this->expandedId === null) {
            $this->subItems = [];

            return;
        }

        $data = app(ProjectCache::class)->subSpectechFor((int) $this->expandedId);

        $this->subItems = array_map(fn (array $item): array => [
            'id'                  => $item['id'],
            'name'                => $item['name'] ?? '',
            'qty_total'           => (int) ($item['qty_total'] ?? 0),
            'qty_received'        => (int) ($item['qty_received'] ?? 0),
            'total_nominal'       => (float) ($item['total_nominal'] ?? 0),
            'progress_percentage' => (float) ($item['progress_percentage'] ?? 0),
            'type'                => $item['type'] ?? 'hardware',
        ], $data);
    }

    /**
     * @return array<string, string>
     */
    protected function subRules(): array
    {
        return [
            'subName'     => 'required|string|min:3|max:120',
            'subQuantity' => 'required|integer|min:1',
            'subPrice'    => 'required',
            'subType'     => 'required|in:hardware,software',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function subMessages(): array
    {
        return [
            'subName.required'     => 'Nama sub spektek wajib diisi.',
            'subName.min'          => 'Nama sub spektek minimal 3 karakter.',
            'subQuantity.required' => 'Jumlah wajib diisi.',
            'subQuantity.min'      => 'Jumlah minimal 1.',
            'subPrice.required'    => 'Total harga wajib diisi.',
        ];
    }

    public function resetSubForm(): void
    {
        $this->reset('subEditId', 'subName', 'subQuantity', 'subPrice');
        $this->subType = 'hardware';
        $this->resetErrorBag(['subName', 'subQuantity', 'subPrice', 'subType']);
    }

    public function saveSub(): void
    {
        if ($this->expandedId === null) {
            return;
        }

        $this->validate($this->subRules(), $this->subMessages());

        $payload = [
            'name'          => $this->subName,
            'qty_total'     => (int) $this->subQuantity,
            'total_nominal' => (int) preg_replace('/[^0-9]/', '', (string) $this->subPrice),
            'type'          => $this->subType,
            'spektek_id'    => (int) $this->expandedId,
        ];

        $result = $this->subEditId !== null
            ? app(ProjectWriter::class)->updateSubSpectech((int) $this->subEditId, (int) $this->expandedId, $payload)
            : app(ProjectWriter::class)->createSubSpectech((int) $this->expandedId, $payload);

        if (! $result['ok']) {
            Toaster::error(getErrorMessages($result['body']['errors'] ?? []) ?: 'Gagal menyimpan sub spektek');
            $this->logApiFailure('saveSub', $result['body']);

            return;
        }

        Toaster::success($this->subEditId !== null ? 'Sub spektek berhasil diperbarui' : 'Sub spektek berhasil ditambahkan');
        $this->resetSubForm();
        $this->loadSubItems();
    }

    public function editSub(int $id): void
    {
        $item = collect($this->subItems)->firstWhere('id', $id);

        if (! $item) {
            Toaster::error('Sub spektek tidak ditemukan');

            return;
        }

        $this->subEditId = $id;
        $this->subName = $item['name'];
        $this->subQuantity = (int) $item['qty_total'];
        $this->subPrice = (string) (int) $item['total_nominal'];
        $this->subType = $item['type'];
    }

    public function confirmDeleteSub(int $id): void
    {
        $this->deletingSubId = $id;
        Flux::modal('deleteSubSpectech')->show();
    }

    public function deleteSub(): void
    {
        if ($this->deletingSubId === null || $this->expandedId === null) {
            return;
        }

        $result = app(ProjectWriter::class)->deleteSubSpectech((int) $this->deletingSubId, (int) $this->expandedId);

        if ($result['ok']) {
            Toaster::success('Sub spektek berhasil dihapus');
            $this->loadSubItems();
        } else {
            Toaster::error('Gagal menghapus sub spektek');
        }

        $this->reset('deletingSubId');
        Flux::modal('deleteSubSpectech')->close();
    }

    public function updateSubQty(int $id, $qty): void
    {
        if ($this->expandedId === null) {
            return;
        }

        $item = collect($this->subItems)->firstWhere('id', $id);

        if (! $item) {
            return;
        }

        $qty = max(0, min((int) $qty, (int) $item['qty_total']));

        $result = app(ProjectWriter::class)->updateSubSpectechQty($id, (int) $this->expandedId, $qty);

        if (! $result['ok']) {
            Toaster::error('Gagal memperbarui jumlah diterima');
        }

        $this->loadSubItems();
    }

    public function showDetail(int $id): void
    {
        $item = collect($this->spectech)->firstWhere('id', $id);

        if (! $item || empty($item['detail'])) {
            Toaster::error('Detail spesifikasi tidak tersedia');

            return;
        }

        $this->detailName = $item['name'];
        $this->detailHtml = $item['detail'];
        Flux::modal('viewSpectechDetail')->show();
    }

    public function toggleBulkMode(): void
    {
        $this->bulkMode = ! $this->bulkMode;
        $this->selectedIds = [];
        $this->collapseSub();
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
        $this->reset('search', 'statusFilter', 'visibleLimit');
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

        $items = collect($this->spectech)
            ->filter(fn ($item) => ($item['type'] ?? 'hardware') === $this->activeType)
            ->when($needle !== '', fn ($items) => $items->filter(
                fn ($item) => str_contains(mb_strtolower((string) ($item['name'] ?? '')), $needle),
            ))
            ->when($this->statusFilter !== 'all', fn ($items) => $items->filter(
                fn ($item) => $this->statusOf($item) === $this->statusFilter,
            ));

        if ($this->sortBy !== 'default') {
            $items = $items->sortBy(
                fn ($item) => $this->sortBy === 'name'
                    ? mb_strtolower((string) ($item['name'] ?? ''))
                    : (float) ($item[$this->sortBy] ?? 0),
                SORT_REGULAR,
                $this->sortDir === 'desc',
            );
        }

        return $items->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function visibleSpectech(): array
    {
        return array_slice($this->filteredSpectech, 0, $this->visibleLimit);
    }

    #[Computed]
    public function hiddenCount(): int
    {
        return max(0, count($this->filteredSpectech) - $this->visibleLimit);
    }

    #[Computed]
    public function filteredTotalNominal(): float
    {
        return collect($this->filteredSpectech)->sum(fn ($item) => (float) ($item['total_nominal'] ?? 0));
    }

    #[Computed]
    public function allTotalNominal(): float
    {
        return collect($this->spectech)->sum(fn ($item) => (float) ($item['total_nominal'] ?? 0));
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
    public function totalItems(): int
    {
        return count($this->spectech);
    }

}; ?>

<div>
    <div class="space-y-4">
        {{-- ============ SUMMARY STRIP ============ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-white border border-zinc-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
                    <flux:icon.squares-2x2 class="w-5 h-5 text-red-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-500">Total Item</p>
                    <p class="text-base font-semibold text-zinc-900">{{ $this->totalItems }}</p>
                </div>
            </div>
            <div class="bg-white border border-zinc-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
                    <flux:icon.banknotes class="w-5 h-5 text-emerald-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-500">Nominal Spektek</p>
                    <p class="text-base font-semibold text-zinc-900 truncate">
                        Rp {{ number_format($this->allTotalNominal, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            <div class="bg-white border border-zinc-200 rounded-xl p-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                    <flux:icon.briefcase class="w-5 h-5 text-blue-600" />
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-500">Total Proyek</p>
                    <p class="text-base font-semibold text-zinc-900 truncate">
                        Rp {{ number_format($totalproject ?? 0, 0, ',', '.') }}
                    </p>
                </div>
            </div>
            <div class="bg-white border border-zinc-200 rounded-xl p-4">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] uppercase tracking-wide text-zinc-500">Progress</p>
                    <p class="text-base font-semibold text-zinc-900">{{ number_format($this->progress ?? 0, 0) }}%</p>
                </div>
                <div class="mt-2.5 w-full h-2 bg-zinc-100 rounded-full overflow-hidden">
                    <div class="h-full bg-linear-to-r from-red-500 to-red-600 rounded-full transition-all"
                         style="width: {{ min($this->progress ?? 0, 100) }}%"></div>
                </div>
            </div>
        </div>

        <div class="space-y-4">
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
                        <flux:button wire:click="toggleBulkMode" variant="ghost" size="sm" icon="check-circle"
                            :disabled="$this->totalItems === 0">
                            Pilih
                        </flux:button>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="primary" size="sm" icon="plus" icon:trailing="chevron-down">
                                Tambah
                            </flux:button>
                            <flux:navmenu>
                                <flux:navmenu.item icon="plus" wire:click="openAdd">
                                    Tambah Satu Item
                                </flux:navmenu.item>
                                <flux:navmenu.item icon="squares-plus" wire:click="$dispatch('openManageSpectech', { tab: 'manual' })">
                                    Tambah Banyak
                                </flux:navmenu.item>
                                <flux:navmenu.item icon="arrow-up-tray" wire:click="$dispatch('openManageSpectech', { tab: 'import' })">
                                    Import Excel
                                </flux:navmenu.item>
                            </flux:navmenu>
                        </flux:dropdown>
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
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Cari nama spektek..."
                    size="sm"
                    class="w-full sm:flex-1"
                    clearable
                />
                <flux:select wire:model.live="statusFilter" size="sm" class="w-full sm:w-44 shrink-0">
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

            {{-- Bulk select-all toolbar (mobile; desktop pakai checkbox di header tabel) --}}
            @if($bulkMode && !$this->loadingSpectech && count($this->filteredSpectech) > 0)
                @php $filteredCount = count($this->filteredSpectech); @endphp
                <div class="md:hidden flex items-center justify-between bg-zinc-50 border border-zinc-200 rounded-lg px-4 py-2.5">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-zinc-700">
                        <input type="checkbox"
                            class="rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                            x-on:click.prevent="$wire.toggleSelectAllVisible()"
                            x-bind:checked="$wire.selectedIds.length === {{ $filteredCount }} && {{ $filteredCount }} > 0"
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
                <div class="bg-white border border-zinc-200 rounded-xl p-4 space-y-3 animate-pulse">
                    <div class="h-9 bg-zinc-100 rounded-lg"></div>
                    @for ($i = 0; $i < 6; $i++)
                        <div class="flex items-center gap-4">
                            <div class="h-4 bg-zinc-100 rounded flex-1"></div>
                            <div class="h-4 bg-zinc-100 rounded w-14"></div>
                            <div class="h-4 bg-zinc-100 rounded w-28 hidden sm:block"></div>
                            <div class="h-4 bg-zinc-100 rounded w-20"></div>
                        </div>
                    @endfor
                </div>
            @elseif(count($this->filteredSpectech) === 0)
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
                        <div class="mt-4 flex items-center justify-center gap-2">
                            <flux:button wire:click="openAdd" variant="primary" icon="plus" size="sm">Tambah Spektek</flux:button>
                            <flux:button wire:click="$dispatch('openManageSpectech', { tab: 'import' })" variant="ghost" icon="arrow-up-tray" size="sm">
                                Import Excel
                            </flux:button>
                        </div>
                    </div>
                @endif
            @else
                {{-- ============ TABLE VIEW (desktop) ============ --}}
                <div class="hidden md:block bg-white border border-zinc-200 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 border-b border-zinc-200">
                            <tr class="text-left text-[11px] uppercase tracking-wide text-zinc-500">
                                @if($bulkMode)
                                    <th class="px-4 py-3 w-10">
                                        <input type="checkbox"
                                            class="rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                            x-on:click.prevent="$wire.toggleSelectAllVisible()"
                                            x-bind:checked="$wire.selectedIds.length === {{ count($this->filteredSpectech) }}"
                                        />
                                    </th>
                                @endif
                                <th class="px-4 py-3 font-medium">
                                    <button type="button" wire:click="sortByColumn('name')"
                                        class="inline-flex items-center gap-1 uppercase tracking-wide font-medium hover:text-zinc-800 cursor-pointer">
                                        Nama Spektek
                                        <flux:icon name="{{ $sortBy === 'name' ? ($sortDir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down' }}" class="w-3.5 h-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-3 font-medium w-24 text-right">
                                    <button type="button" wire:click="sortByColumn('qty_total')"
                                        class="inline-flex items-center gap-1 uppercase tracking-wide font-medium hover:text-zinc-800 cursor-pointer">
                                        Qty
                                        <flux:icon name="{{ $sortBy === 'qty_total' ? ($sortDir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down' }}" class="w-3.5 h-3.5" />
                                    </button>
                                </th>
                                <th class="px-3 py-3 font-medium w-36 text-right">Harga Satuan</th>
                                <th class="px-3 py-3 font-medium w-40 text-right">
                                    <button type="button" wire:click="sortByColumn('total_nominal')"
                                        class="inline-flex items-center gap-1 uppercase tracking-wide font-medium hover:text-zinc-800 cursor-pointer">
                                        Total Nominal
                                        <flux:icon name="{{ $sortBy === 'total_nominal' ? ($sortDir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevron-up-down' }}" class="w-3.5 h-3.5" />
                                    </button>
                                </th>
                                @unless($bulkMode)
                                    <th class="px-2 py-3 w-12"></th>
                                @endunless
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            @foreach ($this->visibleSpectech as $data)
                                @php
                                    $qtyTotal = (int) ($data['qty_total'] ?? 0);
                                    $isSelected = in_array((int) $data['id'], array_map('intval', $selectedIds), true);
                                    $hasDetail = ! empty($data['detail']);
                                    $isExpanded = ! $bulkMode && $expandedId === (int) $data['id'];
                                @endphp
                                <tr wire:key="spectech-row-{{ $data['id'] }}"
                                    @class([
                                        'transition cursor-pointer',
                                        'bg-red-50/60' => $bulkMode && $isSelected,
                                        'bg-zinc-50/80' => $isExpanded,
                                        'hover:bg-zinc-50/60' => !($bulkMode && $isSelected) && !$isExpanded,
                                    ])
                                    wire:click="{{ $bulkMode ? 'toggleSelect' : 'toggleExpand' }}({{ (int) $data['id'] }})">
                                    @if($bulkMode)
                                        <td class="px-4 py-3 align-top">
                                            <input type="checkbox"
                                                class="w-4 h-4 rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                                x-bind:checked="$wire.selectedIds.map(Number).includes({{ (int) $data['id'] }})"
                                                x-on:click.stop.prevent="$wire.toggleSelect({{ (int) $data['id'] }})"
                                            />
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 align-top">
                                        <div class="flex items-start gap-2">
                                            @unless($bulkMode)
                                                <flux:icon.chevron-right @class([
                                                    'w-4 h-4 mt-0.5 text-zinc-400 shrink-0 transition-transform',
                                                    'rotate-90' => $isExpanded,
                                                ]) />
                                            @endunless
                                            <div class="min-w-0">
                                                <p class="font-medium text-zinc-900">{{ $data['name'] }}</p>
                                                @if(!empty($data['note']))
                                                    <p class="text-xs text-zinc-500 mt-0.5 truncate max-w-md" title="{{ $data['note'] }}">
                                                        {{ $data['note'] }}
                                                    </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 align-top text-right text-zinc-700 font-medium">
                                        {{ $qtyTotal }}
                                    </td>
                                    <td class="px-3 py-3 align-top text-right text-zinc-600">
                                        Rp {{ number_format($data['qty_nominal'] ?? 0, 0, ',', '.') }}
                                    </td>
                                    <td class="px-3 py-3 align-top text-right font-semibold text-zinc-900">
                                        Rp {{ number_format($data['total_nominal'] ?? 0, 0, ',', '.') }}
                                    </td>
                                    @unless($bulkMode)
                                        <td class="px-2 py-2 align-top text-right" x-on:click.stop>
                                            <flux:dropdown wire:key="spectech-menu-{{ $data['id'] }}">
                                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-zinc-400" />
                                                <flux:navmenu>
                                                    @if($hasDetail)
                                                        <flux:navmenu.item icon="eye" wire:click="showDetail({{ (int) $data['id'] }})">Lihat Detail</flux:navmenu.item>
                                                    @endif
                                                    <flux:navmenu.item icon="pencil-square" wire:click="editSpectech({{ $data['id'] }})">Edit</flux:navmenu.item>
                                                    <flux:navmenu.item icon="trash" variant="danger"
                                                        wire:click="confirmDelete({{ $data['id'] }})">Hapus</flux:navmenu.item>
                                                </flux:navmenu>
                                            </flux:dropdown>
                                        </td>
                                    @endunless
                                </tr>

                                {{-- Expandable sub spektek panel --}}
                                @if($isExpanded)
                                    <tr wire:key="spectech-sub-{{ $data['id'] }}" class="border-t-0!">
                                        <td colspan="5" class="px-4 pb-5 pt-0">
                                            <div class="ml-6 rounded-xl border border-zinc-200 bg-zinc-50/60 p-4 space-y-4">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-zinc-500">
                                                        <flux:icon.squares-2x2 class="w-3.5 h-3.5" />
                                                        Sub Spektek ({{ count($subItems) }})
                                                    </p>
                                                    @if($hasDetail)
                                                        <button type="button" wire:click="showDetail({{ (int) $data['id'] }})"
                                                            class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700 cursor-pointer">
                                                            <flux:icon.document-text class="w-3.5 h-3.5" />
                                                            Lihat Detail Spesifikasi
                                                        </button>
                                                    @endif
                                                </div>

                                                @if(count($subItems) > 0)
                                                    <div class="rounded-lg border border-zinc-200 bg-white overflow-hidden">
                                                        <table class="w-full text-sm">
                                                            <thead class="bg-zinc-50 border-b border-zinc-200">
                                                                <tr class="text-left text-[11px] uppercase tracking-wide text-zinc-500">
                                                                    <th class="px-3 py-2 font-medium">Nama</th>
                                                                    <th class="px-3 py-2 font-medium w-36">Diterima / Qty</th>
                                                                    <th class="px-3 py-2 font-medium w-36 text-right">Total Nominal</th>
                                                                    <th class="px-2 py-2 w-20"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-zinc-100">
                                                                @foreach($subItems as $sub)
                                                                    <tr wire:key="sub-row-{{ $sub['id'] }}">
                                                                        <td class="px-3 py-2">
                                                                            <p class="font-medium text-zinc-900">{{ $sub['name'] }}</p>
                                                                            <p class="text-xs text-zinc-500">{{ $sub['type'] === 'software' ? 'Aplikasi' : 'Barang' }}</p>
                                                                        </td>
                                                                        <td class="px-3 py-2">
                                                                            <div class="flex items-center gap-1.5">
                                                                                <input type="number" min="0" max="{{ $sub['qty_total'] }}"
                                                                                    value="{{ $sub['qty_received'] }}"
                                                                                    wire:change="updateSubQty({{ (int) $sub['id'] }}, $event.target.value)"
                                                                                    class="w-16 rounded-lg border border-zinc-300 bg-white text-sm py-1 px-2 focus:border-red-500 focus:ring-red-500" />
                                                                                <span class="text-xs text-zinc-500">/ {{ $sub['qty_total'] }}</span>
                                                                            </div>
                                                                        </td>
                                                                        <td class="px-3 py-2 text-right font-medium text-zinc-900">
                                                                            Rp {{ number_format($sub['total_nominal'], 0, ',', '.') }}
                                                                        </td>
                                                                        <td class="px-2 py-2 text-right whitespace-nowrap">
                                                                            <flux:button wire:click="editSub({{ (int) $sub['id'] }})"
                                                                                variant="ghost" size="xs" icon="pencil-square" class="text-zinc-400" />
                                                                            <flux:button wire:click="confirmDeleteSub({{ (int) $sub['id'] }})"
                                                                                variant="ghost" size="xs" icon="trash" class="text-zinc-400 hover:text-red-600!" />
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <p class="text-sm text-zinc-500">
                                                        Belum ada sub spektek untuk item ini — tambahkan lewat form di bawah (opsional).
                                                    </p>
                                                @endif

                                                {{-- Inline add/edit form --}}
                                                <form wire:submit="saveSub" class="space-y-2">
                                                    <div class="grid grid-cols-12 gap-2">
                                                        <div class="col-span-4">
                                                            <flux:input wire:model="subName" size="sm" placeholder="Nama sub spektek..." />
                                                        </div>
                                                        <div class="col-span-2">
                                                            <flux:select wire:model="subType" size="sm">
                                                                <flux:select.option value="hardware">Barang</flux:select.option>
                                                                <flux:select.option value="software">Aplikasi</flux:select.option>
                                                            </flux:select>
                                                        </div>
                                                        <div class="col-span-2">
                                                            <flux:input wire:model="subQuantity" type="number" min="1" size="sm" placeholder="Qty" />
                                                        </div>
                                                        <div class="col-span-2">
                                                            <flux:input wire:model="subPrice" type="number" min="0" size="sm" placeholder="Total harga" />
                                                        </div>
                                                        <div class="col-span-2 flex items-start gap-1">
                                                            <flux:button type="submit" variant="primary" size="sm" class="flex-1"
                                                                icon="{{ $subEditId ? 'check' : 'plus' }}"
                                                                wire:loading.attr="disabled" wire:target="saveSub">
                                                                {{ $subEditId ? 'Simpan' : 'Tambah' }}
                                                            </flux:button>
                                                            @if($subEditId)
                                                                <flux:button wire:click="resetSubForm" variant="ghost" size="sm" icon="x-mark" tooltip="Batal edit" />
                                                            @endif
                                                        </div>
                                                    </div>
                                                    <flux:error name="subName" />
                                                    <flux:error name="subQuantity" />
                                                    <flux:error name="subPrice" />
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-50 border-t border-zinc-200">
                            <tr>
                                <td colspan="{{ $bulkMode ? 4 : 3 }}" class="px-4 py-3 text-xs font-medium text-zinc-500 uppercase tracking-wide">
                                    Total {{ count($this->filteredSpectech) }} item
                                </td>
                                <td class="px-3 py-3 text-right text-sm font-semibold text-red-600">
                                    Rp {{ number_format($this->filteredTotalNominal, 0, ',', '.') }}
                                </td>
                                @unless($bulkMode)
                                    <td></td>
                                @endunless
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- ============ COMPACT LIST (mobile) ============ --}}
                <div class="md:hidden bg-white border border-zinc-200 rounded-xl divide-y divide-zinc-100 overflow-hidden">
                    @foreach ($this->visibleSpectech as $data)
                        @php
                            $qtyRecv = (int) ($data['qty_recived'] ?? 0);
                            $qtyTotal = (int) ($data['qty_total'] ?? 0);
                            $percentage = (float) ($data['percentage'] ?? 0);
                            $isComplete = $percentage >= 100;
                            $statusLabel = $isComplete ? 'Selesai' : ($qtyRecv > 0 ? 'Berjalan' : 'Belum Mulai');
                            $statusColor = $isComplete
                                ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20'
                                : ($qtyRecv > 0 ? 'bg-amber-50 text-amber-700 ring-amber-600/20' : 'bg-zinc-50 text-zinc-600 ring-zinc-500/20');
                            $isSelected = in_array((int) $data['id'], array_map('intval', $selectedIds), true);
                            $hasDetail = ! empty($data['detail']);
                            $isExpanded = ! $bulkMode && $expandedId === (int) $data['id'];
                        @endphp
                        <div wire:key="spectech-mobile-{{ $data['id'] }}"
                            @class([
                                'flex items-start gap-3 p-4 transition cursor-pointer',
                                'bg-red-50/60' => $bulkMode && $isSelected,
                                'bg-zinc-50/80' => $isExpanded,
                            ])
                            wire:click="{{ $bulkMode ? 'toggleSelect' : 'toggleExpand' }}({{ (int) $data['id'] }})">
                            @if($bulkMode)
                                <input type="checkbox"
                                    class="mt-1 w-4 h-4 shrink-0 rounded border-zinc-300 text-red-600 focus:ring-red-500 cursor-pointer"
                                    x-bind:checked="$wire.selectedIds.map(Number).includes({{ (int) $data['id'] }})"
                                    x-on:click.stop.prevent="$wire.toggleSelect({{ (int) $data['id'] }})"
                                />
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @unless($bulkMode)
                                        <flux:icon.chevron-right @class([
                                            'w-4 h-4 text-zinc-400 shrink-0 transition-transform',
                                            'rotate-90' => $isExpanded,
                                        ]) />
                                    @endunless
                                    <p class="font-medium text-zinc-900 truncate">{{ $data['name'] }}</p>
                                    <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full ring-1 ring-inset {{ $statusColor }}">
                                        {{ $statusLabel }}
                                    </span>
                                </div>
                                <p class="text-xs text-zinc-500 mt-1">
                                    {{ $qtyTotal }} × Rp {{ number_format($data['qty_nominal'] ?? 0, 0, ',', '.') }}
                                </p>
                                <p class="text-sm font-semibold text-zinc-900 mt-1">
                                    Rp {{ number_format($data['total_nominal'] ?? 0, 0, ',', '.') }}
                                </p>
                                @if(!empty($data['note']))
                                    <p class="text-xs text-amber-900 bg-amber-50/70 border border-amber-100 rounded-lg px-2.5 py-1.5 mt-2 leading-relaxed">
                                        {{ $data['note'] }}
                                    </p>
                                @endif
                                @if($isExpanded)
                                    <div class="mt-3 rounded-lg bg-zinc-50 border border-zinc-200 p-3 space-y-3 cursor-default" x-on:click.stop>
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-[11px] uppercase tracking-wide text-zinc-500">
                                                Sub Spektek ({{ count($subItems) }})
                                            </p>
                                            @if($hasDetail)
                                                <button type="button" wire:click="showDetail({{ (int) $data['id'] }})"
                                                    class="text-xs font-medium text-red-600 cursor-pointer">
                                                    Lihat Detail
                                                </button>
                                            @endif
                                        </div>

                                        @forelse($subItems as $sub)
                                            <div wire:key="sub-mobile-{{ $sub['id'] }}"
                                                class="flex items-center justify-between gap-2 rounded-lg bg-white border border-zinc-100 px-3 py-2">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-medium text-zinc-900 truncate">{{ $sub['name'] }}</p>
                                                    <p class="text-xs text-zinc-500">
                                                        {{ $sub['qty_received'] }}/{{ $sub['qty_total'] }} diterima
                                                        · Rp {{ number_format($sub['total_nominal'], 0, ',', '.') }}
                                                    </p>
                                                </div>
                                                <div class="flex items-center shrink-0">
                                                    <flux:button wire:click="editSub({{ (int) $sub['id'] }})"
                                                        variant="ghost" size="xs" icon="pencil-square" class="text-zinc-400" />
                                                    <flux:button wire:click="confirmDeleteSub({{ (int) $sub['id'] }})"
                                                        variant="ghost" size="xs" icon="trash" class="text-zinc-400 hover:text-red-600!" />
                                                </div>
                                            </div>
                                        @empty
                                            <p class="text-xs text-zinc-500">Belum ada sub spektek untuk item ini.</p>
                                        @endforelse

                                        {{-- Inline add/edit form --}}
                                        <form wire:submit="saveSub" class="space-y-2">
                                            <flux:input wire:model="subName" size="sm" placeholder="Nama sub spektek..." />
                                            <div class="grid grid-cols-3 gap-2">
                                                <flux:select wire:model="subType" size="sm">
                                                    <flux:select.option value="hardware">Barang</flux:select.option>
                                                    <flux:select.option value="software">Aplikasi</flux:select.option>
                                                </flux:select>
                                                <flux:input wire:model="subQuantity" type="number" min="1" size="sm" placeholder="Qty" />
                                                <flux:input wire:model="subPrice" type="number" min="0" size="sm" placeholder="Harga" />
                                            </div>
                                            <flux:error name="subName" />
                                            <flux:error name="subQuantity" />
                                            <flux:error name="subPrice" />
                                            <div class="flex gap-2">
                                                <flux:button type="submit" variant="primary" size="sm" class="flex-1"
                                                    icon="{{ $subEditId ? 'check' : 'plus' }}"
                                                    wire:loading.attr="disabled" wire:target="saveSub">
                                                    {{ $subEditId ? 'Simpan Perubahan' : 'Tambah Sub' }}
                                                </flux:button>
                                                @if($subEditId)
                                                    <flux:button wire:click="resetSubForm" variant="ghost" size="sm" icon="x-mark" />
                                                @endif
                                            </div>
                                        </form>
                                    </div>
                                @endif
                            </div>
                            @unless($bulkMode)
                                <flux:dropdown wire:key="spectech-mobile-menu-{{ $data['id'] }}" x-on:click.stop>
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" class="text-zinc-400 -mr-1" />
                                    <flux:navmenu>
                                        <flux:navmenu.item icon="pencil-square" wire:click="editSpectech({{ $data['id'] }})">Edit</flux:navmenu.item>
                                        <flux:navmenu.item icon="trash" variant="danger"
                                            wire:click="confirmDelete({{ $data['id'] }})">Hapus</flux:navmenu.item>
                                    </flux:navmenu>
                                </flux:dropdown>
                            @endunless
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between bg-zinc-50 px-4 py-3">
                        <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">
                            Total {{ count($this->filteredSpectech) }} item
                        </span>
                        <span class="text-sm font-semibold text-red-600">
                            Rp {{ number_format($this->filteredTotalNominal, 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Show more --}}
                @if($this->hiddenCount > 0)
                    <div class="flex justify-center">
                        <flux:button wire:click="showMore" variant="ghost" size="sm" icon="chevron-down"
                            wire:loading.attr="disabled" wire:target="showMore">
                            Tampilkan lebih banyak ({{ $this->hiddenCount }} tersisa)
                        </flux:button>
                    </div>
                @endif
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
    </div>


{{-- ============ ADD SPECTECH MODAL ============ --}}
<flux:modal name="addSpectech" wire:close="resetForm" class="md:w-120 lg:min-w-5xl">
    <form wire:submit="create" class="space-y-6">
        <div class="space-y-1">
            <flux:heading size="lg">Tambah Spektek</flux:heading>
            <flux:text class="text-sm text-zinc-500">
                Tambahkan item spektek baru ke proyek ini.
            </flux:text>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- KANAN: inputan utama (mobile: tampil duluan) --}}
            <div class="space-y-4">
                {{-- Tipe spektek: Hardware / Software --}}
                <flux:field>
                    <flux:label>Tipe spektek</flux:label>
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
                        <x-rupiah-input model="form.price" placeholder="0" />
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

            {{-- KIRI: detail spesifikasi --}}
            <div class="">
                <flux:field>
                    <flux:label>Detail Spesifikasi</flux:label>
                    <div wire:ignore x-data="spectechRichEditor(@entangle('form.detail'))"
                        class="spectech-editor spectech-editor--tall overflow-hidden rounded-lg border border-zinc-200 bg-white focus-within:border-zinc-400">
                        <div x-ref="editor" data-placeholder="Rincian spesifikasi lengkap — cth. RAM 16GB DDR5, SSD 512GB NVMe, Prosesor Intel i7..."></div>
                    </div>
                    <flux:description>Opsional. Bisa berupa daftar poin; dibuka lewat "Lihat Detail" pada item di tabel.</flux:description>
                    <flux:error name="form.detail" />
                </flux:field>
            </div>
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

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- KANAN: inputan utama (mobile: tampil duluan) --}}
            <div class="space-y-4">
                <flux:field>
                    <flux:label badge="Wajib">Tipe spektek</flux:label>
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
                    <flux:label badge="Wajib">Nama Spektek</flux:label>
                    <flux:input wire:model="form.name" placeholder="cth. Pipa PVC 4 inch" />
                    <flux:error name="form.name" />
                </flux:field>

                <div class="grid grid-cols-3 gap-3">
                    <flux:field class="col-span-2">
                        <flux:label badge="Wajib">Total Harga</flux:label>
                        <x-rupiah-input model="form.price" placeholder="0" />
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

            {{-- KIRI: detail spesifikasi --}}
            <div class="">
                <flux:field>
                    <flux:label>Detail Spesifikasi</flux:label>
                    <div wire:ignore x-data="spectechRichEditor(@entangle('form.detail'))"
                        class="spectech-editor spectech-editor--tall overflow-hidden rounded-lg border border-zinc-200 bg-white focus-within:border-zinc-400">
                        <div x-ref="editor" data-placeholder="Rincian spesifikasi lengkap — cth. RAM 16GB DDR5, SSD 512GB NVMe, Prosesor Intel i7..."></div>
                    </div>
                    <flux:description>Opsional. Bisa berupa daftar poin; dibuka lewat "Lihat Detail" pada item di tabel.</flux:description>
                    <flux:error name="form.detail" />
                </flux:field>
            </div>
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

{{-- ============ VIEW DETAIL MODAL ============ --}}
<flux:modal name="viewSpectechDetail" class="md:w-2xl">
    <div class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">{{ $detailName }}</flux:heading>
            <flux:text class="text-sm text-zinc-500">Detail spesifikasi lengkap item.</flux:text>
        </div>
        <div class="spectech-prose text-sm text-zinc-700 leading-relaxed max-h-[60vh] overflow-y-auto rounded-lg bg-zinc-50 border border-zinc-200 p-4">
            {!! $detailHtml !!}
        </div>
        <div class="flex justify-end">
            <flux:modal.close>
                <flux:button variant="ghost">Tutup</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>

{{-- ============ DELETE SUB CONFIRMATION MODAL ============ --}}
<x-confirm-modal name="deleteSubSpectech" confirm="deleteSub" title="Hapus Sub Spektek?">
    Sub spektek akan dihapus permanen dari item ini. Tindakan ini tidak dapat dibatalkan.
</x-confirm-modal>

{{-- ============ DELETE CONFIRMATION MODAL ============ --}}
<x-confirm-modal name="deleteSpectech" confirm="deleteSpectech" title="Hapus Spectech?">
    Item <span class="font-medium text-zinc-800">"{{ $deletingName }}"</span>
    akan dihapus permanen beserta seluruh datanya. Tindakan ini tidak dapat dibatalkan.
</x-confirm-modal>

{{-- ============ KELOLA SPEKTEK (child component) ============ --}}
<livewire:project.components.project-spectech-manage :id="(int) $id" />
</div>

@assets
{{-- CKEditor di-bundle lewat resources/js/app.js (window.ClassicEditor), bukan CDN. --}}
<style>
    .spectech-editor .ck.ck-toolbar {
        border: none;
        border-bottom: 1px solid #e4e4e7;
        background: #fafafa;
    }

    .spectech-editor .ck.ck-editor__editable_inline {
        border: none !important;
        box-shadow: none !important;
        font-size: 0.875rem;
        min-height: 110px;
        max-height: 260px;
        overflow-y: auto;
    }

    .spectech-editor--tall .ck.ck-editor__editable_inline {
        min-height: 280px;
        max-height: 420px;
    }

    .spectech-editor .ck.ck-editor__editable_inline > :first-child {
        margin-top: 0.5rem;
    }

    .spectech-editor .ck .ck-placeholder::before {
        color: #a1a1aa;
    }

    .spectech-prose ul {
        list-style: disc;
        padding-left: 1.25rem;
    }

    .spectech-prose ol {
        list-style: decimal;
        padding-left: 1.25rem;
    }

    .spectech-prose li {
        margin: 0.125rem 0;
    }

    .spectech-prose p + p {
        margin-top: 0.25rem;
    }
</style>
@endassets

@script
<script>
    Alpine.data('spectechRichEditor', (model) => ({
        value: model,
        editor: null,

        init() {
            this.whenEditorReady(() => this.mountEditor());
        },

        destroy() {
            this.editor?.destroy().catch(() => {});
            this.editor = null;
        },

        whenEditorReady(callback, tries = 0) {
            if (window.ClassicEditor) {
                callback();
                return;
            }

            if (tries > 200) {
                console.error('Editor gagal dimuat.');
                return;
            }

            setTimeout(() => this.whenEditorReady(callback, tries + 1), 50);
        },

        mountEditor() {
            if (this.editor) {
                return;
            }

            ClassicEditor
                .create(this.$refs.editor, {
                    toolbar: ['bulletedList', 'numberedList', 'bold', 'italic', 'undo', 'redo'],
                    placeholder: this.$refs.editor.dataset.placeholder || '',
                })
                .then((editor) => {
                    this.editor = editor;
                    editor.setData(this.value || '');

                    editor.model.document.on('change:data', () => {
                        this.value = editor.getData();
                    });

                    this.$watch('value', (val) => {
                        if (editor.getData() !== (val || '')) {
                            editor.setData(val || '');
                        }
                    });
                })
                .catch((error) => console.error(error));
        },
    }));
</script>
@endscript
