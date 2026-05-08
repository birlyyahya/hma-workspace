<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array{balance:string,used:int,limit:int,used_amount:string,limit_amount:string}
     */
    #[Computed]
    public function balance(): array
    {
        return [
            'balance'      => 'Rp 12.430.000',
            'used'         => 62,
            'limit'        => 100,
            'used_amount'  => 'Rp 7.570.000',
            'limit_amount' => 'Rp 20.000.000',
        ];
    }

    /**
     * @return array<int, array{title:string,amount:string,date:string,type:string}>
     */
    #[Computed]
    public function recentTx(): array
    {
        return [
            ['title' => 'Perjalanan Dinas Surabaya', 'amount' => '- Rp 1.250.000', 'date' => 'Kemarin',     'type' => 'out'],
            ['title' => 'Reimburse Bensin',         'amount' => '+ Rp   650.000',  'date' => '2 hari lalu', 'type' => 'in'],
            ['title' => 'Konsumsi Rapat',           'amount' => '- Rp   320.000',  'date' => '3 hari lalu', 'type' => 'out'],
        ];
    }
}; ?>

<div class="relative overflow-hidden rounded-2xl border border-emerald-100 bg-linear-to-br from-emerald-50 via-white to-white p-5 sm:p-6 shadow-xs h-full flex flex-col">
    <div class="pointer-events-none absolute -right-20 -top-20 size-56 rounded-full bg-emerald-200/40 blur-3xl"></div>
    <div class="pointer-events-none absolute -left-20 bottom-0 size-48 rounded-full bg-teal-200/30 blur-3xl"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 size-10 rounded-xl bg-emerald-500/10 ring-1 ring-emerald-200 flex items-center justify-center">
                <flux:icon name="banknotes" class="size-5 text-emerald-600" />
            </div>
            <div class="min-w-0">
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Cash Advance</flux:heading>
                <flux:description class="text-xs text-zinc-500">Saldo & transaksi terakhir</flux:description>
            </div>
        </div>
        <a href="{{ route('cashadvance') }}" wire:navigate
            class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Detail <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    <div class="relative mt-5">
        <p class="text-xs font-medium text-zinc-500">Saldo tersedia</p>
        <div class="mt-1 flex items-baseline gap-2">
            <span class="text-3xl sm:text-4xl font-semibold text-zinc-900 tabular-nums tracking-tight">
                {{ $this->balance['balance'] }}
            </span>
            <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600">
                <flux:icon name="arrow-trending-up" class="size-3.5" />
                +12,3%
            </span>
        </div>
    </div>

    <div class="relative mt-4">
        <div class="flex items-center justify-between text-xs text-zinc-500 mb-1.5">
            <span>Terpakai {{ $this->balance['used_amount'] }}</span>
            <span>Limit {{ $this->balance['limit_amount'] }}</span>
        </div>
        <div class="h-2 rounded-full bg-zinc-100 overflow-hidden">
            <div class="h-full bg-linear-to-r from-emerald-500 to-teal-500 transition-all duration-500"
                style="width: {{ $this->balance['used'] }}%"></div>
        </div>
    </div>

    <div class="relative mt-5 space-y-2 flex-1">
        @foreach ($this->recentTx as $tx)
            <div wire:key="ca-{{ $loop->index }}"
                class="flex items-center justify-between gap-3 rounded-xl border border-zinc-100 bg-white/70 backdrop-blur-sm p-3 hover:bg-white transition">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-8 rounded-lg flex items-center justify-center
                        {{ $tx['type'] === 'in' ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                        <flux:icon :name="$tx['type'] === 'in' ? 'arrow-down-left' : 'arrow-up-right'" class="size-4" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-zinc-900 truncate">{{ $tx['title'] }}</p>
                        <p class="text-xs text-zinc-500">{{ $tx['date'] }}</p>
                    </div>
                </div>
                <p class="text-sm font-semibold tabular-nums {{ $tx['type'] === 'in' ? 'text-emerald-600' : 'text-zinc-900' }}">
                    {{ $tx['amount'] }}
                </p>
            </div>
        @endforeach
    </div>
</div>
