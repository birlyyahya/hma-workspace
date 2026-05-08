<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array{total:int,available:int,inuse:int,maintenance:int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'total'       => 248,
            'available'   => 174,
            'inuse'       => 58,
            'maintenance' => 16,
        ];
    }

    /**
     * @return array<int, array{name:string,category:string,qty:int,status:string}>
     */
    #[Computed]
    public function lowStock(): array
    {
        return [
            ['name' => 'Helm Safety',          'category' => 'APD',        'qty' => 4,  'status' => 'low'],
            ['name' => 'Sarung Tangan Karet',  'category' => 'APD',        'qty' => 8,  'status' => 'low'],
            ['name' => 'Toner Printer A3',     'category' => 'Office',     'qty' => 2,  'status' => 'critical'],
            ['name' => 'Kabel LAN Cat6 (m)',   'category' => 'IT',         'qty' => 12, 'status' => 'low'],
        ];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs h-full flex flex-col">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 size-10 rounded-xl bg-amber-50 ring-1 ring-amber-100 flex items-center justify-center">
                <flux:icon name="cube" class="size-5 text-amber-600" />
            </div>
            <div class="min-w-0">
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Inventaris</flux:heading>
                <flux:description class="text-xs text-zinc-500">Status stok & ketersediaan</flux:description>
            </div>
        </div>
        <a href="{{ route('inventaris') }}" wire:navigate
            class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Detail <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    <div class="mt-5">
        <p class="text-xs font-medium text-zinc-500">Total aset</p>
        <p class="mt-1 text-3xl sm:text-4xl font-semibold text-zinc-900 tabular-nums tracking-tight">
            {{ number_format($this->stats['total'], 0, ',', '.') }}
            <span class="text-sm font-normal text-zinc-400">unit</span>
        </p>
    </div>

    @php
        $total = max($this->stats['total'], 1);
        $pAvail = round($this->stats['available'] / $total * 100, 1);
        $pUse   = round($this->stats['inuse']     / $total * 100, 1);
        $pMaint = round($this->stats['maintenance'] / $total * 100, 1);
    @endphp

    <div class="mt-4 h-2 rounded-full bg-zinc-100 overflow-hidden flex">
        <div class="bg-emerald-500 h-full transition-all duration-500" style="width: {{ $pAvail }}%"></div>
        <div class="bg-blue-500 h-full transition-all duration-500"    style="width: {{ $pUse }}%"></div>
        <div class="bg-amber-500 h-full transition-all duration-500"   style="width: {{ $pMaint }}%"></div>
    </div>

    <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
        @php
            $legend = [
                ['label' => 'Tersedia',    'value' => $this->stats['available'],   'percent' => $pAvail, 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-600'],
                ['label' => 'Dipakai',     'value' => $this->stats['inuse'],       'percent' => $pUse,   'dot' => 'bg-blue-500',    'text' => 'text-blue-600'],
                ['label' => 'Maintenance', 'value' => $this->stats['maintenance'], 'percent' => $pMaint, 'dot' => 'bg-amber-500',   'text' => 'text-amber-600'],
            ];
        @endphp
        @foreach ($legend as $l)
            <div class="rounded-lg border border-zinc-200 p-2.5">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $l['dot'] }}"></span>
                    <span class="text-[10px] font-medium uppercase tracking-wide text-zinc-500 truncate">{{ $l['label'] }}</span>
                </div>
                <div class="mt-1 flex items-baseline justify-between gap-1">
                    <span class="text-lg font-semibold text-zinc-900 tabular-nums">{{ $l['value'] }}</span>
                    <span class="text-[10px] {{ $l['text'] }} font-medium tabular-nums">{{ $l['percent'] }}%</span>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-5 flex-1">
        <div class="flex items-center justify-between mb-2.5">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Stok Menipis</p>
            <flux:badge size="sm" color="amber" variant="pill">{{ count($this->lowStock) }}</flux:badge>
        </div>
        <div class="space-y-2">
            @foreach ($this->lowStock as $item)
                @php
                    $isCritical = $item['status'] === 'critical';
                @endphp
                <div wire:key="inv-{{ $loop->index }}"
                    class="flex items-center justify-between gap-3 rounded-xl border border-zinc-100 p-2.5 hover:border-zinc-200 transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="size-8 rounded-lg flex items-center justify-center
                            {{ $isCritical ? 'bg-rose-50 text-rose-600' : 'bg-amber-50 text-amber-600' }}">
                            <flux:icon name="exclamation-triangle" class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-zinc-900 truncate">{{ $item['name'] }}</p>
                            <p class="text-xs text-zinc-500">{{ $item['category'] }}</p>
                        </div>
                    </div>
                    <span class="text-sm font-semibold tabular-nums {{ $isCritical ? 'text-rose-600' : 'text-amber-600' }}">
                        {{ $item['qty'] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>
