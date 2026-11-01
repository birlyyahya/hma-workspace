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

    /** Satu keluarga warna: merah brand memudar menurut peringkat. */
    private const PALETTE = ['#991b1b', '#b91c1c', '#dc2626', '#ef4444', '#f87171', '#fca5a5', '#fecaca'];

    private const OTHER_COLOR = '#cbd5e1';

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

<div class="bg-white rounded-2xl shadow-sm">
    @php $stats = $this->stats; @endphp

    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 pt-5 sm:px-6 sm:pt-6">
        <h2 class="text-base font-semibold tracking-tight text-zinc-900">Aktivitas Project</h2>
        <p class="text-xs text-zinc-500 tabular-nums">
            @if ($stats['total'] > 0)
                {{ $stats['project_count'] }} project &middot;
            @endif
            {{ Carbon::parse($stats['range']['from'])->translatedFormat('d M') }} –
            {{ Carbon::parse($stats['range']['to'])->translatedFormat('d M Y') }}
        </p>
    </div>

    @if ($stats['total'] === 0)
        <div class="px-5 py-14 text-center sm:px-6">
            <p class="text-sm font-medium text-zinc-700">Belum ada aktivitas</p>
            <p class="mt-1 text-xs text-zinc-500">Tidak ada aktivitas DAR terkait project dalam 2 minggu terakhir</p>
        </div>
    @else
        <div class="grid grid-cols-1 gap-6 px-5 pb-5 pt-4 sm:px-6 sm:pb-6 lg:grid-cols-[17rem_minmax(0,1fr)] lg:items-center lg:gap-10">

            {{-- Donut --}}
            <div class="relative mx-auto size-56 lg:size-64" wire:ignore
                x-data="{
                    chart: null,
                    slices: @js($stats['slices']),
                    init() {
                        this.$nextTick(() => {
                            if (!window.ApexCharts || this.chart) return;
                            this.chart = new ApexCharts(this.$refs.donut, {
                                chart: { type: 'donut', height: '100%', fontFamily: 'inherit', animations: { speed: 400 } },
                                series: this.slices.map(s => s.count),
                                labels: this.slices.map(s => (s.code ? `[${s.code}] ` : '') + s.name),
                                colors: this.slices.map(s => s.color),
                                stroke: { width: 3, colors: ['#ffffff'] },
                                dataLabels: { enabled: false },
                                legend: { show: false },
                                plotOptions: { pie: { expandOnClick: false, donut: { size: '78%', labels: { show: false } } } },
                                tooltip: { theme: 'light', y: { formatter: (val) => val + ' aktivitas' } },
                                states: { hover: { filter: { type: 'none' } }, active: { filter: { type: 'none' } } },
                            });
                            this.chart.render();
                        });
                    },
                    destroy() { this.chart?.destroy(); },
                }">
                <div x-ref="donut" class="size-full"></div>

                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-4xl font-semibold tracking-tight text-zinc-900 tabular-nums leading-none">{{ $stats['total'] }}</span>
                    <span class="mt-1.5 text-xs text-zinc-500">aktivitas DAR</span>
                </div>
            </div>

            {{-- Peringkat = legenda --}}
            <ul class="min-w-0 divide-y divide-zinc-100">
                @foreach ($stats['slices'] as $slice)
                    <li wire:key="slice-{{ $loop->index }}" class="py-2.5" @if ($slice['is_other']) x-data="{ open: false }" @endif>
                        <div class="flex items-center gap-3">
                            <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $slice['color'] }}" aria-hidden="true"></span>

                            @if ($slice['is_other'])
                                <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                                    class="flex min-w-0 flex-1 cursor-pointer items-center gap-1.5 text-start text-sm text-zinc-800">
                                    <span class="truncate">{{ $slice['name'] }}</span>
                                    <flux:icon name="chevron-down" x-bind:class="open ? 'rotate-180' : ''" class="size-3.5 shrink-0 text-zinc-500 transition-transform motion-reduce:transition-none" />
                                </button>
                            @else
                                <p class="min-w-0 flex-1 truncate text-sm text-zinc-800">
                                    @if ($slice['code'])
                                        <span class="tabular-nums text-zinc-500">[{{ $slice['code'] }}]</span>
                                    @endif
                                    {{ $slice['name'] }}
                                </p>
                            @endif

                            <span class="shrink-0 text-xs tabular-nums text-zinc-500">{{ $slice['count'] }}</span>
                            <span class="w-12 shrink-0 text-end text-sm font-semibold tabular-nums text-zinc-900">{{ $slice['pct'] }}%</span>
                        </div>

                        @if ($slice['is_other'])
                            <ul x-show="open" x-collapse class="mt-2 ml-5 space-y-1.5 border-s border-zinc-200 ps-3">
                                @foreach ($slice['members'] as $member)
                                    <li wire:key="slice-{{ $loop->parent->index }}-member-{{ $loop->index }}" class="flex items-center gap-3">
                                        <p class="min-w-0 flex-1 truncate text-xs text-zinc-600">
                                            @if ($member['code'])
                                                <span class="tabular-nums text-zinc-500">[{{ $member['code'] }}]</span>
                                            @endif
                                            {{ $member['name'] }}
                                        </p>
                                        <span class="text-xs tabular-nums text-zinc-500">{{ $member['count'] }}</span>
                                        <span class="w-10 text-end text-xs font-medium tabular-nums text-zinc-700">{{ $member['pct'] }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
