<?php

use App\Models\User;
use App\Services\DarCache;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    public Carbon $currentMonth;

    public string $selectedDate;

    /** @var array<string, array<int, array{id:int,title:string,time:string,location:string,color:string,source:string,status:int,end_date:string,is_multi_day:bool,user_name:?string,url:?string}>> */
    public array $eventMap = [];

    public function placeholder(): \Illuminate\Contracts\View\View
    {
        return view('components.placeholder.ph_event_calendar');
    }

    public function mount(): void
    {
        $this->currentMonth = Carbon::now()->startOfMonth();
        $this->selectedDate = Carbon::today()->toDateString();
        $this->loadEvents();
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function prevMonth(): void
    {
        $this->currentMonth = $this->currentMonth->copy()->subMonth();
    }

    public function nextMonth(): void
    {
        $this->currentMonth = $this->currentMonth->copy()->addMonth();
    }

    public function goToday(): void
    {
        $this->currentMonth = Carbon::now()->startOfMonth();
        $this->selectedDate = Carbon::today()->toDateString();
    }

    #[On('updatedTimeline')]
    #[On('updatedCardTaskDar')]
    public function refreshEvents(): void
    {
        $this->loadEvents();
    }

    protected function loadEvents(): void
    {
        try {
            $scope = Auth::user()?->viewScopeFor('dar') === 'all' ? 'all' : 'user';
            $response = app(DarCache::class)->list('all', Auth::id());
            $rows = $response['data'] ?? [];

            $projectMap = collect(app(ProjectCache::class)->allProjects())
                ->keyBy('id')
                ->map(fn ($p) => $p['name'] ?? null)
                ->filter()
                ->toArray();

            $teamUserIds = collect($rows)
                ->pluck('team_user')
                ->filter()
                ->flatMap(fn ($team) => collect($team)->pluck('user_id'));

            $userIds = collect($rows)
                ->pluck('user_id')
                ->merge($teamUserIds)
                ->filter()
                ->unique()
                ->values();
            $userMap = $userIds->isNotEmpty()
                ? User::whereIn('id', $userIds)->pluck('name', 'id')->toArray()
                : [];
        } catch (\Throwable $e) {
            $rows = [];
            $projectMap = [];
            $userMap = [];
        }

        $events = [];

        foreach ($rows as $row) {
            $startRaw = $row['start_date'] ?? $row['date'] ?? null;

            if (! $startRaw) {
                continue;
            }

            try {
                $start = Carbon::parse($startRaw);
            } catch (\Throwable) {
                continue;
            }

            $end = ! empty($row['end_date']) ? Carbon::parse($row['end_date']) : $start->copy();
            $status = (int) ($row['status'] ?? 0);
            $id = (int) ($row['id'] ?? 0);

            $color = match ($status) {
                1 => 'blue',     // Open
                2 => 'amber',    // Pending / Hold
                3 => 'rose',     // Cancelled
                4 => 'emerald',  // Completed
                default => 'zinc',
            };

            $projectId = $row['project_id'] ?? null;
            $location = $projectId
                ? ($projectMap[$projectId] ?? 'Project')
                : 'Non-project';

            $isMultiDay = ! $start->isSameDay($end);
            $time = $isMultiDay
                ? $start->format('H:i').' (mulai)'
                : $start->format('H:i').' – '.$end->format('H:i');

            $userId = $row['user_id'] ?? null;
            $userName = $userId ? ($userMap[$userId] ?? null) : null;

            $teamUsers = collect($row['team_user'] ?? [])
                ->map(fn ($member) => [
                    'user_id' => $member['user_id'] ?? null,
                    'name' => $userMap[$member['user_id'] ?? null] ?? null,
                ])
                ->values()
                ->all();

            $events[$start->toDateString()][] = [
                'id' => $id,
                'title' => $row['activity'] ?? 'Untitled',
                'time' => $time,
                'location' => $location,
                'color' => $color,
                'source' => 'DAR',
                'status' => $status,
                'end_date' => $end->toDateString(),
                'is_multi_day' => $isMultiDay,
                'user_name' => $userName,
                'team_user' => $teamUsers,
                'url' => $id ? route('dar.dar-show', ['id' => $id]) : null,
            ];
        }

        // Sort events within each day by start time.
        foreach ($events as $date => $list) {
            usort($events[$date], fn ($a, $b) => strcmp($a['time'], $b['time']));
        }

        $this->eventMap = $events;
    }

    /**
     * @return array<int, array{id:int,title:string,time:string,location:string,color:string,source:string,status:int,end_date:string,is_multi_day:bool,user_name:?string,url:?string}>
     */
    #[Computed]
    public function selectedEvents(): array
    {
        return $this->eventMap[$this->selectedDate] ?? [];
    }

    /** @return array<string,bool> */
    #[Computed]
    public function eventDates(): array
    {
        return array_fill_keys(array_keys($this->eventMap), true);
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 shadow-xs ">
    <div class="grid lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-zinc-100">

        {{-- Calendar --}}
        <div class="!min-w-0 p-5 lg:col-span-3">
            @php
            $startOfMonth = $currentMonth->copy()->startOfMonth();
            $startDay = $startOfMonth->dayOfWeek;
            $daysInMonth = $currentMonth->daysInMonth;
            $prevMonth = $currentMonth->copy()->subMonth();
            $daysInPrevMonth = $prevMonth->daysInMonth;
            $totalCells = $startDay + $daysInMonth;
            $nextDays = (7 - ($totalCells % 7)) % 7;
            @endphp

            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-violet-50 ring-1 ring-violet-100 flex items-center justify-center">
                        <flux:icon name="calendar-days" class="size-5 text-violet-600" />
                    </div>
                    <div>
                        <h2 class="text-base font-semibold text-zinc-900 leading-tight">
                            {{ $currentMonth->translatedFormat('F') }}
                            <span class="text-zinc-400 font-medium">{{ $currentMonth->year }}</span>
                        </h2>
                        <p class="text-xs text-zinc-500">Pilih tanggal untuk melihat event</p>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" wire:click="goToday" class="px-3 py-1.5 text-xs font-medium text-zinc-600 rounded-lg hover:bg-zinc-100 transition">
                        Hari ini
                    </button>
                    <button type="button" wire:click="prevMonth" class="size-8 flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 transition">
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                    <button type="button" wire:click="nextMonth" class="size-8 flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 transition">
                        <flux:icon name="chevron-right" class="size-4" />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-7 text-[11px] font-medium text-zinc-400 mb-2 text-center">
                @foreach (['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $d)
                <div>{{ $d }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-y-1 auto-rows-fr min-h-82 text-sm">
                @for ($i = $startDay - 1; $i >= 0; $i--)
                <div class="size-11 m-auto flex items-center justify-center text-zinc-300">
                    {{ $daysInPrevMonth - $i }}
                </div>
                @endfor

                @for ($day = 1; $day <= $daysInMonth; $day++) @php $date=$currentMonth->copy()->day($day);
                    $dateString = $date->toDateString();
                    $isToday = $date->isToday();
                    $isSelected = $selectedDate === $dateString;
                    $hasEvent = isset($this->eventDates[$dateString]);
                    @endphp

                    <button type="button" wire:click="selectDate('{{ $dateString }}')" wire:key="d-{{ $dateString }}" class="relative size-11 m-auto flex items-center justify-center rounded-full transition
                            {{ $isSelected ? 'bg-violet-600 text-white font-semibold shadow-sm shadow-violet-200'
                                : ($isToday ? 'ring-1 ring-violet-400 text-violet-700 font-semibold'
                                : 'text-zinc-700 hover:bg-zinc-100') }}">
                        {{ $day }}
                        @if ($hasEvent && ! $isSelected)
                        <span class="absolute bottom-1 size-1 w-5 rounded-sm bg-violet-500"></span>
                        @endif
                    </button>
                    @endfor

                    @for ($i = 1; $i <= $nextDays; $i++) <div class="size-11 m-auto flex items-center justify-center text-zinc-300">{{ $i }}</div>
            @endfor
        </div>
    </div>

    {{-- Event List --}}
    <div class="min-w-0 p-5 lg:col-span-2 bg-zinc-50/40">
        @php
        $selected = Carbon::parse($selectedDate);
        @endphp

        <div class="flex items-center justify-between gap-3 mb-4">
            <div class="min-w-0">
                <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-400">Event pada</p>
                <p class="text-sm font-semibold text-zinc-900 truncate">{{ $selected->translatedFormat('l, d F Y') }}</p>
            </div>
            <flux:badge size="sm" color="violet" variant="pill" class="shrink-0">
                {{ count($this->selectedEvents) }} event
            </flux:badge>
        </div>

        <div class="space-y-2.5 max-h-96 overflow-y-auto pr-1">
            @forelse ($this->selectedEvents as $event)
            @php
            $accent = $event['color'];
            $sourceColors = [
            'DAR' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
            'Event' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200',
            ];
            $sourceClass = $sourceColors[$event['source']] ?? 'bg-zinc-50 text-zinc-700 ring-zinc-200';
            @endphp
            @php $cardClass = 'group relative block rounded-xl border border-zinc-200 bg-white p-3 hover:border-zinc-300 hover:shadow-xs transition'; @endphp

            @if($event['url'])
            <a wire:key="ev-{{ $loop->index }}-{{ $selectedDate }}" href="{{ $event['url'] }}" wire:navigate class="{{ $cardClass }}">
                @else
                <div wire:key="ev-{{ $loop->index }}-{{ $selectedDate }}" class="{{ $cardClass }}">
                    @endif
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 w-1 self-stretch rounded-full bg-{{ $accent }}-500"></div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 flex-1 text-sm font-semibold text-zinc-900 truncate group-hover:text-zinc-950">{{ $event['title'] }}</p>
                                <div class="flex gap-2 items-center">
                                    <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ring-1 {{ $sourceClass }}">
                                        {{ $event['source'] }}
                                    </span>
                                </div>
                            </div>
                            <div class="mt-1 flex flex-col gap-x-3 gap-y-1 text-xs text-zinc-500">
                                <span class="inline-flex items-center gap-1">
                                    <flux:icon name="clock" class="size-3.5" />
                                    {{ $event['time'] }}
                                </span>
                                @if(! empty($event['location']))
                                <div class="flex gap-3 justify-between">
                                    <span class="inline-flex items-start gap-1">
                                        <flux:icon name="map-pin" class="size-3.5" />
                                        {{ $event['location'] }}
                                    </span>
                                </div>
                                @endif
                                <div class="flex gap-1 justify-between mt-2">
                                    @if($event['is_multi_day'])
                                    <span class="inline-flex items-center gap-1 text-zinc-400">
                                        <flux:icon name="calendar" class="size-3.5" />
                                        sampai {{ Carbon::parse($event['end_date'])->translatedFormat('d M Y') }}
                                    </span>
                                    @else
                                    <span class="inline-flex items-center gap-1 text-zinc-400">
                                        <flux:icon name="users" class="size-3.5" />
                                        Teams :
                                    </span>
                                    @endif
                                @php
                                    $supportCount = count($event['team_user']);
                                @endphp
                                @if($supportCount > 0 || ! empty($event['user_name']))
                                <flux:avatar.group>
                                    @if(! empty($event['user_name']))
                                        <flux:tooltip content="Pembuat: {{ $event['user_name'] }}">
                                            <flux:avatar size="xs" circle name="{{ $event['user_name'] }}" color="auto" color:seed="{{ $event['user_name'] }}" class="ring-2 ring-violet-400" />
                                        </flux:tooltip>
                                    @endif
                                    @foreach (array_slice($event['team_user'], 0, 4) as $user)
                                        @php $label = $user['name'] ?? $user['user_id']; @endphp
                                        <flux:tooltip wire:key="team-{{ $event['id'] }}-{{ $user['user_id'] }}" content="{{ $label }}">
                                            <flux:avatar size="xs" circle name="{{ $label }}" color="auto" color:seed="{{ $user['user_id'] }}" />
                                        </flux:tooltip>
                                    @endforeach

                                    @if($supportCount > 4)
                                        <flux:tooltip content="{{ collect(array_slice($event['team_user'], 4))->map(fn ($u) => $u['name'] ?? $u['user_id'])->implode(', ') }}">
                                            <flux:avatar circle size="xs" class="ring-2 ring-white">
                                                +{{ $supportCount - 4 }}
                                            </flux:avatar>
                                        </flux:tooltip>
                                    @endif
                                </flux:avatar.group>
                                @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($event['url'])
            </a>
            @else
        </div>
        @endif
        @empty
        <div class="rounded-xl border border-dashed border-zinc-200 p-6 text-center">
            <div class="mx-auto size-10 rounded-full bg-zinc-100 flex items-center justify-center">
                <flux:icon name="calendar" class="size-5 text-zinc-400" />
            </div>
            <p class="mt-2 text-sm text-zinc-500">Tidak ada event di tanggal ini</p>
        </div>
        @endforelse
    </div>

    <a href="{{ route('events') }}" wire:navigate class="!hidden mt-4 inline-flex items-center gap-1 text-xs font-medium text-violet-600 hover:text-violet-700">
        Lihat semua event
        <flux:icon name="arrow-right" class="size-3.5" />
    </a>
</div>
</div>
</div>
