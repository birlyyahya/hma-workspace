<?php

use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /** @var int Jumlah slice project teratas sebelum digabung ke "Lainnya". */
    public int $topLimit = 7;

    /** Palet kategori — dipakai bersama oleh ApexCharts & legend kustom. */
    private const PALETTE = [
        '#8b5cf6', // violet
        '#3b82f6', // blue
        '#10b981', // emerald
        '#f59e0b', // amber
        '#f43f5e', // rose
        '#06b6d4', // cyan
        '#d946ef', // fuchsia
        '#84cc16', // lime
    ];

    private const OTHER_COLOR = '#a1a1aa'; // zinc-400

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.placeholder.ph_project_activity');
    }

    #[On('updatedTimeline')]
    #[On('updatedCardTaskDar')]
    public function refresh(): void
    {
        unset($this->stats);
    }

    /**
     * Agregasi aktivitas DAR 14 hari terakhir per project (aktivitas non-project diabaikan).
     *
     * @return array{
     *     total:int,
     *     project_count:int,
     *     range:array{from:string,to:string},
     *     top:?array{name:string,code:?string,count:int,pct:float,color:string},
     *     slices:array<int, array{name:string,code:?string,count:int,pct:float,color:string,is_other:bool,members:array<int,array{name:string,code:?string,count:int,pct:float}>}>
     * }
     */
    #[Computed]
    public function stats(): array
    {
        $to = Carbon::today();
        $from = $to->copy()->subDays(13);

        $empty = [
            'total' => 0,
            'project_count' => 0,
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'top' => null,
            'slices' => [],
        ];

        try {
            $rows = app(DarCache::class)
                ->listForRange('all', null, $from->toDateString(), $to->toDateString())['data'] ?? [];
        } catch (\Throwable) {
            return $empty;
        }

        $counts = collect($rows)
            ->filter(fn ($row) => ! empty($row['project_id']))
            ->countBy(fn ($row) => $row['project_id'])
            ->sortDesc();

        $total = $counts->sum();

        if ($total === 0) {
            return $empty;
        }

        $projectIds = $counts->keys();
        $projects = collect(app(ProjectCache::class)->allProjects())
            ->whereIn('id', $projectIds->all())
            ->keyBy('id');

        $named = $counts->map(function (int $count, $id) use ($projects): array {
            $project = $projects->get($id);

            return [
                'name' => $project['name'] ?? 'Project #'.$id,
                'code' => $project['code'] ?? null,
                'count' => $count,
            ];
        })->values();

        $visible = $named->take($this->topLimit);
        $rest = $named->slice($this->topLimit);

        $slices = $visible->values()->map(fn (array $item, int $i): array => [
            'name' => $item['name'],
            'code' => $item['code'],
            'count' => $item['count'],
            'pct' => round($item['count'] / $total * 100, 1),
            'color' => self::PALETTE[$i % count(self::PALETTE)],
            'is_other' => false,
            'members' => [],
        ])->all();

        if ($rest->isNotEmpty()) {
            $restCount = (int) $rest->sum('count');
            $slices[] = [
                'name' => 'Lainnya ('.$rest->count().' project)',
                'code' => null,
                'count' => $restCount,
                'pct' => round($restCount / $total * 100, 1),
                'color' => self::OTHER_COLOR,
                'is_other' => true,
                'members' => $rest->values()->map(fn (array $item): array => [
                    'name' => $item['name'],
                    'code' => $item['code'],
                    'count' => $item['count'],
                    'pct' => round($item['count'] / $total * 100, 1),
                ])->all(),
            ];
        }

        return [
            'total' => $total,
            'project_count' => $projectIds->count(),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'top' => $slices[0] ?? null,
            'slices' => $slices,
        ];
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 shadow-xs min-h-100 overflow-auto lg:overflow-hidden">
    @php $stats = $this->stats; @endphp

    <div class="flex flex-wrap items-center justify-between gap-3 p-4 pb-0 sm:p-5 sm:pb-0">
        <div class="flex items-center gap-3 min-w-0">
            <div class="shrink-0 size-10 rounded-xl bg-emerald-50 ring-1 ring-emerald-100 flex items-center justify-center">
                <flux:icon name="chart-pie" class="size-5 text-emerald-600" />
            </div>
            <div class="min-w-0">
                <h2 class="text-base font-semibold text-zinc-900 leading-tight">Aktivitas Project</h2>
                <p class="text-xs text-zinc-500">
                    Distribusi aktivitas DAR &middot;
                    {{ Carbon::parse($stats['range']['from'])->translatedFormat('d M') }} –
                    {{ Carbon::parse($stats['range']['to'])->translatedFormat('d M Y') }}
                </p>
            </div>
        </div>
        <flux:badge size="sm" color="emerald" variant="pill" class="shrink-0">
            {{ $stats['project_count'] }} project aktif
        </flux:badge>
    </div>

    @if ($stats['total'] === 0)
        <div class="p-4 sm:p-5">
            <div class="rounded-xl border border-dashed border-zinc-200 p-8 text-center sm:p-10">
                <div class="mx-auto size-12 rounded-full bg-zinc-100 flex items-center justify-center">
                    <flux:icon name="chart-pie" class="size-6 text-zinc-400" />
                </div>
                <p class="mt-3 text-sm font-medium text-zinc-600">Belum ada aktivitas</p>
                <p class="mt-0.5 text-xs text-zinc-400">Tidak ada aktivitas DAR terkait project dalam 2 minggu terakhir</p>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 p-4 sm:p-5 lg:grid-cols-5 lg:items-center lg:gap-2">

            {{-- Donut --}}
            <div class="relative z-10 mx-auto flex h-52 w-52 items-center justify-center sm:h-60 sm:w-60 lg:col-span-2 lg:h-64 lg:w-full" wire:ignore
                x-data="{
                    chart: null,
                    slices: @js($stats['slices']),
                    truncate(label, max = 34) {
                        return label.length > max ? label.slice(0, max - 1).trimEnd() + '…' : label;
                    },
                    init() {
                        this.$nextTick(() => {
                            if (!window.ApexCharts || this.chart) return;
                            this.chart = new ApexCharts(this.$refs.donut, {
                                chart: { type: 'donut', height: '100%', fontFamily: 'inherit', animations: { speed: 500 } },
                                series: this.slices.map(s => s.count),
                                labels: this.slices.map(s => this.truncate(s.code ? `[${s.code}] ${s.name}` : s.name)),
                                colors: this.slices.map(s => s.color),
                                stroke: { width: 2, colors: ['#ffffff'] },
                                dataLabels: { enabled: false },
                                legend: { show: false },
                                plotOptions: { pie: { expandOnClick: false, donut: { size: '74%', labels: { show: false } } } },
                                tooltip: {
                                    theme: 'light',
                                    y: { formatter: (val) => val + ' aktivitas' },
                                    style: { fontSize: '12px' },
                                },
                                states: { hover: { filter: { type: 'lighten', value: 0.08 } } },
                                responsive: [{ breakpoint: 640, options: { chart: { height: '100%' } } }],
                            });
                            this.chart.render();
                        });
                    },
                    destroy() { this.chart?.destroy(); },
                }">
                <div x-ref="donut" class="h-full w-full"></div>

                {{-- Total di tengah donut --}}
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-2xl font-bold text-zinc-900 tabular-nums leading-none sm:text-3xl">{{ $stats['total'] }}</span>
                    <span class="mt-1 text-[11px] font-medium uppercase tracking-wide text-zinc-400">Aktivitas</span>
                </div>
            </div>

            {{-- Ranking / legend kustom --}}
            <div class="min-w-0 lg:col-span-3">
                @if ($stats['top'])
                    <div class="mb-3 flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2.5 ring-1 ring-zinc-100">
                        <flux:icon name="trophy" class="size-4 text-amber-500 shrink-0" />
                        <p class="text-xs text-zinc-500 min-w-0 truncate">
                            Paling aktif
                            <span class="font-semibold text-zinc-900">
                                @if ($stats['top']['code'])
                                    <span class="text-zinc-400">[{{ $stats['top']['code'] }}]</span>
                                @endif
                                {{ $stats['top']['name'] }}
                            </span>
                        </p>
                        <span class="ml-auto shrink-0 text-xs font-semibold text-zinc-900 tabular-nums">{{ $stats['top']['pct'] }}%</span>
                    </div>
                @endif

                <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
                    @foreach ($stats['slices'] as $slice)
                        <div wire:key="slice-{{ $loop->index }}" class="group" @if ($slice['is_other']) x-data="{ open: false }" @endif>
                            <div class="flex items-center gap-2.5 {{ $slice['is_other'] ? 'cursor-pointer' : '' }}"
                                @if ($slice['is_other']) @click="open = ! open" @endif>
                                <span class="size-2.5 rounded-full shrink-0" style="background-color: {{ $slice['color'] }}"></span>
                                <p class="min-w-0 flex-1 truncate text-sm text-zinc-700">
                                    @if ($slice['code'])
                                        <span class="text-zinc-400">[{{ $slice['code'] }}]</span>
                                    @endif
                                    {{ $slice['name'] }}
                                </p>
                                <span class="shrink-0 text-xs text-zinc-400 tabular-nums">{{ $slice['count'] }}</span>
                                <span class="shrink-0 w-11 text-right text-xs font-semibold text-zinc-900 tabular-nums">{{ $slice['pct'] }}%</span>
                                @if ($slice['is_other'])
                                    <flux:icon name="chevron-down" x-bind:class="open ? 'rotate-180' : ''" class="size-3.5 shrink-0 text-zinc-400 transition-transform" />
                                @endif
                            </div>
                            <div class="mt-1.5 ml-5 h-1.5 rounded-full bg-zinc-100 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                    style="width: {{ $slice['pct'] }}%; background-color: {{ $slice['color'] }}"></div>
                            </div>

                            @if ($slice['is_other'])
                                <div x-show="open" x-collapse class="mt-2 ml-5 space-y-1.5 border-l border-zinc-200 pl-3">
                                    @foreach ($slice['members'] as $member)
                                        <div wire:key="slice-{{ $loop->parent->index }}-member-{{ $loop->index }}" class="flex items-center gap-2">
                                            <p class="min-w-0 flex-1 truncate text-xs text-zinc-500">
                                                @if ($member['code'])
                                                    <span class="text-zinc-400">[{{ $member['code'] }}]</span>
                                                @endif
                                                {{ $member['name'] }}
                                            </p>
                                            <span class="shrink-0 text-[11px] text-zinc-400 tabular-nums">{{ $member['count'] }}</span>
                                            <span class="shrink-0 w-9 text-right text-[11px] font-medium text-zinc-600 tabular-nums">{{ $member['pct'] }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

