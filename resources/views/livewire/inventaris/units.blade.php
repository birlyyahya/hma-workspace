<?php

use App\Services\InventarisDummy;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — List Unit'])]
class extends Component {
    public int $goodsReceiptId;

    /** Input mode scan beruntun. */
    public string $scanSn = '';

    /** Textarea import SN (satu SN per baris). */
    public string $importText = '';

    /** Unit yang sedang diedit SN-nya. */
    public ?int $editUnitId = null;

    public string $editCode = '';

    public string $editSn = '';

    /** Alokasi per jumlah. */
    public string $allocSatker = '';

    public int $allocCount = 1;

    /** Aksi bulk atas unit terpilih (id unit dikirim dari Alpine). */
    public array $bulkIds = [];

    public string $bulkSatker = '';

    public string $bulkStatus = 'in_transit';

    public function mount(int $goodsReceiptId): void
    {
        $this->goodsReceiptId = $goodsReceiptId;
        $this->allocSatker = InventarisDummy::satkers()[0];
        $this->bulkSatker = InventarisDummy::satkers()[0];
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function receipt(): ?array
    {
        return app(InventarisDummy::class)->receipt($this->goodsReceiptId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function units(): array
    {
        return app(InventarisDummy::class)->unitsForReceipt($this->goodsReceiptId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function groups(): array
    {
        return app(InventarisDummy::class)->unitsGroupedByDestination($this->goodsReceiptId);
    }

    #[Computed]
    public function projectName(): string
    {
        return app(InventarisDummy::class)->project($this->receipt['project_id'] ?? 0)['name'] ?? 'Project';
    }

    public function unallocatedCount(): int
    {
        return collect($this->units)->whereNull('destination')->count();
    }

    /**
     * @return array<int, string>
     */
    public function satkerOptions(): array
    {
        return InventarisDummy::satkers();
    }

    /**
     * @return array<int, array{status: string, label: string, color: string, count: int}>
     */
    #[Computed]
    public function filters(): array
    {
        $counts = collect($this->units)->countBy('status');
        $out = [];

        foreach (['empty', 'sn_assigned', 'in_transit', 'delivered', 'installed', 'damaged'] as $status) {
            if ($counts->get($status, 0) > 0) {
                $out[] = ['status' => $status, 'label' => InventarisDummy::statusLabel($status), 'color' => InventarisDummy::statusColor($status), 'count' => $counts->get($status)];
            }
        }

        return $out;
    }

    public function firstEmptyCode(): ?string
    {
        return collect($this->units)->firstWhere('status', 'empty')['code'] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function importPreview(): array
    {
        if (trim($this->importText) === '') {
            return [];
        }

        return app(InventarisDummy::class)->previewImport($this->goodsReceiptId, preg_split('/\r\n|\r|\n/', $this->importText) ?: []);
    }

    private function refresh(): void
    {
        unset($this->units, $this->groups, $this->filters, $this->receipt);
    }

    public function scan(): void
    {
        if (trim($this->scanSn) === '') {
            return;
        }

        $result = app(InventarisDummy::class)->assignNextEmpty($this->goodsReceiptId, $this->scanSn);
        $this->scanSn = '';

        if ($result['ok']) {
            $this->refresh();
            Toaster::success("{$result['unit']} → {$result['message']}");
        } else {
            Toaster::warning($result['message']);
        }
    }

    public function confirmImport(): void
    {
        $summary = app(InventarisDummy::class)->importSn($this->goodsReceiptId, preg_split('/\r\n|\r|\n/', $this->importText) ?: []);
        $this->importText = '';
        $this->refresh();
        Flux::modal('import-sn-modal')->close();
        Toaster::success("{$summary['assigned']} SN di-assign · {$summary['duplicates']} duplikat · {$summary['overflow']} kelebihan.");
    }

    public function openEdit(int $unitId): void
    {
        $unit = app(InventarisDummy::class)->unit($unitId);

        if ($unit === null) {
            return;
        }

        $this->editUnitId = $unitId;
        $this->editCode = $unit['code'];
        $this->editSn = $unit['sn'] ?? '';

        Flux::modal('edit-sn-modal')->show();
    }

    public function saveEdit(): void
    {
        if ($this->editUnitId === null) {
            return;
        }

        $result = app(InventarisDummy::class)->updateSn($this->editUnitId, $this->editSn);

        if ($result['ok']) {
            $this->refresh();
            Flux::modal('edit-sn-modal')->close();
            Toaster::success($result['message']);
        } else {
            Toaster::warning($result['message']);
        }
    }

    /** Alokasi per jumlah ke satker. */
    public function allocate(): void
    {
        $n = app(InventarisDummy::class)->allocateToSatker($this->goodsReceiptId, $this->allocSatker, (int) $this->allocCount);
        $this->refresh();
        Flux::modal('allocate-modal')->close();

        $n > 0
            ? Toaster::success("{$n} unit dialokasikan ke {$this->allocSatker}.")
            : Toaster::warning('Tidak ada unit tersedia untuk dialokasikan.');
    }

    /** Majukan status seluruh unit satu grup satker. */
    public function advanceGroup(string $destination): void
    {
        $result = app(InventarisDummy::class)->advanceGroup($this->goodsReceiptId, $destination);
        $this->refresh();

        $result['moved'] > 0
            ? Toaster::success("{$result['moved']} unit di {$destination} maju satu tahap.")
            : Toaster::warning('Tidak ada unit yang bisa dimajukan di grup ini.');
    }

    /** @param  array<int, int>  $ids */
    public function openBulkSatker(array $ids): void
    {
        if (empty($ids)) {
            Toaster::warning('Pilih unit terlebih dahulu.');

            return;
        }

        $this->bulkIds = $ids;
        Flux::modal('bulk-satker-modal')->show();
    }

    public function applyBulkSatker(): void
    {
        $n = app(InventarisDummy::class)->assignUnitsToSatker($this->bulkIds, $this->bulkSatker);
        $this->refresh();
        Flux::modal('bulk-satker-modal')->close();
        $this->dispatch('clear-selection');
        Toaster::success("{$n} unit di-assign ke {$this->bulkSatker}.");
    }

    /** @param  array<int, int>  $ids */
    public function openBulkStatus(array $ids): void
    {
        if (empty($ids)) {
            Toaster::warning('Pilih unit terlebih dahulu.');

            return;
        }

        $this->bulkIds = $ids;
        Flux::modal('bulk-status-modal')->show();
    }

    public function applyBulkStatus(): void
    {
        $result = app(InventarisDummy::class)->bulkSetStatus($this->bulkIds, $this->bulkStatus);
        $this->refresh();
        Flux::modal('bulk-status-modal')->close();
        $this->dispatch('clear-selection');
        Toaster::success("{$result['applied']} unit diubah ke ".InventarisDummy::statusLabel($this->bulkStatus).($result['skipped'] ? " · {$result['skipped']} dilewati (tanpa SN)" : '').'.');
    }

    public function statusLabel(string $status): string
    {
        return InventarisDummy::statusLabel($status);
    }

    public function statusColor(string $status): string
    {
        return InventarisDummy::statusColor($status);
    }

    /** Kelas titik warna literal (bukan interpolasi) agar tak ter-purge Tailwind. */
    public function statusDotClass(string $status): string
    {
        return match (InventarisDummy::statusColor($status)) {
            'blue' => 'bg-blue-500',
            'amber' => 'bg-amber-500',
            'green' => 'bg-green-500',
            'emerald' => 'bg-emerald-500',
            'red' => 'bg-red-500',
            default => 'bg-zinc-400',
        };
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    @php $receipt = $this->receipt; @endphp

    @if ($receipt === null)
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6">
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:text>Batch tidak ditemukan.</flux:text>
            </div>
        </div>
    @else
        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6"
            x-data="{ selected: [], scanMode: false, activeStatus: 'all', view: 'table' }"
            x-on:clear-selection.window="selected = []">

            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventaris.project', $receipt['project_id'])" wire:navigate>{{ $this->projectName }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventaris.spektek', [$receipt['project_id'], $receipt['spektek_id']])" wire:navigate>{{ $receipt['spektek_name'] }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $receipt['code'] }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            {{-- HEADER BATCH --}}
            <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <flux:heading size="lg" class="font-mono">{{ $receipt['code'] }}</flux:heading>
                        <flux:badge size="sm" :color="$receipt['sn_filled'] === $receipt['qty'] ? 'emerald' : 'blue'">{{ $receipt['sn_filled'] }}/{{ $receipt['qty'] }} ber-SN</flux:badge>
                    </div>
                    <flux:text size="sm" class="mt-0.5">{{ $receipt['sub_item'] }} · {{ $receipt['qty'] }} unit · {{ $this->unallocatedCount() }} belum dialokasikan</flux:text>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:modal.trigger name="allocate-modal">
                        <flux:button size="sm" variant="primary" icon="building-office-2" class="cursor-pointer">Alokasi ke Satker</flux:button>
                    </flux:modal.trigger>
                    <flux:modal.trigger name="import-sn-modal">
                        <flux:button size="sm" variant="outline" icon="arrow-up-tray" class="cursor-pointer">Import SN</flux:button>
                    </flux:modal.trigger>
                    <flux:button size="sm" variant="outline" icon="qr-code" class="cursor-pointer" x-on:click="scanMode = !scanMode" x-bind:class="scanMode && 'ring-2 ring-red-500'">
                        Mode Scan
                    </flux:button>
                </div>
            </div>

            {{-- PANEL MODE SCAN --}}
            <div x-show="scanMode" x-collapse x-cloak
                class="rounded-2xl border-2 border-dashed border-red-300 bg-red-50/50 p-5 dark:border-red-800 dark:bg-red-950/20">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1">
                        <flux:heading size="sm">Mode scan beruntun</flux:heading>
                        <flux:text size="sm">
                            @if ($this->firstEmptyCode())
                                Scan / ketik SN lalu Enter — mengisi unit kosong berikutnya ({{ $this->firstEmptyCode() }}).
                            @else
                                Semua unit batch ini sudah ber-SN.
                            @endif
                        </flux:text>
                    </div>
                    <form wire:submit="scan" class="sm:w-64">
                        <flux:input wire:model="scanSn" icon="qr-code" placeholder="Scan SN di sini..." autofocus :disabled="! $this->firstEmptyCode()" kbd="↵" />
                    </form>
                </div>
            </div>

            {{-- TOGGLE VIEW --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-xl bg-zinc-100 p-1 shadow-sm dark:bg-zinc-800">
                    <button x-on:click="view = 'table'" x-bind:class="view === 'table' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500'" class="cursor-pointer rounded-lg px-4 py-1.5 text-sm font-medium transition">
                        <flux:icon name="table-cells" class="me-1 inline size-4" /> Daftar Unit
                    </button>
                    <button x-on:click="view = 'satker'" x-bind:class="view === 'satker' ? 'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' : 'text-zinc-500'" class="cursor-pointer rounded-lg px-4 py-1.5 text-sm font-medium transition">
                        <flux:icon name="building-office-2" class="me-1 inline size-4" /> Per Satker
                    </button>
                </div>

                {{-- FILTER STATUS (hanya di tampilan tabel) --}}
                <div x-show="view === 'table'" class="flex flex-wrap items-center gap-2">
                    <flux:badge as="button" x-on:click="activeStatus = 'all'" color="red" x-bind:variant="activeStatus === 'all' ? undefined : 'outline'" class="cursor-pointer">
                        Semua ({{ count($this->units) }})
                    </flux:badge>
                    @foreach ($this->filters as $filter)
                        <flux:badge wire:key="filter-{{ $filter['status'] }}" as="button" x-on:click="activeStatus = '{{ $filter['status'] }}'" :color="$filter['color']" x-bind:variant="activeStatus === '{{ $filter['status'] }}' ? undefined : 'outline'" class="cursor-pointer">
                            {{ $filter['label'] }} ({{ $filter['count'] }})
                        </flux:badge>
                    @endforeach
                </div>
            </div>

            {{-- TAMPILAN TABEL --}}
            <div x-show="view === 'table'" class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="w-10 px-4 py-3">
                                <flux:checkbox x-on:change="selected = $event.target.checked ? [{{ collect($this->units)->pluck('id')->implode(',') }}] : []" />
                            </th>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Kode Unit</th>
                            <th class="px-4 py-3">Serial Number</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Satker / Tujuan</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @foreach ($this->units as $unit)
                            <tr wire:key="unit-{{ $unit['id'] }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30"
                                x-show="activeStatus === 'all' || activeStatus === '{{ $unit['status'] }}'">
                                <td class="px-4 py-3">
                                    <flux:checkbox x-bind:checked="selected.includes({{ $unit['id'] }})"
                                        x-on:change="$event.target.checked ? selected.push({{ $unit['id'] }}) : selected = selected.filter(i => i !== {{ $unit['id'] }})" />
                                </td>
                                <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $unit['no'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $unit['code'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($unit['sn'])
                                        <span class="font-mono text-zinc-800 dark:text-zinc-100">{{ $unit['sn'] }}</span>
                                    @else
                                        <span class="italic text-zinc-400 dark:text-zinc-500">— belum diisi</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><flux:badge size="sm" :color="$this->statusColor($unit['status'])">{{ $this->statusLabel($unit['status']) }}</flux:badge></td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $unit['destination'] ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex gap-1">
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" class="cursor-pointer" title="Edit SN" wire:click="openEdit({{ $unit['id'] }})" />
                                        <a href="{{ route('inventaris.unit-detail', $unit['id']) }}" wire:navigate>
                                            <flux:button size="xs" variant="outline" icon="eye">Detail</flux:button>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- TAMPILAN PER SATKER --}}
            <div x-show="view === 'satker'" x-cloak class="space-y-4">
                @foreach ($this->groups as $group)
                    <div wire:key="group-{{ $loop->index }}" class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex flex-col gap-3 border-b border-zinc-100 p-5 dark:border-zinc-700/50 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 space-y-1.5">
                                <div class="flex items-center gap-2">
                                    @if ($group['destination'])
                                        <flux:icon name="building-office-2" class="size-4 text-zinc-400" />
                                        <flux:heading size="sm">{{ $group['destination'] }}</flux:heading>
                                    @else
                                        <flux:icon name="inbox" class="size-4 text-zinc-400" />
                                        <flux:heading size="sm" class="text-zinc-500">Belum dialokasikan</flux:heading>
                                    @endif
                                    <flux:badge size="sm" color="zinc">{{ $group['count'] }} unit</flux:badge>
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    @foreach ($group['status_counts'] as $sc)
                                        <flux:badge size="sm" :color="$sc['color']">{{ $sc['count'] }} {{ $sc['label'] }}</flux:badge>
                                    @endforeach
                                </div>
                            </div>
                            @if ($group['destination'] && $group['can_advance'])
                                <flux:button size="sm" variant="primary" icon="arrow-right-circle" class="cursor-pointer shrink-0"
                                    wire:click="advanceGroup(@js($group['destination']))"
                                    wire:confirm="Majukan status semua {{ $group['count'] }} unit di {{ $group['destination'] }} satu tahap?">
                                    Majukan Status
                                </flux:button>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1.5 p-5">
                            @foreach ($group['units'] as $u)
                                <a href="{{ route('inventaris.unit-detail', $u['id']) }}" wire:navigate wire:key="gu-{{ $u['id'] }}"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-xs transition hover:border-red-300 dark:border-zinc-700 dark:bg-zinc-900/40 dark:hover:border-red-700">
                                    <span class="size-1.5 rounded-full {{ $this->statusDotClass($u['status']) }}"></span>
                                    <span class="font-mono text-zinc-700 dark:text-zinc-200">{{ $u['sn'] ?? $u['code'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- BULK ACTION BAR --}}
            <div x-show="selected.length > 0" x-cloak x-transition
                class="fixed inset-x-0 bottom-6 z-40 mx-auto flex w-fit flex-wrap items-center justify-center gap-3 rounded-full border border-zinc-200 bg-white px-5 py-2.5 shadow-lg dark:border-zinc-600 dark:bg-zinc-800">
                <flux:text size="sm"><span class="font-semibold" x-text="selected.length"></span> unit dipilih</flux:text>
                <flux:separator vertical class="h-5" />
                <flux:button size="sm" variant="ghost" icon="building-office-2" class="cursor-pointer" x-on:click="$wire.openBulkSatker(selected)">Assign Satker</flux:button>
                <flux:button size="sm" variant="ghost" icon="arrows-up-down" class="cursor-pointer" x-on:click="$wire.openBulkStatus(selected)">Ubah Status</flux:button>
                <flux:button size="sm" variant="ghost" icon="x-mark" class="cursor-pointer" x-on:click="selected = []" />
            </div>

            {{-- MODAL ALOKASI PER JUMLAH --}}
            <flux:modal name="allocate-modal" class="w-full max-w-md">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Alokasi ke Satker</flux:heading>
                        <flux:text size="sm" class="mt-1">Ambil sejumlah unit yang belum dialokasikan ({{ $this->unallocatedCount() }} tersedia) untuk satu satker. SN yang terpilih otomatis berurutan.</flux:text>
                    </div>
                    <flux:field>
                        <flux:label>Satker / tujuan</flux:label>
                        <flux:select wire:model="allocSatker">
                            @foreach ($this->satkerOptions() as $satker)
                                <flux:select.option :value="$satker">{{ $satker }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <flux:field>
                        <flux:label>Jumlah unit</flux:label>
                        <flux:input type="number" wire:model="allocCount" min="1" :max="$this->unallocatedCount()" />
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="allocate" :disabled="$this->unallocatedCount() === 0">Alokasikan</flux:button>
                    </div>
                </div>
            </flux:modal>

            {{-- MODAL BULK: ASSIGN SATKER --}}
            <flux:modal name="bulk-satker-modal" class="w-full max-w-md">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Assign Satker</flux:heading>
                        <flux:text size="sm" class="mt-1">{{ count($bulkIds) }} unit terpilih akan diarahkan ke satker berikut.</flux:text>
                    </div>
                    <flux:field>
                        <flux:label>Satker / tujuan</flux:label>
                        <flux:select wire:model="bulkSatker">
                            @foreach ($this->satkerOptions() as $satker)
                                <flux:select.option :value="$satker">{{ $satker }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="applyBulkSatker">Terapkan</flux:button>
                    </div>
                </div>
            </flux:modal>

            {{-- MODAL BULK: UBAH STATUS --}}
            <flux:modal name="bulk-status-modal" class="w-full max-w-md">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Ubah Status</flux:heading>
                        <flux:text size="sm" class="mt-1">{{ count($bulkIds) }} unit terpilih. Unit tanpa SN dilewati (kecuali status Rusak).</flux:text>
                    </div>
                    <flux:field>
                        <flux:label>Status baru</flux:label>
                        <flux:select wire:model="bulkStatus">
                            <flux:select.option value="sn_assigned">SN Terpasang</flux:select.option>
                            <flux:select.option value="in_transit">Dalam Pengiriman</flux:select.option>
                            <flux:select.option value="delivered">Diterima di Site</flux:select.option>
                            <flux:select.option value="installed">Terpasang</flux:select.option>
                            <flux:select.option value="damaged">Rusak</flux:select.option>
                        </flux:select>
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="applyBulkStatus">Terapkan</flux:button>
                    </div>
                </div>
            </flux:modal>

            {{-- MODAL IMPORT SN --}}
            <flux:modal name="import-sn-modal" class="w-full max-w-2xl">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Import SN</flux:heading>
                        <flux:text size="sm" class="mt-1">Tempel daftar SN (satu per baris). SN di-assign berurutan ke unit kosong; duplikat &amp; kelebihan dilewati.</flux:text>
                    </div>

                    <flux:textarea wire:model.live.debounce.300ms="importText" rows="5" placeholder="ZTE10G-88361&#10;ZTE10G-88362&#10;..." class="font-mono text-xs" />

                    @if (! empty($this->importPreview))
                        <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        <th class="px-4 py-2.5">Baris</th>
                                        <th class="px-4 py-2.5">SN</th>
                                        <th class="px-4 py-2.5">→ Unit</th>
                                        <th class="px-4 py-2.5">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                    @foreach ($this->importPreview as $row)
                                        <tr wire:key="prev-{{ $row['row'] }}" @class([
                                            'bg-red-50/50 dark:bg-red-950/20' => $row['state'] === 'duplicate',
                                            'bg-amber-50/50 dark:bg-amber-950/20' => $row['state'] === 'overflow',
                                        ])>
                                            <td class="px-4 py-2.5 text-zinc-500">{{ $row['row'] }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs">{{ $row['sn'] }}</td>
                                            <td class="px-4 py-2.5 font-mono text-xs">{{ $row['unit'] ?? '—' }}</td>
                                            <td class="px-4 py-2.5">
                                                <flux:badge size="sm" :color="$row['state'] === 'ok' ? 'green' : ($row['state'] === 'duplicate' ? 'red' : 'amber')">{{ $row['note'] }}</flux:badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @php $okCount = collect($this->importPreview)->where('state', 'ok')->count(); @endphp
                    @else
                        @php $okCount = 0; @endphp
                        <flux:text size="sm" class="text-zinc-400">Belum ada SN untuk dipratinjau.</flux:text>
                    @endif

                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="confirmImport" :disabled="$okCount === 0">
                            Assign {{ $okCount }} SN
                        </flux:button>
                    </div>
                </div>
            </flux:modal>

            {{-- MODAL EDIT SN --}}
            <flux:modal name="edit-sn-modal" class="w-full max-w-sm">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Edit Serial Number</flux:heading>
                        <flux:text size="sm" class="mt-1 font-mono">{{ $editCode }}</flux:text>
                    </div>
                    <flux:field>
                        <flux:label>Serial number</flux:label>
                        <flux:input wire:model="editSn" icon="qr-code" placeholder="Ketik / scan SN..." />
                    </flux:field>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost" class="cursor-pointer">Batal</flux:button></flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="saveEdit">Simpan</flux:button>
                    </div>
                </div>
            </flux:modal>
        </div>
    @endif
</div>
