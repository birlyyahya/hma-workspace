<?php

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public array $events = [];
    public string $date = '';
    public array $hours = [];
    public bool $loading = true;

    public int $rangeStartHour = 8;
    public int $rangeEndHour = 18;

    private const COLORS = ['blue', 'indigo', 'orange', 'emerald'];
    private const DEFAULT_START = 8;
    private const DEFAULT_END = 18;
    private const HOUR_WIDTH = 96;
    private const ROW_HEIGHT = 56;
    private const HEADER_HEIGHT = 44;

    #[On('updatedTimeline')]
    public function mount(): void
    {
        $this->loading = true;
        $this->date = now()->format('d/m/Y');

        try {
              if(Auth::user()->role_id < 3){
                $response = Http::get(env('API_IZIN').'/global/dar/list?limit=1000000')->json();
            }else {
                $response = Http::get(env('API_IZIN').'/global/dar/list?limit=1000000&team_user='.Auth::id())->json();
            }
            $this->buildTimeline($response['data'] ?? []);
        } catch (\Throwable $e) {
            $this->events = [];
            $this->hours = $this->buildHours(self::DEFAULT_START, self::DEFAULT_END);
            $this->rangeStartHour = self::DEFAULT_START;
            $this->rangeEndHour = self::DEFAULT_END;
        } finally {
            $this->loading = false;
        }
    }

    private function buildTimeline(array $data): void
    {
        $timelineDate = Carbon::createFromFormat('d/m/Y', $this->date);
        $dayStart = $timelineDate->copy()->startOfDay();
        $dayEnd = $timelineDate->copy()->endOfDay();

        $relevant = collect($data)
            ->filter(function ($item) use ($dayStart, $dayEnd) {
                $start = Carbon::parse($item['start_date']);
                $end = Carbon::parse($item['end_date']);
                $inDateRange = $start <= $dayEnd && $end >= $dayStart;
                $inProgress = in_array($item['status'] ?? null, [1, 2, 3], true);

                return $inDateRange || $inProgress;
            })
            ->values();

        $clamped = $relevant->map(function ($item) use ($dayStart, $dayEnd) {
            $start = Carbon::parse($item['start_date'])->max($dayStart);
            $end = Carbon::parse($item['end_date'])->min($dayEnd);

            return [
                'item' => $item,
                'start' => $start,
                'end' => $end->greaterThan($start) ? $end : $start->copy()->addMinutes(30),
            ];
        });

        [$rangeStart, $rangeEnd] = $this->computeRange($clamped);
        $this->rangeStartHour = $rangeStart;
        $this->rangeEndHour = $rangeEnd;
        $this->hours = $this->buildHours($rangeStart, $rangeEnd);

        $this->events = $clamped->values()->map(function ($entry, $index) use ($rangeStart) {
            $start = $entry['start'];
            $end = $entry['end'];

            $startHour = $start->hour + ($start->minute / 60);
            $endHour = $end->hour + ($end->minute / 60);

            return [
                'title' => $entry['item']['activity'] ?? 'Untitled',
                'description' => $entry['item']['description'] ?? null,
                'status' => $entry['item']['status'] ?? null,
                'start_label' => $start->format('H:i'),
                'end_label' => $end->format('H:i'),
                'offset' => max(0, $startHour - $rangeStart),
                'span' => max(0.25, $endHour - $startHour),
                'row' => $index + 1,
                'color' => self::COLORS[$index % count(self::COLORS)],
                'user' => $entry['item']['user'] ?? null,
            ];
        })->toArray();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{start: Carbon, end: Carbon}>  $clamped
     * @return array{0: int, 1: int}
     */
    private function computeRange($clamped): array
    {
        if ($clamped->isEmpty()) {
            return [self::DEFAULT_START, self::DEFAULT_END];
        }

        $minHour = (int) $clamped->map(fn ($e) => $e['start']->hour)->min();
        $maxHour = (int) $clamped->map(fn ($e) => $e['end']->copy()->addMinutes($e['end']->minute > 0 ? 60 : 0)->hour)->max();

        $maxHour = $clamped->map(fn ($e) => $e['end']->minute > 0 ? $e['end']->hour + 1 : $e['end']->hour)->max();
        $maxHour = (int) $maxHour;

        $start = min(self::DEFAULT_START, $minHour);
        $end = max(self::DEFAULT_END, $maxHour);

        $start = max(0, $start);
        $end = min(24, max($end, $start + 1));

        return [$start, $end];
    }

    private function buildHours(int $start, int $end): array
    {
        $period = CarbonPeriod::create(
            Carbon::today()->setTime($start, 0),
            '1 hour',
            Carbon::today()->setTime(min(23, $end), 0)
        );

        return collect($period)
            ->map(fn ($t) => $t->format('H:i'))
            ->values()
            ->toArray();
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_timeline_dar');
    }

    public function getHourWidthProperty(): int
    {
        return self::HOUR_WIDTH;
    }

    public function getRowHeightProperty(): int
    {
        return self::ROW_HEIGHT;
    }

    public function getHeaderHeightProperty(): int
    {
        return self::HEADER_HEIGHT;
    }
}; ?>

<div>
    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70 overflow-hidden">

        {{-- HEADER --}}
        <header class="flex items-center justify-between border-b border-slate-200/70 px-5 py-4">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-700">
                    <flux:icon name="clock" class="size-5" />
                </span>
                <div>
                    <h2 class="text-sm font-semibold tracking-tight text-slate-900">Task Timeline</h2>
                    <p class="text-xs text-slate-500">Agenda hari ini · {{ $date }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                <span class="rounded-full bg-slate-100 px-2.5 py-1 ring-1 ring-slate-200">
                    {{ count($events) }} task
                </span>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 ring-1 ring-slate-200">
                    {{ str_pad($rangeStartHour, 2, '0', STR_PAD_LEFT) }}:00 – {{ str_pad($rangeEndHour, 2, '0', STR_PAD_LEFT) }}:00
                </span>
            </div>
        </header>

        @if($loading)
            <div class="px-5 py-8 text-sm text-slate-500">Memuat timeline...</div>
        @elseif(empty($events))
            <div class="px-5 py-10 text-center text-sm text-slate-500">
                Belum ada task untuk ditampilkan pada timeline.
            </div>
        @else
            @php
                $hourWidth = $this->hourWidth;
                $rowHeight = $this->rowHeight;
                $headerHeight = $this->headerHeight;
                $hourCount = count($hours);
                $maxRow = collect($events)->max('row') ?? 1;
                $bodyHeight = $maxRow * $rowHeight + 16;
                $totalWidth = $hourCount * $hourWidth;
            @endphp

            <div class="overflow-x-auto">
                <div class="relative" style="min-width: {{ $totalWidth }}px;">

                    {{-- HOURS HEADER --}}
                    <div class="sticky top-0 z-20 flex border-b border-slate-200/70 bg-slate-50/80 backdrop-blur" style="height: {{ $headerHeight }}px;">
                        @foreach($hours as $hour)
                            <div class="flex shrink-0 items-center justify-center text-[11px] font-semibold uppercase tracking-wide text-slate-500" style="width: {{ $hourWidth }}px;">
                                {{ $hour }}
                            </div>
                        @endforeach
                    </div>

                    {{-- BODY --}}
                    <div class="relative bg-white" style="height: {{ $bodyHeight }}px;">

                        {{-- Vertical hour lines --}}
                        <div class="pointer-events-none absolute inset-0 flex">
                            @foreach($hours as $i => $hour)
                                <div class="shrink-0 border-r border-dashed border-slate-200/70 last:border-r-0" style="width: {{ $hourWidth }}px;"></div>
                            @endforeach
                        </div>

                        {{-- Horizontal row lines --}}
                        <div class="pointer-events-none absolute inset-0">
                            @for($r = 1; $r < $maxRow; $r++)
                                <div class="absolute left-0 right-0 border-t border-slate-100" style="top: {{ $r * $rowHeight }}px;"></div>
                            @endfor
                        </div>

                        {{-- EVENTS --}}
                        @php
                            $colorMap = [
                                'blue' => 'bg-blue-50 text-blue-800 ring-blue-200 before:bg-blue-500',
                                'indigo' => 'bg-indigo-50 text-indigo-800 ring-indigo-200 before:bg-indigo-500',
                                'orange' => 'bg-orange-50 text-orange-800 ring-orange-200 before:bg-orange-500',
                                'emerald' => 'bg-emerald-50 text-emerald-800 ring-emerald-200 before:bg-emerald-500',
                            ];
                        @endphp

                        @foreach($events as $event)
                            @php
                                $left = $event['offset'] * $hourWidth;
                                $width = max(48, $event['span'] * $hourWidth);
                                $top = ($event['row'] - 1) * $rowHeight + 8;
                                $classes = $colorMap[$event['color']] ?? $colorMap['blue'];
                            @endphp

                            <div
                                class="absolute flex items-center gap-2 overflow-hidden rounded-xl px-3 py-2 text-xs font-semibold shadow-sm ring-1 transition hover:shadow-md before:absolute before:inset-y-1 before:left-1 before:w-1 before:rounded-full {{ $classes }}"
                                style="left: {{ $left }}px; width: {{ $width }}px; top: {{ $top }}px; height: {{ $rowHeight - 16 }}px;"
                                title="{{ $event['title'] }} · {{ $event['start_label'] }}–{{ $event['end_label'] }}"
                            >
                                <div class="ml-2 min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold leading-tight">{{ $event['title'] }}</p>
                                    <p class="truncate text-[10px] font-medium opacity-70">
                                        {{ $event['start_label'] }} – {{ $event['end_label'] }}
                                    </p>
                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
