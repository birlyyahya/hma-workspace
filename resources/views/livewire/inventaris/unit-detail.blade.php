<?php

use App\Services\InventarisDummy;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Detail Unit'])]
class extends Component {
    public int $unitId;

    /** Input untuk aksi utama (assign SN / kirim ke lokasi). */
    public string $actionSn = '';

    public string $actionDestination = '';

    public function mount(int $unitId): void
    {
        $this->unitId = $unitId;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function unit(): ?array
    {
        return app(InventarisDummy::class)->unit($this->unitId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function timeline(): array
    {
        return $this->unit === null ? [] : app(InventarisDummy::class)->timelineFor($this->unit);
    }

    /**
     * Jalankan aksi utama sesuai status: assign SN, atau advance lifecycle.
     */
    public function primaryAction(): void
    {
        $unit = $this->unit;

        if ($unit === null) {
            return;
        }

        $service = app(InventarisDummy::class);

        if ($unit['status'] === 'empty') {
            $result = $service->assignSn($this->unitId, $this->actionSn, $this->actionDestination);
        } else {
            $next = InventarisDummy::nextStatus($unit['status']);

            if ($next === null) {
                return;
            }

            $result = $service->setStatus($this->unitId, $next, $this->actionDestination);
        }

        $this->reset('actionSn', 'actionDestination');

        if ($result['ok']) {
            unset($this->unit, $this->timeline);
            Flux::modal('action-modal')->close();
            Toaster::success($result['message']);
        } else {
            Toaster::warning($result['message']);
        }
    }

    public function markDamaged(): void
    {
        $result = app(InventarisDummy::class)->setStatus($this->unitId, 'damaged');
        unset($this->unit, $this->timeline);
        Toaster::success($result['message']);
    }

    public function statusLabel(string $status): string
    {
        return InventarisDummy::statusLabel($status);
    }

    public function statusColor(string $status): string
    {
        return InventarisDummy::statusColor($status);
    }

    public function timelineDotClasses(string $color): string
    {
        return match ($color) {
            'blue' => 'bg-blue-100 dark:bg-blue-900',
            'amber' => 'bg-amber-100 dark:bg-amber-900',
            'green' => 'bg-green-100 dark:bg-green-900',
            'emerald' => 'bg-emerald-100 dark:bg-emerald-900',
            'red' => 'bg-red-100 dark:bg-red-900',
            default => 'bg-zinc-100 dark:bg-zinc-700',
        };
    }

    public function timelineDotInnerClasses(string $color): string
    {
        return match ($color) {
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
    <div class="max-w-screen-lg mx-auto px-4 sm:px-6 py-6 space-y-6">

        @php $unit = $this->unit; @endphp

        @if ($unit === null)
            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Unit tidak ditemukan</flux:breadcrumbs.item>
            </flux:breadcrumbs>
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon name="exclamation-triangle" class="mx-auto size-8 text-zinc-400" />
                <flux:text class="mt-2">Unit dengan kode ini tidak ditemukan.</flux:text>
            </div>
        @else
            @php $action = App\Services\InventarisDummy::nextAction($unit['status']); @endphp

            <flux:breadcrumbs>
                <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventaris.project', $unit['project_id'])" wire:navigate>{{ $unit['project_name'] ?? $unit['spektek_name'] }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item :href="route('inventaris.units', $unit['receipt_id'])" wire:navigate>{{ $unit['receipt_code'] }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>Unit #{{ $unit['no'] }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            {{-- KARTU IDENTITAS + AKSI UTAMA --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-col gap-6 md:flex-row md:items-start md:justify-between">
                    <div class="min-w-0 space-y-3">
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$this->statusColor($unit['status'])">{{ $this->statusLabel($unit['status']) }}</flux:badge>
                            <flux:badge size="sm" color="zinc" class="font-mono">{{ $unit['code'] }}</flux:badge>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($unit['sn'])
                                <flux:heading size="xl" class="font-mono tracking-tight">{{ $unit['sn'] }}</flux:heading>
                            @else
                                <flux:heading size="xl" class="italic text-zinc-400 dark:text-zinc-500">Belum ada SN</flux:heading>
                            @endif
                        </div>
                        <flux:text size="sm">{{ $unit['item'] }} · Batch {{ $unit['receipt_code'] }} · Tujuan: {{ $unit['destination'] ?? '-' }}</flux:text>

                        <div class="flex flex-wrap gap-2 pt-1">
                            @if ($action)
                                <flux:modal.trigger name="action-modal">
                                    <flux:button variant="primary" :icon="$action['icon']" class="cursor-pointer">{{ $action['label'] }}</flux:button>
                                </flux:modal.trigger>
                            @endif
                            <flux:button variant="outline" icon="printer" class="cursor-pointer">Print Label</flux:button>
                            @if ($unit['status'] !== 'damaged')
                                <flux:dropdown>
                                    <flux:button variant="ghost" icon="ellipsis-horizontal" class="cursor-pointer" />
                                    <flux:menu>
                                        <flux:menu.item icon="exclamation-triangle" variant="danger" wire:click="markDamaged">Tandai Rusak</flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>
                    </div>

                    {{-- QR label unit --}}
                    <div class="flex shrink-0 flex-col items-center gap-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <div class="flex size-28 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-700">
                            <flux:icon name="qr-code" class="size-16 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <flux:text size="xs" class="font-mono">{{ $unit['code'] }}</flux:text>
                    </div>
                </div>
            </div>

            {{-- FOTO --}}
            @php $photoSteps = array_values(array_filter($this->timeline, fn ($s) => $s['photo'])); @endphp
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading size="sm">Foto Unit</flux:heading>
                    <flux:button size="sm" variant="outline" icon="camera" class="cursor-pointer">Tambah Foto</flux:button>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    @foreach ($photoSteps as $step)
                        <div wire:key="photo-{{ $loop->index }}" class="group relative aspect-square overflow-hidden rounded-xl border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                            <div class="flex h-full items-center justify-center">
                                <flux:icon name="photo" class="size-10 text-zinc-400 dark:text-zinc-500" />
                            </div>
                            <div class="absolute inset-x-0 bottom-0 bg-black/60 px-2 py-1.5 text-[11px] text-white">
                                {{ $step['label'] }}
                            </div>
                        </div>
                    @endforeach
                    <button class="flex aspect-square cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 text-zinc-400 transition hover:border-red-400 hover:text-red-500 dark:border-zinc-600 dark:text-zinc-500">
                        <div class="flex flex-col items-center gap-1">
                            <flux:icon name="plus" class="size-6" />
                            <span class="text-xs">Upload</span>
                        </div>
                    </button>
                </div>
                @if (empty($photoSteps))
                    <flux:text size="xs">Belum ada foto — unit ini belum melewati tahap yang membutuhkan bukti foto.</flux:text>
                @endif
            </div>

            {{-- TIMELINE MOVEMENT --}}
            <div class="space-y-3">
                <flux:heading size="sm">Riwayat Pergerakan</flux:heading>
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <ol class="relative space-y-6 border-s border-zinc-200 dark:border-zinc-700">
                        @foreach ($this->timeline as $step)
                            @php $color = $this->statusColor($step['status']); @endphp
                            <li class="ms-5" wire:key="step-{{ $loop->index }}">
                                <span class="absolute -start-[9px] flex size-[18px] items-center justify-center rounded-full {{ $this->timelineDotClasses($color) }} ring-4 ring-white dark:ring-zinc-800">
                                    <span class="size-2 rounded-full {{ $this->timelineDotInnerClasses($color) }}"></span>
                                </span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" :color="$color">{{ $step['label'] }}</flux:badge>
                                    <flux:text size="xs">{{ $step['when'] }} · {{ $step['by'] }}</flux:text>
                                </div>
                                <flux:text size="sm" class="mt-1">{{ $step['desc'] }}</flux:text>
                                @if ($step['photo'])
                                    <div class="mt-2 flex size-14 items-center justify-center rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-700">
                                        <flux:icon name="photo" class="size-6 text-zinc-400" />
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>

            {{-- MODAL AKSI UTAMA --}}
            @if ($action)
                <flux:modal name="action-modal" class="w-full max-w-sm">
                    <div class="space-y-5">
                        <div>
                            <flux:heading size="lg">{{ $action['label'] }}</flux:heading>
                            <flux:text size="sm" class="mt-1 font-mono">{{ $unit['code'] }}</flux:text>
                        </div>

                        @if ($unit['status'] === 'empty')
                            <flux:field>
                                <flux:label>Serial number</flux:label>
                                <flux:input wire:model="actionSn" icon="qr-code" placeholder="Ketik / scan SN dari fisik barang..." />
                            </flux:field>
                        @endif

                        @if (in_array($unit['status'], ['empty', 'sn_assigned'], true))
                            <flux:field>
                                <flux:label>Tujuan / lokasi {{ $unit['status'] === 'empty' ? '(opsional)' : '' }}</flux:label>
                                <flux:input wire:model="actionDestination" placeholder="Contoh: POP Jababeka" />
                            </flux:field>
                        @endif

                        <flux:text size="sm" class="text-zinc-500">
                            Status akan berubah menjadi <strong>{{ $this->statusLabel($action['status']) }}</strong>.
                        </flux:text>

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button variant="ghost" class="cursor-pointer">Batal</flux:button>
                            </flux:modal.close>
                            <flux:button variant="primary" :icon="$action['icon']" class="cursor-pointer" wire:click="primaryAction">{{ $action['label'] }}</flux:button>
                        </div>
                    </div>
                </flux:modal>
            @endif
        @endif

    </div>
</div>
