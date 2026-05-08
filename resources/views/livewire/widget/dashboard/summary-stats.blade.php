<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    /**
     * @return array<int, array{
     *   key:string,label:string,value:int|string,delta:string,trend:string,
     *   route:string,icon:string,color:string
     * }>
     */
    #[Computed]
    public function cards(): array
    {
        $izin = $this->fetchIzin();

        return [
            [
                'key'   => 'inventaris',
                'label' => 'Inventaris',
                'value' => 248,
                'delta' => '+8 unit',
                'trend' => 'up',
                'route' => 'inventaris',
                'icon'  => 'cube',
                'color' => 'amber',
            ],
            [
                'key'   => 'event',
                'label' => 'Event Aktif',
                'value' => 5,
                'delta' => '2 minggu ini',
                'trend' => 'up',
                'route' => 'events',
                'icon'  => 'calendar-days',
                'color' => 'violet',
            ],
            [
                'key'   => 'cash_advance',
                'label' => 'Cash Advance',
                'value' => 'Rp 12,4 Jt',
                'delta' => '3 pengajuan',
                'trend' => 'up',
                'route' => 'cashadvance',
                'icon'  => 'banknotes',
                'color' => 'emerald',
            ],
            [
                'key'   => 'dar',
                'label' => 'DAR Berjalan',
                'value' => 64,
                'delta' => '12 selesai',
                'trend' => 'up',
                'route' => 'dar',
                'icon'  => 'clipboard-document-list',
                'color' => 'blue',
            ],
            [
                'key'   => 'project',
                'label' => 'Project',
                'value' => 18,
                'delta' => '4 aktif',
                'trend' => 'up',
                'route' => 'projects',
                'icon'  => 'briefcase',
                'color' => 'teal',
            ],
            [
                'key'   => 'izin',
                'label' => 'Pengajuan Izin',
                'value' => $izin['total'],
                'delta' => $izin['approved'].' disetujui',
                'trend' => 'up',
                'route' => 'izin',
                'icon'  => 'document-check',
                'color' => 'rose',
            ],
        ];
    }

    /** @return array{total:int,approved:int} */
    protected function fetchIzin(): array
    {
        return Cache::remember(
            'dashboard_izin_summary_'.Auth::user()->username,
            now()->addMinutes(15),
            function (): array {
                try {
                    $response = Http::timeout(5)->get(
                        config('services.api_izin').'/global/izin/dashboard/'.Auth::user()->username
                    );

                    if (! $response->successful()) {
                        return ['total' => 0, 'approved' => 0];
                    }

                    $json = $response->json();

                    return [
                        'total'    => (int) ($json['data']['all_izin'] ?? 0),
                        'approved' => (int) ($json['data']['approve_izin'] ?? 0),
                    ];
                } catch (\Throwable) {
                    return ['total' => 0, 'approved' => 0];
                }
            }
        );
    }
}; ?>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
    @foreach ($this->cards as $card)
        @php
            $bg     = "bg-{$card['color']}-50";
            $ring   = "ring-{$card['color']}-100";
            $text   = "text-{$card['color']}-600";
            $glow   = "from-{$card['color']}-500/10";
        @endphp

        <a href="{{ route($card['route']) }}" wire:navigate
            wire:key="kpi-{{ $card['key'] }}"
            class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs transition hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-md">

            <div class="pointer-events-none absolute -right-8 -top-8 size-24 rounded-full bg-linear-to-br {{ $glow }} to-transparent blur-2xl"></div>

            <div class="relative flex items-start justify-between">
                <div class="flex size-9 items-center justify-center rounded-xl {{ $bg }} ring-1 {{ $ring }}">
                    <flux:icon :name="$card['icon']" class="size-4.5 {{ $text }}" />
                </div>
                <flux:icon name="arrow-up-right" class="size-4 text-zinc-300 transition group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-hover:text-zinc-500" />
            </div>

            <div class="relative mt-3">
                <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-400">{{ $card['label'] }}</p>
                <p class="mt-0.5 text-xl font-semibold text-zinc-900 tabular-nums tracking-tight">
                    {{ $card['value'] }}
                </p>
                <div class="mt-1.5 flex items-center gap-1 text-[11px]">
                    <flux:icon :name="$card['trend'] === 'up' ? 'arrow-trending-up' : 'arrow-trending-down'"
                        class="size-3 {{ $card['trend'] === 'up' ? 'text-emerald-500' : 'text-rose-500' }}" />
                    <span class="text-zinc-500">{{ $card['delta'] }}</span>
                </div>
            </div>
        </a>
    @endforeach
</div>
