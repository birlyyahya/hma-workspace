<?php

use App\Livewire\Forms\ActivityForm;
use App\Models\User;
use App\Services\DarCache;
use App\Services\DarNotifier;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $id;
    public $user_id;

    public ActivityForm $form;

    public array $timelines = [];
    public array $activities = [];
    public array $weeks = [];
    public array $monthGroups = [];
    public array $users = [];

    /** @var array<int, bool> */
    public array $expanded = [];

    public ?int $addingTimelineId = null;

    public string $addingTimelineTitle = '';

    private const COLORS = [
        'from-blue-500 to-indigo-500',
        'from-emerald-500 to-teal-500',
        'from-orange-500 to-amber-500',
        'from-pink-500 to-rose-500',
        'from-violet-500 to-purple-500',
        'from-sky-500 to-cyan-500',
    ];

    private const STATUS_MAP = [
        1 => ['label' => 'Open', 'dot' => 'bg-blue-500', 'bar' => 'bg-blue-500'],
        2 => ['label' => 'Pending', 'dot' => 'bg-amber-500', 'bar' => 'bg-amber-500'],
        3 => ['label' => 'Cancelled', 'dot' => 'bg-red-500', 'bar' => 'bg-red-400'],
        4 => ['label' => 'Closed', 'dot' => 'bg-emerald-500', 'bar' => 'bg-emerald-500'],
    ];

    public function placeholder()
    {
        return view('components.placeholder.ph_project_timeline_gantt');
    }

    public function mount(): void
    {
        $this->users = User::whereNotIn('role_id', [1, 2])->orderBy('name')->get(['id', 'name'])->toArray();

        $this->loadTimelines();
    }

    #[On('timelineLoad')]
    #[On('timelinesUpdated')]
    public function loadTimelines(): void
    {
        $this->timelines = app(ProjectCache::class)->timelines((int) $this->id);
        $this->activities = app(DarCache::class)->tasks(['project_id' => (int) $this->id])['data'] ?? [];

        $this->buildWeeks();
    }

    public function toggleExpand(int $timelineId): void
    {
        $this->expanded[$timelineId] = ! ($this->expanded[$timelineId] ?? true);
    }

    public function openAddDar(int $timelineId): void
    {
        $timeline = collect($this->timelines)->firstWhere('id', $timelineId);

        $this->addingTimelineId = $timelineId;
        $this->addingTimelineTitle = $timeline['title'] ?? '';

        $this->form->resetForm();
        $this->form->isproject = true;
        $this->form->timelines_id = $timelineId;

        Flux::modal('add-dar-activity')->show();
    }

    public function toggleTeamUser(int $userId): void
    {
        $current = collect($this->form->team_user ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if (in_array($userId, $current, true)) {
            $this->form->team_user = array_values(array_filter($current, fn ($uid) => $uid !== $userId));
        } else {
            $current[] = $userId;
            $this->form->team_user = $current;
        }
    }

    public function saveDar(): void
    {
        $result = $this->form->store((int) $this->id);

        if (! $result['ok']) {
            $message = $result['error'] ?? ($result['body']['message'] ?? 'Gagal membuat aktivitas DAR.');
            Toaster::error($message);
            Log::error('DAR create from gantt failed', [
                'status' => $result['status'],
                'body' => $result['body'],
                'error' => $result['error'],
            ]);

            return;
        }

        Toaster::success('Aktivitas DAR berhasil dibuat.');

        app(DarNotifier::class)->activityCreated([
            'id' => $result['body']['data']['id'] ?? 0,
            'activity' => $this->form->activity,
            'user_id' => Auth::id(),
            'team_user' => ! empty($this->form->team_user) ? $this->form->team_user : ($this->form->team ?? []),
        ], (int) Auth::id());

        $this->form->resetForm();
        $this->addingTimelineId = null;
        $this->addingTimelineTitle = '';

        Flux::modal('add-dar-activity')->close();

        $this->dispatch('timelineLoad');
    }

    private function buildWeeks(): void
    {
        if (empty($this->timelines)) {
            $this->weeks = [];
            $this->monthGroups = [];

            return;
        }

        $start = collect($this->timelines)->min('start_date');
        $end = collect($this->timelines)->max('end_date');

        $period = CarbonPeriod::create(
            Carbon::parse($start)->startOfMonth(),
            '1 month',
            Carbon::parse($end)->endOfMonth()
        );

        $weeks = [];
        $monthGroups = [];

        foreach ($period as $monthDate) {
            $monthStart = $monthDate->copy()->startOfMonth();
            $monthEnd = $monthDate->copy()->endOfMonth();

            $weekNo = 1;
            $cursor = $monthStart->copy();
            $spanForMonth = 0;

            while ($cursor->lte($monthEnd)) {
                $weekEnd = $cursor->copy()->addDays(6);

                if ($weekEnd->gt($monthEnd)) {
                    $weekEnd = $monthEnd->copy();
                }

                $weeks[] = [
                    'month_value' => $monthDate->format('Y-m'),
                    'label' => 'M'.$weekNo,
                    'start' => $cursor->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                ];

                $spanForMonth++;
                $weekNo++;
                $cursor = $weekEnd->copy()->addDay();
            }

            $monthGroups[] = [
                'value' => $monthDate->format('Y-m'),
                'label' => $monthDate->locale('id')->translatedFormat('M Y'),
                'span' => $spanForMonth,
            ];
        }

        $this->weeks = $weeks;
        $this->monthGroups = $monthGroups;
    }

    private function weekIndexForDate(string $date): int
    {
        if (empty($this->weeks)) {
            return 0;
        }

        $target = Carbon::parse($date);

        foreach ($this->weeks as $idx => $week) {
            if ($target->between(Carbon::parse($week['start'])->startOfDay(), Carbon::parse($week['end'])->endOfDay())) {
                return $idx;
            }
        }

        if ($target->lt(Carbon::parse($this->weeks[0]['start'])->startOfDay())) {
            return 0;
        }

        return count($this->weeks) - 1;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRowsProperty(): array
    {
        if (empty($this->weeks) || empty($this->timelines)) {
            return [];
        }

        $palette = self::COLORS;
        $statusMap = self::STATUS_MAP;

        return collect($this->timelines)
            ->sortBy('start_date')
            ->values()
            ->map(function ($tl, $index) use ($palette, $statusMap) {
                $startIdx = $this->weekIndexForDate($tl['start_date']);
                $endIdx = max($startIdx, $this->weekIndexForDate($tl['end_date']));

                $activities = collect($this->activities)
                    ->where('project_category_id', $tl['id'])
                    ->sortBy('start_date')
                    ->values()
                    ->map(function ($activity) use ($statusMap) {
                        $actStartIdx = $this->weekIndexForDate($activity['start_date']);
                        $actEndIdx = max($actStartIdx, $this->weekIndexForDate($activity['end_date']));
                        $status = $statusMap[(int) ($activity['status'] ?? 1)] ?? $statusMap[1];

                        return [
                            'id' => $activity['id'],
                            'title' => $activity['activity'] ?? 'Untitled',
                            'start_label' => Carbon::parse($activity['start_date'])->locale('id')->translatedFormat('d M'),
                            'end_label' => Carbon::parse($activity['end_date'])->locale('id')->translatedFormat('d M Y'),
                            'col_start' => $actStartIdx + 2,
                            'col_span' => $actEndIdx - $actStartIdx + 1,
                            'status_label' => $status['label'],
                            'status_dot' => $status['dot'],
                            'status_bar' => $status['bar'],
                        ];
                    })
                    ->all();

                return [
                    'id' => $tl['id'] ?? $index,
                    'title' => $tl['title'] ?? 'Untitled',
                    'start_label' => Carbon::parse($tl['start_date'])->locale('id')->translatedFormat('d M Y'),
                    'end_label' => Carbon::parse($tl['end_date'])->locale('id')->translatedFormat('d M Y'),
                    'col_start' => $startIdx + 2, // +1 for label column, +1 because grid is 1-indexed
                    'col_span' => $endIdx - $startIdx + 1,
                    'color' => $palette[$index % count($palette)],
                    'activities' => $activities,
                    'activity_count' => count($activities),
                    'expanded' => $this->expanded[$tl['id'] ?? $index] ?? true,
                ];
            })
            ->all();
    }

    public function getTodayMarkerProperty(): ?array
    {
        if (empty($this->weeks)) {
            return null;
        }

        $today = Carbon::now();
        $rangeStart = Carbon::parse($this->weeks[0]['start'])->startOfDay();
        $rangeEnd = Carbon::parse(end($this->weeks)['end'])->endOfDay();

        if ($today->lt($rangeStart) || $today->gt($rangeEnd)) {
            return null;
        }

        $idx = $this->weekIndexForDate($today->format('Y-m-d'));
        $week = $this->weeks[$idx];
        $weekStart = Carbon::parse($week['start'])->startOfDay();
        $weekEnd = Carbon::parse($week['end'])->endOfDay();
        $daysInWeek = max(1, $weekStart->diffInDays($weekEnd));
        $progress = min(1, max(0, $weekStart->diffInDays($today) / $daysInWeek));

        return [
            'week_index' => $idx,
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
                    <p class="text-xs text-gray-500">Fase &amp; aktivitas DAR per minggu</p>
                </div>
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                <span class="rounded-full bg-gray-100 px-2.5 py-1 ring-1 ring-gray-200">
                    {{ count($timelines) }} timeline
                </span>
                @if(count($monthGroups))
                <span class="rounded-full bg-gray-100 px-2.5 py-1 ring-1 ring-gray-200">
                    {{ count($monthGroups) }} bulan
                </span>
                @endif
                @if(count($timelines))
                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('projects.gantt-print', $id)" target="_blank">
                    Export PDF
                </flux:button>
                @endif
            </div>
        </div>

        @if(empty($timelines) || empty($weeks))
        <div class="flex flex-col items-center justify-center py-12 text-center">
            <flux:icon name="chart-bar" class="w-10 h-10 text-gray-300 mb-3" />
            <p class="text-sm font-medium text-gray-600">Belum ada timeline</p>
            <p class="text-xs text-gray-400 mt-1">Tambah timeline untuk melihat gantt chart.</p>
        </div>
        @else
        @php
            $rows = $this->rows;
            $todayMarker = $this->todayMarker;
            $weekCount = count($weeks);
            $labelColWidth = 220;
            $weekMinWidth = 56;
            $totalMinWidth = $labelColWidth + ($weekCount * $weekMinWidth);
            $gridTemplate = $labelColWidth.'px repeat('.$weekCount.', minmax('.$weekMinWidth.'px, 1fr))';
        @endphp

        <div class="overflow-x-auto">
            <div class="min-w-max" style="min-width: {{ $totalMinWidth }}px;">

                {{-- HEADER: months (row 1) + weeks (row 2) --}}
                <div class="border-b bg-gray-50 sticky top-0 z-10">
                    <div class="grid" style="grid-template-columns: {{ $gridTemplate }};">
                        <div class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 border-r bg-gray-50 sticky left-0 z-10">
                            Timeline
                        </div>
                        @foreach($monthGroups as $group)
                        <div class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wide border-r last:border-r-0 border-b border-gray-200 text-gray-600" style="grid-column: span {{ $group['span'] }};">
                            {{ $group['label'] }}
                        </div>
                        @endforeach
                    </div>
                    <div class="grid" style="grid-template-columns: {{ $gridTemplate }};">
                        <div class="px-4 py-1.5 text-[10px] text-gray-400 border-r bg-gray-50 sticky left-0 z-10">
                            Weeks
                        </div>
                        @foreach($weeks as $i => $week)
                        @php
                            $isCurrentWeek = $todayMarker && $todayMarker['week_index'] === $i;
                        @endphp
                        <div class="px-1 py-1.5 text-center text-[10px] font-medium border-r last:border-r-0 {{ $isCurrentWeek ? 'text-blue-700 bg-blue-50/60' : 'text-gray-400' }}"
                             title="{{ Carbon::parse($week['start'])->locale('id')->translatedFormat('d M') }} – {{ Carbon::parse($week['end'])->locale('id')->translatedFormat('d M') }}">
                            {{ $week['label'] }}
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- BODY: timeline rows --}}
                <div class="relative">

                    {{-- Today marker (vertical line spanning all rows) --}}
                    @if($todayMarker)
                    <div class="pointer-events-none absolute inset-y-0 z-0 grid" style="grid-template-columns: {{ $gridTemplate }}; left: 0; right: 0;">
                        <div></div>
                        @foreach($weeks as $i => $week)
                        <div class="relative">
                            @if($i === $todayMarker['week_index'])
                            <div class="absolute inset-y-0 w-px bg-blue-400/70" style="left: {{ $todayMarker['progress'] * 100 }}%;">
                                <div class="absolute -top-1 -translate-x-1/2 size-2 rounded-full bg-blue-500"></div>
                            </div>
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif

                    @foreach($rows as $row)
                    {{-- Fase row --}}
                    <div wire:key="gantt-row-{{ $row['id'] }}" class="grid items-center border-b last:border-b-0 hover:bg-gray-50/60 transition" style="grid-template-columns: {{ $gridTemplate }}; min-height: 56px;">
                        {{-- Label cell --}}
                        <div class="px-4 py-3 border-r bg-white sticky left-0 z-1 flex items-start gap-1">
                            <button type="button" wire:click="toggleExpand({{ $row['id'] }})" class="flex items-start gap-1.5 text-left flex-1 min-w-0 group/toggle">
                                @if($row['activity_count'] > 0)
                                    <flux:icon name="{{ $row['expanded'] ? 'chevron-down' : 'chevron-right' }}" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-gray-400 group-hover/toggle:text-gray-600" />
                                @else
                                    <span class="w-3.5 shrink-0"></span>
                                @endif
                                <span class="min-w-0">
                                    <span class="flex items-center gap-1.5">
                                        <span class="text-sm font-semibold text-gray-900 truncate">{{ $row['title'] }}</span>
                                        @if($row['activity_count'] > 0)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 shrink-0">{{ $row['activity_count'] }}</span>
                                        @endif
                                    </span>
                                    <span class="block text-[11px] text-gray-500 mt-0.5">
                                        {{ $row['start_label'] }} – {{ $row['end_label'] }}
                                    </span>
                                </span>
                            </button>
                            <flux:tooltip content="Tambah DAR ke timeline ini">
                                <button type="button" wire:click="openAddDar({{ $row['id'] }})" class="shrink-0 p-1 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-700 transition">
                                    <flux:icon name="plus" class="w-3.5 h-3.5" />
                                </button>
                            </flux:tooltip>
                        </div>

                        {{-- Bar cell (spans across weeks) --}}
                        <div class="px-1 py-2 relative" style="grid-column: {{ $row['col_start'] }} / span {{ $row['col_span'] }};">
                            <div class="h-6 rounded-lg bg-linear-to-r {{ $row['color'] }} shadow-sm ring-1 ring-black/5 flex items-center px-3"
                                 title="{{ $row['title'] }} · {{ $row['start_label'] }} – {{ $row['end_label'] }}">
                                <span class="text-[11px] font-semibold text-white truncate drop-shadow-sm">
                                    {{ $row['title'] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- DAR activity sub-rows --}}
                    @if($row['expanded'])
                    @foreach($row['activities'] as $activity)
                    <a wire:key="gantt-activity-{{ $activity['id'] }}" href="{{ route('dar.dar-show', $activity['id']) }}" wire:navigate
                       class="grid items-center border-b last:border-b-0 bg-gray-50/40 hover:bg-gray-100/70 transition" style="grid-template-columns: {{ $gridTemplate }}; min-height: 40px;">
                        <div class="px-4 py-2 border-r bg-gray-50/40 sticky left-0 z-1 pl-10">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <span class="size-1.5 rounded-full shrink-0 {{ $activity['status_dot'] }}"></span>
                                <span class="text-xs text-gray-600 truncate">{{ $activity['title'] }}</span>
                            </span>
                        </div>
                        <div class="px-1 py-1.5 relative" style="grid-column: {{ $activity['col_start'] }} / span {{ $activity['col_span'] }};">
                            <div class="h-5 rounded-md {{ $activity['status_bar'] }} shadow-sm ring-1 ring-black/5 flex items-center px-2 gap-1.5 min-w-0"
                                 title="{{ $activity['title'] }} · {{ $activity['start_label'] }} – {{ $activity['end_label'] }} · {{ $activity['status_label'] }}">
                                <span class="text-[10px] font-medium text-white truncate drop-shadow-sm">
                                    {{ $activity['title'] }}
                                </span>
                            </div>
                        </div>
                    </a>
                    @endforeach
                    @endif
                    @endforeach

                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ============== MODAL: TAMBAH DAR KE FASE ============== --}}
    <flux:modal name="add-dar-activity" class="w-xs sm:w-sm md:min-w-2xl lg:min-w-3xl max-sm:max-h-[85dvh] md:overflow-visible overflow-auto">
        <form wire:submit="saveDar" class="space-y-4 sm:space-y-5">
            {{-- Header --}}
            <div class="flex items-start gap-3 border-b border-zinc-100 pb-4">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-700">
                    <flux:icon name="clipboard-document-list" class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <flux:heading size="lg" class="mb-0!">Tambah Aktivitas DAR</flux:heading>
                    <p class="mt-0.5 text-sm text-zinc-500 truncate">
                        Fase: <span class="font-medium text-zinc-700">{{ $addingTimelineTitle ?: '-' }}</span>
                    </p>
                </div>
            </div>

            <div class="space-y-4 sm:space-y-5">
                <div>
                    <flux:input wire:model="form.activity" placeholder="Nama aktivitas" />
                    @error('form.activity')
                    <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Deskripsi</label>
                    <x-ckeditor model="form.description" placeholder="Jelaskan lebih detail apa yang akan dikerjakan..." />
                    @error('form.description')
                    <flux:error message="{{ $message }}" /> @enderror
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <flux:input wire:model="form.start_date" label="Mulai" type="datetime-local" />
                    <flux:input wire:model="form.end_date" label="Berakhir" type="datetime-local" />
                </div>
                @error('form.start_date')
                <flux:error message="{{ $message }}" /> @enderror
                @error('form.end_date')
                <flux:error message="{{ $message }}" /> @enderror

                <div class="grid grid-cols-1 gap-4 items-start sm:grid-cols-2">
                    <div x-data="{ open: false, query: '', matches(name) { return !this.query || name.toLowerCase().includes(this.query.toLowerCase()); } }" @click.away="open = false" @keydown.escape.window="open = false" class="relative">
                        @php
                        $selectedTeam = collect($this->form->team_user ?? [])->map(fn ($uid) => (int) $uid)->all();
                        $userById = collect($this->users)->keyBy('id');
                        @endphp

                        <div class="mb-1.5 flex items-center justify-between">
                            <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Tim</p>
                            <span class="text-[11px] text-zinc-400">{{ count($selectedTeam) }} dipilih</span>
                        </div>

                        <div @click="open = true; $nextTick(() => $refs.ganttTeamSearch.focus())" class="flex min-h-11.5 cursor-text flex-wrap items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-2 py-1.5 transition focus-within:border-zinc-400 focus-within:ring-2 focus-within:ring-zinc-200">
                            @foreach ($selectedTeam as $uid)
                            @php $u = $userById[$uid] ?? null; @endphp
                            @if ($u)
                            <span wire:key="gantt-team-chip-{{ $uid }}" class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200">
                                <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                {{ $u['name'] }}
                                <button type="button" @click.stop="$wire.toggleTeamUser({{ $uid }})" class="grid h-4 w-4 place-items-center rounded-full text-zinc-400 hover:bg-white hover:text-red-600" aria-label="Hapus">
                                    <flux:icon name="x-mark" class="h-3 w-3" />
                                </button>
                            </span>
                            @endif
                            @endforeach

                            <input x-ref="ganttTeamSearch" x-model="query" @focus="open = true" @keydown.enter.prevent type="text" placeholder="@if (empty($selectedTeam)) Pilih anggota tim... @else Tambah anggota lain... @endif" class="min-w-30 flex-1 border-0 bg-transparent px-2 py-1 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                        </div>

                        <div x-show="open" x-cloak x-transition.origin.top class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white p-1 shadow-lg ring-1 ring-zinc-200/70">
                            @forelse ($this->users as $u)
                            @php $isSelected = in_array((int) $u['id'], $selectedTeam, true); @endphp
                            <button wire:key="gantt-team-opt-{{ $u['id'] }}" type="button" x-show="matches('{{ addslashes($u['name']) }}')" @click="$wire.toggleTeamUser({{ $u['id'] }}); query = ''; $refs.ganttTeamSearch.focus()" class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ $isSelected ? 'bg-zinc-50' : '' }}">
                                <span class="inline-flex min-w-0 items-center gap-2">
                                    <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                    <span class="truncate text-zinc-800">{{ $u['name'] }}</span>
                                </span>
                                @if ($isSelected)
                                <flux:icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" />
                                @endif
                            </button>
                            @empty
                            <div class="px-3 py-2 text-xs text-zinc-500">Tidak ada user.</div>
                            @endforelse
                        </div>

                        @error('form.team_user')
                        <flux:error message="{{ $message }}" /> @enderror
                    </div>

                    <div>
                        <p class="text-[11px] mb-1.5 font-semibold uppercase tracking-widest text-zinc-500">Status</p>
                        <flux:select wire:model="form.status" placeholder="Pilih status...">
                            <flux:select.option value="1">Open</flux:select.option>
                            <flux:select.option value="2">Pending</flux:select.option>
                            <flux:select.option value="3">Cancelled</flux:select.option>
                            <flux:select.option value="4">Completed</flux:select.option>
                        </flux:select>
                        @error('form.status')
                        <flux:error message="{{ $message }}" /> @enderror
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex flex-col-reverse gap-2 border-t border-zinc-100 pt-4 sm:flex-row sm:items-center sm:justify-end">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button" class="w-full sm:w-auto">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="saveDar">
                    <span wire:loading.remove wire:target="saveDar">Simpan Aktivitas</span>
                    <span wire:loading wire:target="saveDar">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
