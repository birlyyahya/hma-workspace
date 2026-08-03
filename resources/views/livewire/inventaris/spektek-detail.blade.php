<?php

use App\Services\InventarisDummy;
use App\Services\ProjectCache;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Detail Spektek'])]
class extends Component {
    public int $projectId;

    public int $spektekId;

    /** Sub-item yang sedang dibuka di modal generate. */
    public ?int $generateSubId = null;

    public string $generateName = '';

    public int $generateRemaining = 0;

    public int $generateGenerated = 0;

    public int $generateTotal = 0;

    public int $generateQty = 1;

    public function mount(int $projectId, int $spektekId): void
    {
        $this->projectId = $projectId;
        $this->spektekId = $spektekId;
    }

    /**
     * Spektek asli (BEPM) untuk project ini, digabung progres generate/SN per
     * sub-item dari state dummy (belum tersambung API inventaris).
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function spektek(): ?array
    {
        $data = collect(app(ProjectCache::class)->spectechFor($this->projectId))
            ->firstWhere('id', $this->spektekId);

        if ($data === null) {
            return null;
        }

        $inventaris = app(InventarisDummy::class);
        $subs = $data['sub_spekteks'] ?? [];

        if (empty($subs)) {
            $subs = [[
                'id' => $data['id'],
                'name' => $data['name'] ?? '-',
                'qty_total' => $data['qty_total'] ?? 0,
            ]];
        }

        $subItems = collect($subs)
            ->map(function (array $sub) use ($inventaris) {
                $subId = (int) ($sub['id'] ?? 0);

                return [
                    'id' => $subId,
                    'name' => $sub['name'] ?? '-',
                    'unit' => 'item',
                    'qty_total' => (int) ($sub['qty_total'] ?? 0),
                    ...$inventaris->progressForSubItem($subId),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) ($data['id'] ?? 0),
            'name' => $data['name'] ?? '-',
            'sub_items' => $subItems,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function receipts(): array
    {
        return app(InventarisDummy::class)->receiptsForSpektek($this->spektekId);
    }

    #[Computed]
    public function projectName(): string
    {
        return app(ProjectCache::class)->projectFor($this->projectId)['name'] ?? 'Project';
    }

    public function openGenerate(int $subItemId): void
    {
        $sub = collect($this->spektek['sub_items'])->firstWhere('id', $subItemId);

        if ($sub === null) {
            return;
        }

        $this->generateSubId = $subItemId;
        $this->generateName = $sub['name'];
        $this->generateTotal = $sub['qty_total'];
        $this->generateGenerated = $sub['generated'];
        $this->generateRemaining = $sub['qty_total'] - $sub['generated'];
        $this->generateQty = $this->generateRemaining;

        Flux::modal('generate-modal')->show();
    }

    public function generate(): void
    {
        if ($this->generateSubId === null) {
            return;
        }

        $receiptId = app(InventarisDummy::class)->generate($this->spektekId, $this->generateSubId, (int) $this->generateQty);

        if ($receiptId === 0) {
            Toaster::warning('Tidak ada sisa qty untuk digenerate.');

            return;
        }

        Flux::modal('generate-modal')->close();
        Toaster::success("Batch baru dibuat: {$this->generateQty} unit.");
        $this->redirectRoute('inventaris.units', ['goodsReceiptId' => $receiptId], navigate: true);
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        @php $spektek = $this->spektek; @endphp

        @if ($spektek === null)
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Spektek tidak ditemukan</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:text>Spektek tidak ditemukan.</flux:text>
            </div>
        @else
            @php
                $qtyTotal = collect($spektek['sub_items'])->sum('qty_total');
                $generated = collect($spektek['sub_items'])->sum('generated');
            @endphp

            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventaris.project', $projectId)" wire:navigate>{{ $this->projectName }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $spektek['name'] }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ $spektek['name'] }}</flux:heading>
                    <flux:text size="sm" class="mt-0.5">{{ count($spektek['sub_items']) }} sub-item · {{ $qtyTotal }} unit total · {{ $generated }} sudah digenerate</flux:text>
                </div>
            </div>

            {{-- SUB-ITEM LIST --}}
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @foreach ($spektek['sub_items'] as $item)
                        @php
                            $sisa = $item['qty_total'] - $item['generated'];
                            $belumSn = $item['generated'] - $item['sn_assigned'];
                        @endphp
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between" wire:key="sub-{{ $item['id'] }}">
                            <div class="min-w-0 space-y-1">
                                <flux:heading size="sm">{{ $item['name'] }}</flux:heading>
                                <flux:text size="xs">Total {{ $item['qty_total'] }} {{ $item['unit'] }} · digenerate {{ $item['generated'] }} · ber-SN {{ $item['sn_assigned'] }}</flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($sisa === 0 && $belumSn === 0)
                                    <flux:badge size="sm" color="emerald">Selesai</flux:badge>
                                    <flux:button size="sm" variant="outline" disabled>Generate</flux:button>
                                @elseif ($sisa === 0)
                                    <flux:badge size="sm" color="blue">{{ $belumSn }} belum SN</flux:badge>
                                    <flux:button size="sm" variant="outline" disabled>Generate</flux:button>
                                @else
                                    <flux:badge size="sm" color="zinc">Sisa {{ $sisa }} {{ $item['unit'] }}</flux:badge>
                                    <flux:button size="sm" variant="primary" icon="plus-circle" class="cursor-pointer" wire:click="openGenerate({{ $item['id'] }})">Generate</flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- RIWAYAT BATCH --}}
            <div class="space-y-3">
                <flux:heading size="sm">Riwayat Batch</flux:heading>
                @if (empty($this->receipts))
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center dark:border-zinc-700 dark:bg-zinc-800">
                        <flux:text>Belum ada batch diterima untuk spektek ini.</flux:text>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 dark:bg-zinc-900/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-4 py-3">Kode Batch</th>
                                    <th class="px-4 py-3">Sub-item</th>
                                    <th class="px-4 py-3">Jumlah</th>
                                    <th class="px-4 py-3">SN Terisi</th>
                                    <th class="px-4 py-3">Diterima</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                @foreach ($this->receipts as $receipt)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30" wire:key="receipt-{{ $receipt['id'] }}">
                                        <td class="px-4 py-3 font-mono text-xs text-zinc-600 dark:text-zinc-300">{{ $receipt['code'] }}</td>
                                        <td class="px-4 py-3 text-zinc-800 dark:text-zinc-100">{{ $receipt['sub_item'] }}</td>
                                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $receipt['qty'] }} unit</td>
                                        <td class="px-4 py-3">
                                            <flux:badge size="sm" :color="$receipt['sn_filled'] === $receipt['qty'] ? 'emerald' : ($receipt['sn_filled'] === 0 ? 'zinc' : 'blue')">
                                                {{ $receipt['sn_filled'] }}/{{ $receipt['qty'] }}
                                            </flux:badge>
                                        </td>
                                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $receipt['received_at'] }} · {{ $receipt['by'] }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('inventaris.units', $receipt['id']) }}" wire:navigate>
                                                <flux:button size="xs" variant="outline" icon="eye">Lihat Unit</flux:button>
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- MODAL GENERATE (satu modal, konteks di-set saat openGenerate) --}}
            <flux:modal name="generate-modal" class="w-full max-w-md">
                <div class="space-y-5">
                    <div>
                        <flux:heading size="lg">Generate Unit</flux:heading>
                        <flux:text size="sm" class="mt-1">{{ $generateName }}</flux:text>
                    </div>

                    <div class="grid grid-cols-3 gap-3 rounded-xl bg-zinc-50 p-4 text-center dark:bg-zinc-900/50">
                        <div>
                            <flux:heading size="lg">{{ $generateTotal }}</flux:heading>
                            <flux:text size="xs">Total spektek</flux:text>
                        </div>
                        <div>
                            <flux:heading size="lg">{{ $generateGenerated }}</flux:heading>
                            <flux:text size="xs">Sudah digenerate</flux:text>
                        </div>
                        <div>
                            <flux:heading size="lg" class="text-red-600 dark:text-red-400">{{ $generateRemaining }}</flux:heading>
                            <flux:text size="xs">Sisa</flux:text>
                        </div>
                    </div>

                    <flux:field>
                        <flux:label>Jumlah unit yang diterima</flux:label>
                        <flux:input type="number" wire:model="generateQty" min="1" :max="$generateRemaining" />
                        <flux:description>Maksimal sesuai sisa qty spektek. Satu kali generate = satu batch penerimaan.</flux:description>
                    </flux:field>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost" class="cursor-pointer">Batal</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" icon="check" class="cursor-pointer" wire:click="generate" wire:loading.attr="disabled">
                            Generate Unit
                        </flux:button>
                    </div>
                </div>
            </flux:modal>
        @endif

    </div>
</div>
