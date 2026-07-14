<?php

use App\Services\ProjectCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public $id;
    public $user_id;

    public array $timelines = [];
    public array $months = [];

    private const COLORS = [
        'from-blue-500 to-indigo-500',
        'from-emerald-500 to-teal-500',
        'from-orange-500 to-amber-500',
        'from-pink-500 to-rose-500',
        'from-violet-500 to-purple-500',
        'from-sky-500 to-cyan-500',
    ];

    public function placeholder()
    {
        return view('components.placeholder.ph_project_timeline_gantt');
    }

    public function mount(): void
    {
        $this->loadTimelines();
    }

    #[On('timelineLoad')]
    #[On('timelinesUpdated')]
    public function loadTimelines(): void
    {
        $this->timelines = app(ProjectCache::class)->timelines((int) $this->id);

        $this->buildMonths();
    }

    private function buildMonths(): void
    {
        if (empty($this->timelines)) {
            $this->months = [];

            return;
        }

        $start = collect($this->timelines)->min('start_date');
        $end = collect($this->timelines)->max('end_date');

        $period = CarbonPeriod::create(
            Carbon::parse($start)->startOfMonth(),
            '1 month',
            Carbon::parse($end)->endOfMonth()
        );

        $this->months = collect($period)->map(fn ($date) => [
            'label' => $date->locale('id')->translatedFormat('M Y'),
            'value' => $date->format('Y-m'),
        ])->values()->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        if (empty($this->months) || empty($this->timelines)) {
            return [];
        }

        $monthValues = collect($this->months)->pluck('value')->values()->all();
        $monthCount = count($monthValues);
        $palette = self::COLORS;

        return collect($this->timelines)
            ->sortBy('start_date')
            ->values()
            ->map(function ($tl, $index) use ($monthValues, $monthCount, $palette) {
                $startMonth = Carbon::parse($tl['start_date'])->format('Y-m');
                $endMonth = Carbon::parse($tl['end_date'])->format('Y-m');

                $startIdx = array_search($startMonth, $monthValues, true);
                $endIdx = array_search($endMonth, $monthValues, true);

                if ($startIdx === false) {
                    $startIdx = 0;
                }

                if ($endIdx === false) {
                    $endIdx = $monthCount - 1;
                }

                $startIdx = max(0, min($startIdx, $monthCount - 1));
                $endIdx = max($startIdx, min($endIdx, $monthCount - 1));

                return [
                    'id' => $tl['id'] ?? $index,
                    'title' => $tl['title'] ?? 'Untitled',
                    'start_label' => Carbon::parse($tl['start_date'])->locale('id')->translatedFormat('d M Y'),
                    'end_label' => Carbon::parse($tl['end_date'])->locale('id')->translatedFormat('d M Y'),
                    'col_start' => $startIdx + 2, // +1 for label column, +1 because grid is 1-indexed
                    'col_span' => $endIdx - $startIdx + 1,
                    'color' => $palette[$index % count($palette)],
                ];
            })
            ->all();
    }

    public function getTodayMarkerProperty(): ?array
    {
        if (empty($this->months)) {
            return null;
        }

        $today = Carbon::now();
        $currentMonth = $today->format('Y-m');
        $idx = array_search($currentMonth, collect($this->months)->pluck('value')->all(), true);

        if ($idx === false) {
            return null;
        }

        $progress = ($today->day - 1) / max(1, $today->daysInMonth - 1);

        return [
            'month_index' => $idx,
            'progress' => $progress,
        ];
    }
}; ?>

<div>
    <div class="bg-white border rounded-2xl overflow-hidden">
        {{-- HEADER --}}
        <div class="flex flex-wrap items-center justify-between gap-2 px-4 sm:px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <flux:icon name="chart-bar" class="w-5 h-5 text-gray-400" />
                <div>
                    <h2 class="text-base font-semibold">Gantt Chart Timeline</h2>
                    <p class="text-xs text-gray-500">Visualisasi rentang timeline per bulan</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 ring-1 ring-gray-200">
                    {{ count($timelines) }} timeline
                </span>
                @if(count($months))
                <span class="rounded-full bg-gray-100 px-2.5 py-1 ring-1 ring-gray-200">
                    {{ count($months) }} bulan
                </span>
                @endif
            </div>
        </div>

        @if(empty($timelines) || empty($months))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon name="chart-bar" class="w-10 h-10 text-gray-300 mb-3" />
            <p class="text-sm font-medium text-gray-600">Belum ada timeline</p>
            <p class="text-xs text-gray-400 mt-1">Tambah timeline untuk melihat gantt chart.</p>
        </div>
        @else
        @php
            $rows = $this->rows;
            $todayMarker = $this->todayMarker;
            $monthCount = count($months);
            $labelColWidth = 200;
            $monthMinWidth = 100;
            $totalMinWidth = $labelColWidth + ($monthCount * $monthMinWidth);
            $gridTemplate = $labelColWidth.'px repeat('.$monthCount.', minmax('.$monthMinWidth.'px, 1fr))';
        @endphp

        <div class="overflow-x-auto">
            <div class="min-w-max" style="min-width: {{ $totalMinWidth }}px;">

                {{-- HEADER ROW: months --}}
                <div class="grid border-b bg-gray-50 sticky top-0 z-10" style="grid-template-columns: {{ $gridTemplate }};">
                    <div class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 border-r bg-gray-50 sticky left-0 z-10">
                        Timeline
                    </div>
                    @foreach($months as $i => $month)
                    @php
                        $isCurrent = $todayMarker && $todayMarker['month_index'] === $i;
                    @endphp
                    <div class="px-2 py-3 text-center text-[11px] font-semibold uppercase tracking-wide border-r last:border-r-0 {{ $isCurrent ? 'text-blue-700 bg-blue-50/60' : 'text-gray-500' }}">
                        {{ $month['label'] }}
                    </div>
                    @endforeach
                </div>

                {{-- BODY: timeline rows --}}
                <div class="relative">

                    {{-- Today marker (vertical line spanning all rows) --}}
                    @if($todayMarker)
                    @php
                        $markerCol = $todayMarker['month_index'] + 2;
                    @endphp
                    <div class="pointer-events-none absolute inset-y-0 z-0 grid" style="grid-template-columns: {{ $gridTemplate }}; left: 0; right: 0;">
                        <div></div>
                        @foreach($months as $i => $month)
                        <div class="relative">
                            @if($i === $todayMarker['month_index'])
                            <div class="absolute inset-y-0 w-px bg-blue-400/70" style="left: {{ $todayMarker['progress'] * 100 }}%;">
                                <div class="absolute -top-1 -translate-x-1/2 size-2 rounded-full bg-blue-500"></div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @foreach($rows as $row)
                    <div wire:key="gantt-row-{{ $row['id'] }}" class="grid items-center border-b last:border-b-0 hover:bg-gray-50/60 transition" style="grid-template-columns: {{ $gridTemplate }}; min-height: 56px;">
                        {{-- Label cell --}}
                        <div class="px-4 py-3 border-r bg-white sticky left-0 z-1">
                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $row['title'] }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">
                                {{ $row['start_label'] }} – {{ $row['end_label'] }}
                            </p>
                        </div>

                        {{-- Bar cell (spans across months) --}}
                        <div class="px-1 py-2 relative" style="grid-column: {{ $row['col_start'] }} / span {{ $row['col_span'] }};">
                            <div class="h-7 rounded-lg bg-linear-to-r {{ $row['color'] }} shadow-sm ring-1 ring-black/5 flex items-center px-3"
                                 title="{{ $row['title'] }} · {{ $row['start_label'] }} – {{ $row['end_label'] }}">
                                <span class="text-[11px] font-semibold text-white truncate drop-shadow-sm">
                                    {{ $row['title'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                    @endforeach

                </div>
            </div>
        </div>
        @endif
    </div>
</div>
