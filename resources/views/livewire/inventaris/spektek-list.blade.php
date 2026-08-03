<?php

use App\Services\InventarisDummy;
use App\Services\ProjectCache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new
#[Layout('components.layouts.app', ['title' => 'Inventaris — Daftar Spektek'])]
class extends Component {
    public int $projectId;

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;
    }

    /**
     * Identitas project dari data asli (BEPM); ringkasan progres inventaris masih
     * dummy per-project sampai API inventaris tersambung.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function project(): ?array
    {
        $project = app(ProjectCache::class)->projectFor($this->projectId);

        if (empty($project)) {
            return null;
        }

        return [
            'name' => $project['name'] ?? 'Project',
            'company' => $project['client'] ?? '-',
            ...app(InventarisDummy::class)->summaryFor($this->projectId),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * Daftar spektek asli (BEPM) untuk project ini, digabung progres generate/SN
     * per sub-item dari state dummy (belum tersambung API inventaris).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function spekteks(): array
    {
        $inventaris = app(InventarisDummy::class);

        return collect(app(ProjectCache::class)->spectechFor($this->projectId))
            ->map(fn (array $s) => $this->mapSpektek($s, $inventaris))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapSpektek(array $data, InventarisDummy $inventaris): array
    {
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
            'composite' => count($subItems) > 1,
            'sub_items' => $subItems,
        ];
    }
} ?>

<div class="bg-zinc-50/50 dark:bg-zinc-900/50 min-h-screen">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 py-6 space-y-6">

        @php $project = $this->project; @endphp

        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('inventaris')" wire:navigate>Inventaris</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $project['name'] ?? 'Project' }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        @if ($project === null)
            <div class="rounded-2xl border border-dashed border-zinc-300 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-800">
                <flux:text>Project tidak ditemukan.</flux:text>
            </div>
        @else
            {{-- HEADER PROJECT --}}
            <div class="flex flex-col gap-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 md:flex-row md:items-center md:justify-between">
                <div class="min-w-0">
                    <flux:heading size="lg" class="leading-tight">{{ $project['name'] }}</flux:heading>
                    <flux:text size="sm" class="mt-0.5">{{ $project['company'] }} · {{ $project['spektek_count'] }} spektek · {{ $project['total_units'] }} unit</flux:text>
                </div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="text-center">
                        <flux:heading size="lg">{{ $project['sn_units'] }}</flux:heading>
                        <flux:text size="xs">Ber-SN</flux:text>
                    </div>
                    <flux:separator vertical class="h-8" />
                    <div class="text-center">
                        <flux:heading size="lg">{{ $project['generated_units'] - $project['sn_units'] }}</flux:heading>
                        <flux:text size="xs">Belum SN</flux:text>
                    </div>
                    <flux:separator vertical class="h-8" />
                    <div class="text-center">
                        <flux:heading size="lg">{{ $project['total_units'] - $project['generated_units'] }}</flux:heading>
                        <flux:text size="xs">Belum generate</flux:text>
                    </div>
                </div>
            </div>

            {{-- LEGEND --}}
            <div class="flex flex-wrap items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-emerald-500"></span> SN terpasang</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-blue-400"></span> Generated, belum SN</span>
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-zinc-200 dark:bg-zinc-700"></span> Belum generate</span>
            </div>

            {{-- SPEKTEK LIST --}}
            <div class="space-y-3">
                @foreach ($this->spekteks as $spektek)
                    @php
                        $qtyTotal = collect($spektek['sub_items'])->sum('qty_total');
                        $generated = collect($spektek['sub_items'])->sum('generated');
                        $sn = collect($spektek['sub_items'])->sum('sn_assigned');
                        $snPct = $qtyTotal > 0 ? round($sn / $qtyTotal * 100) : 0;
                        $genOnlyPct = $qtyTotal > 0 ? round(($generated - $sn) / $qtyTotal * 100) : 0;
                    @endphp
                    <div x-data="{ open: {{ $spektek['composite'] && $generated > 0 ? 'true' : 'false' }} }"
                        wire:key="spektek-{{ $spektek['id'] }}"
                        class="rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex items-center gap-2">
                                    <flux:heading class="truncate">{{ $spektek['name'] }}</flux:heading>
                                    <flux:badge size="sm" :color="$spektek['composite'] ? 'blue' : 'zinc'">
                                        {{ count($spektek['sub_items']) }} {{ $spektek['composite'] ? 'sub-item' : 'item' }}
                                    </flux:badge>
                                </div>
                                <div class="flex h-2 w-full max-w-md overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-700">
                                    <div class="bg-emerald-500" style="width: {{ $snPct }}%"></div>
                                    <div class="bg-blue-400" style="width: {{ $genOnlyPct }}%"></div>
                                </div>
                                <flux:text size="xs">
                                    @if ($generated === 0)
                                        Belum ada unit digenerate — total {{ $qtyTotal }} {{ $spektek['sub_items'][0]['unit'] }}
                                    @else
                                        {{ $sn }} ber-SN · {{ $generated - $sn }} belum SN · {{ $qtyTotal - $generated }} belum generate — total {{ $qtyTotal }} {{ $spektek['sub_items'][0]['unit'] }}
                                    @endif
                                </flux:text>
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($spektek['composite'])
                                    <flux:button size="sm" variant="ghost" x-on:click="open = !open">
                                        <span x-show="!open">Lihat sub-item</span>
                                        <span x-show="open" x-cloak>Tutup</span>
                                    </flux:button>
                                @endif
                                <a href="{{ route('inventaris.spektek', [$projectId, $spektek['id']]) }}" wire:navigate>
                                    @if ($generated === 0)
                                        <flux:button size="sm" variant="primary" icon="plus-circle">Generate</flux:button>
                                    @else
                                        <flux:button size="sm" variant="outline" icon:trailing="chevron-right">Kelola</flux:button>
                                    @endif
                                </a>
                            </div>
                        </div>
                        @if ($spektek['composite'])
                            <div x-show="open" x-collapse x-cloak class="border-t border-zinc-100 dark:border-zinc-700/50">
                                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                                    @foreach ($spektek['sub_items'] as $item)
                                        <div class="flex items-center justify-between gap-3 px-5 py-3" wire:key="sub-{{ $item['id'] }}">
                                            <flux:text size="sm">{{ $item['name'] }}</flux:text>
                                            <flux:text size="xs" class="shrink-0">
                                                @if ($item['generated'] < $item['qty_total'])
                                                    {{ $item['generated'] }}/{{ $item['qty_total'] }} digenerate · {{ $item['sn_assigned'] }} ber-SN
                                                @else
                                                    {{ $item['sn_assigned'] }}/{{ $item['qty_total'] }} ber-SN
                                                @endif
                                            </flux:text>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
