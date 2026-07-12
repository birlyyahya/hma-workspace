<?php

use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public array $events = [];
    public array $overdue = [];
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

    public function mount(): void
    {
        $this->loadTimeline();
    }

    #[On('updatedTimeline')]
    public function refresh(): void
    {
        $this->loadTimeline();
    }

    private function loadTimeline(): void
    {
        $this->loading = true;
        $this->date = now()->format('d/m/Y');

        try {
            $scope = Auth::user()->viewScopeFor('dar') === 'all' ? 'all' : 'user';
            $response = app(DarCache::class)->timelineToday($scope, Auth::id());
            $this->buildTimeline($response['data'] ?? []);
        } catch (\Throwable $e) {
            $this->events = [];
            $this->overdue = [];
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

        $parsed = collect($data)
            ->map(fn ($item) => [
                'item' => $item,
                'rawStart' => Carbon::parse($item['start_date']),
                'rawEnd' => Carbon::parse($item['end_date']),
            ]);

        $relevant = $parsed
            ->filter(fn ($e) => $e['rawStart'] <= $dayEnd && $e['rawEnd'] >= $dayStart)
            ->values();

        $overdue = $parsed
            ->filter(fn ($e) => $e['rawEnd'] < $dayStart)
            ->sortBy(fn ($e) => $e['rawEnd']->timestamp)
            ->values();

        $projectMap = $this->resolveProjectNames(
            $relevant->pluck('item.project_id')->concat($overdue->pluck('item.project_id'))
        );

        $this->overdue = $overdue->map(function ($e) use ($dayStart, $projectMap) {
            $projectId = $e['item']['project_id'] ?? null;

            return [
                'title' => $e['item']['activity'] ?? 'Untitled',
                'status' => $e['item']['status'] ?? null,
                'user' => $e['item']['user'] ?? null,
                'end_label' => $e['rawEnd']->format('d/m/Y'),
                'days_late' => (int) $e['rawEnd']->copy()->startOfDay()->diffInDays($dayStart),
                'project_name' => $projectId ? ($projectMap[$projectId] ?? null) : null,
            ];
        })->toArray();

        $processed = $relevant->map(function ($e) use ($dayStart, $dayEnd) {
            $rawStart = $e['rawStart'];
            $rawEnd = $e['rawEnd'];

            if ($rawEnd->lt($dayStart)) {
                $timing = 'past';
            } elseif ($rawStart->gt($dayEnd)) {
                $timing = 'future';
            } else {
                $timing = 'today';
            }

            if ($timing === 'today') {
                $displayStart = $rawStart->lt($dayStart) ? $dayStart->copy() : $rawStart->copy();
                $displayEnd = $rawEnd->gt($dayEnd) ? $dayEnd->copy() : $rawEnd->copy();
            } else {
                $displayStart = $rawStart->copy();
                $displayEnd = $rawEnd->copy();
            }

            if ($displayEnd->lessThanOrEqualTo($displayStart)) {
                $displayEnd = $displayStart->copy()->addMinutes(30);
            }

            return [
                'item' => $e['item'],
                'start' => $displayStart,
                'end' => $displayEnd,
                'timing' => $timing,
            ];
        });

        [$rangeStart, $rangeEnd] = $this->computeRange($processed);
        $this->rangeStartHour = $rangeStart;
        $this->rangeEndHour = $rangeEnd;
        $this->hours = $this->buildHours($rangeStart, $rangeEnd);

        $this->events = $processed->values()->map(function ($entry, $index) use ($rangeStart, $projectMap) {
            $start = $entry['start'];
            $end = $entry['end'];
            $timing = $entry['timing'];

            $startHour = $start->hour + ($start->minute / 60);
            $endHour = $end->hour + ($end->minute / 60);

            if (! $start->isSameDay($end)) {
                $endHour = 24;
            }

            $color = match ($timing) {
                'past' => 'red',
                'future' => 'sky',
                default => self::COLORS[$index % count(self::COLORS)],
            };

            $projectId = $entry['item']['project_id'] ?? null;

            return [
                'title' => $entry['item']['activity'] ?? 'Untitled',
                'description' => $entry['item']['description'] ?? null,
                'status' => $entry['item']['status'] ?? null,
                'start_label' => $start->format('H:i'),
                'end_label' => $end->format('H:i'),
                'offset' => max(0, $startHour - $rangeStart),
                'span' => max(0.25, $endHour - $startHour),
                'row' => $index + 1,
                'color' => $color,
                'timing' => $timing,
                'user' => $entry['item']['user'] ?? null,
                'project_name' => $projectId ? ($projectMap[$projectId] ?? null) : null,
            ];
        })->toArray();
    }

    /**
     * Resolusi nama project hanya untuk id yang muncul di event hari ini —
     * hindari membangun map atas seluruh katalog project tiap render.
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $projectIds
     * @return array<int, string>
     */
    private function resolveProjectNames($projectIds): array
    {
        $ids = $projectIds->filter()->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        try {
            return collect(app(ProjectCache::class)->allProjects())
                ->whereIn('id', $ids->all())
                ->pluck('name', 'id')
                ->filter()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{start: Carbon, end: Carbon}>  $processed
     * @return array{0: int, 1: int}
     */
    private function computeRange($processed): array
    {
        if ($processed->isEmpty()) {
            return [self::DEFAULT_START, self::DEFAULT_END];
        }

        $minHour = (int) $processed->map(fn ($e) => $e['start']->hour)->min();

        $maxHour = (int) $processed->map(function ($e) {
            if (! $e['start']->isSameDay($e['end'])) {
                return 24;
            }

            return $e['end']->minute > 0 ? $e['end']->hour + 1 : $e['end']->hour;
        })->max();

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
                    <h2 class="text-sm font-semibold tracking-tight text-slate-900">Timeline Tugas</h2>
                    <p class="text-xs text-slate-500">Agenda hari ini · {{ $date }}</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                @if(! empty($overdue))
                    <span class="flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-red-700 ring-1 ring-red-200">
                        <flux:icon name="exclamation-triangle" class="size-3.5" />
                        {{ count($overdue) }} terlambat
                    </span>
                @endif
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
        @elseif(empty($events) && empty($overdue))
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
                                'red' => 'bg-red-50 text-red-800 ring-red-200 before:bg-red-500',
                                'sky' => 'bg-sky-50 text-sky-800 ring-sky-200 before:bg-sky-500',
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
                                        @if(! empty($event['project_name']))
                                            · {{ $event['project_name'] }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach

                    </div>
                </div>
            </div>

            {{-- Task terlambat (Open, end_date sudah lewat) — daftar yang bisa dibuka --}}
            @if(! empty($overdue))
                <div x-data="{ open: false }" class="border-t border-red-100 bg-red-50/50">
                    <button type="button" @click="open = ! open" class="flex w-full items-center justify-between px-5 py-3 text-xs font-semibold text-red-700">
                        <span class="flex items-center gap-1.5">
                            <flux:icon name="exclamation-triangle" class="size-4" />
                            {{ count($overdue) }} task terlambat
                        </span>
                        <span class="flex items-center gap-1 text-red-500" x-text="open ? 'tutup' : 'buka'"></span>
                    </button>
                    <div x-show="open" x-collapse class="space-y-2 px-5 pb-3">
                        @foreach($overdue as $i => $o)
                            <div wire:key="ov-c-{{ $i }}" class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-red-200">
                                <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-red-500" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-semibold text-slate-900">{{ $o['title'] }}</p>
                                    <p class="truncate text-[10px] font-medium text-red-600">
                                        terlambat {{ $o['days_late'] }} hari · jatuh tempo {{ $o['end_label'] }}
                                        @if(! empty($o['project_name'])) · {{ $o['project_name'] }} @endif
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif

    </div>
</div>
