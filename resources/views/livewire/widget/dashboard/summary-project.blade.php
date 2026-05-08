<?php

use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array{active:int,completed:int,onhold:int,total:int}
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'active'    => 4,
            'completed' => 11,
            'onhold'    => 3,
            'total'     => 18,
        ];
    }

    /**
     * @return array<int, array{name:string,client:string,progress:int,deadline:string,status:string}>
     */
    #[Computed]
    public function recent(): array
    {
        return [
            ['name' => 'Renovasi Kantor Pusat', 'client' => 'Internal', 'progress' => 72, 'deadline' => '15 Jun 2026', 'status' => 'on-track'],
            ['name' => 'Pembangunan Gudang B', 'client' => 'PT Jaya Konstruksi', 'progress' => 45, 'deadline' => '02 Aug 2026', 'status' => 'on-track'],
            ['name' => 'Sistem ERP Internal', 'client' => 'Internal IT', 'progress' => 90, 'deadline' => '20 May 2026', 'status' => 'review'],
            ['name' => 'Pengadaan Alat Berat', 'client' => 'Operasional', 'progress' => 30, 'deadline' => '10 Jul 2026', 'status' => 'risk'],
        ];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs h-full flex flex-col">
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 size-10 rounded-xl bg-teal-50 ring-1 ring-teal-100 flex items-center justify-center">
                <flux:icon name="briefcase" class="size-5 text-teal-600" />
            </div>
            <div class="min-w-0">
                <flux:heading size="lg" class="text-zinc-900 leading-tight">Ringkasan Project</flux:heading>
                <flux:description class="text-xs text-zinc-500">Status keseluruhan proyek aktif</flux:description>
            </div>
        </div>
        <a href="{{ route('projects') }}" wire:navigate
            class="shrink-0 inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
            Lihat semua <flux:icon name="arrow-right" class="size-3.5" />
        </a>
    </div>

    <div class="mt-5 grid grid-cols-3 gap-2">
        @php
            $cells = [
                ['label' => 'Aktif',     'value' => $this->stats['active'],    'dot' => 'bg-teal-500'],
                ['label' => 'Selesai',   'value' => $this->stats['completed'], 'dot' => 'bg-emerald-500'],
                ['label' => 'On Hold',   'value' => $this->stats['onhold'],    'dot' => 'bg-amber-500'],
            ];
        @endphp
        @foreach ($cells as $c)
            <div class="rounded-xl border border-zinc-200 p-3">
                <div class="flex items-center gap-1.5">
                    <span class="size-2 rounded-full {{ $c['dot'] }}"></span>
                    <span class="text-[11px] font-medium text-zinc-500 uppercase tracking-wide">{{ $c['label'] }}</span>
                </div>
                <p class="mt-1 text-xl font-semibold text-zinc-900 tabular-nums">{{ $c['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-5 space-y-3 flex-1">
        @foreach ($this->recent as $project)
            @php
                $statusMap = [
                    'on-track' => ['label' => 'On Track', 'color' => 'emerald'],
                    'review'   => ['label' => 'Review',   'color' => 'blue'],
                    'risk'     => ['label' => 'At Risk',  'color' => 'rose'],
                ];
                $status = $statusMap[$project['status']];
                $bar    = "bg-{$status['color']}-500";
            @endphp

            <div wire:key="prj-{{ $loop->index }}"
                class="rounded-xl border border-zinc-200 p-3 hover:border-zinc-300 transition">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-zinc-900 truncate">{{ $project['name'] }}</p>
                        <p class="text-xs text-zinc-500 truncate">{{ $project['client'] }} · Deadline {{ $project['deadline'] }}</p>
                    </div>
                    <flux:badge size="sm" :color="$status['color']" variant="pill">{{ $status['label'] }}</flux:badge>
                </div>
                <div class="mt-2.5 flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-zinc-100 overflow-hidden">
                        <div class="h-full {{ $bar }} transition-all duration-500" style="width: {{ $project['progress'] }}%"></div>
                    </div>
                    <span class="text-xs font-medium text-zinc-600 tabular-nums w-9 text-right">{{ $project['progress'] }}%</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
