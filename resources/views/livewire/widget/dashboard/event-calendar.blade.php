<?php

use Carbon\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public Carbon $currentMonth;

    public string $selectedDate;

    public function mount(): void
    {
        $this->currentMonth  = Carbon::now()->startOfMonth();
        $this->selectedDate  = Carbon::today()->toDateString();
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

    /**
     * @return array<string, array<int, array{title:string,time:string,location:string,color:string}>>
     */
    #[Computed]
    public function events(): array
    {
        $today    = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $week     = $today->copy()->addDays(4);
        $month    = $today->copy()->addDays(12);

        return [
            $today->toDateString() => [
                ['title' => 'Daily Stand-up', 'time' => '09:00 – 09:30', 'location' => 'Zoom', 'color' => 'blue'],
                ['title' => 'Review Sprint Q2', 'time' => '14:00 – 15:30', 'location' => 'Ruang Meeting A', 'color' => 'violet'],
            ],
            $tomorrow->toDateString() => [
                ['title' => 'Workshop UI/UX', 'time' => '10:00 – 12:00', 'location' => 'Aula Utama', 'color' => 'emerald'],
            ],
            $week->toDateString() => [
                ['title' => 'Town Hall Meeting', 'time' => '13:00 – 14:00', 'location' => 'Aula Utama', 'color' => 'amber'],
                ['title' => 'Onboarding Karyawan Baru', 'time' => '15:00 – 16:00', 'location' => 'Ruang HR', 'color' => 'rose'],
            ],
            $month->toDateString() => [
                ['title' => 'Family Gathering', 'time' => '08:00 – 17:00', 'location' => 'Pantai Anyer', 'color' => 'teal'],
            ],
        ];
    }

    /**
     * @return array<int, array{title:string,time:string,location:string,color:string}>
     */
    #[Computed]
    public function selectedEvents(): array
    {
        return $this->events[$this->selectedDate] ?? [];
    }

    /** @return array<string,bool> */
    #[Computed]
    public function eventDates(): array
    {
        return array_fill_keys(array_keys($this->events), true);
    }
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 shadow-xs ">
    <div class="grid lg:grid-cols-5 divide-y lg:divide-y-0 lg:divide-x divide-zinc-100">

        {{-- Calendar --}}
        <div class="p-5 lg:col-span-3">
            @php
                $startOfMonth    = $currentMonth->copy()->startOfMonth();
                $startDay        = $startOfMonth->dayOfWeek;
                $daysInMonth     = $currentMonth->daysInMonth;
                $prevMonth       = $currentMonth->copy()->subMonth();
                $daysInPrevMonth = $prevMonth->daysInMonth;
                $totalCells      = $startDay + $daysInMonth;
                $nextDays        = (7 - ($totalCells % 7)) % 7;
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
                    <button type="button" wire:click="goToday"
                        class="px-3 py-1.5 text-xs font-medium text-zinc-600 rounded-lg hover:bg-zinc-100 transition">
                        Hari ini
                    </button>
                    <button type="button" wire:click="prevMonth"
                        class="size-8 flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 transition">
                        <flux:icon name="chevron-left" class="size-4" />
                    </button>
                    <button type="button" wire:click="nextMonth"
                        class="size-8 flex items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 transition">
                        <flux:icon name="chevron-right" class="size-4" />
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-7 text-[11px] font-medium text-zinc-400 mb-2 text-center">
                @foreach (['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $d)
                    <div>{{ $d }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-y-1 text-sm">
                @for ($i = $startDay - 1; $i >= 0; $i--)
                    <div class="size-9 mx-auto flex items-center justify-center text-zinc-300">
                        {{ $daysInPrevMonth - $i }}
                    </div>
                @endfor

                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $date       = $currentMonth->copy()->day($day);
                        $dateString = $date->toDateString();
                        $isToday    = $date->isToday();
                        $isSelected = $selectedDate === $dateString;
                        $hasEvent   = isset($this->eventDates[$dateString]);
                    @endphp

                    <button type="button" wire:click="selectDate('{{ $dateString }}')"
                        wire:key="d-{{ $dateString }}"
                        class="relative size-9 mx-auto flex items-center justify-center rounded-full transition
                            {{ $isSelected ? 'bg-violet-600 text-white font-semibold shadow-sm shadow-violet-200'
                                : ($isToday ? 'ring-1 ring-violet-400 text-violet-700 font-semibold'
                                : 'text-zinc-700 hover:bg-zinc-100') }}">
                        {{ $day }}
                        @if ($hasEvent && ! $isSelected)
                            <span class="absolute bottom-1 size-1 rounded-full bg-violet-500"></span>
                        @endif
                    </button>
                @endfor

                @for ($i = 1; $i <= $nextDays; $i++)
                    <div class="size-9 mx-auto flex items-center justify-center text-zinc-300">{{ $i }}</div>
                @endfor
            </div>
        </div>

        {{-- Event List --}}
        <div class="p-5 lg:col-span-2 bg-zinc-50/40">
            @php
                $selected = Carbon::parse($selectedDate);
            @endphp

            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-[11px] font-medium uppercase tracking-wide text-zinc-400">Event pada</p>
                    <p class="text-sm font-semibold text-zinc-900">{{ $selected->translatedFormat('l, d F Y') }}</p>
                </div>
                <flux:badge size="sm" color="violet" variant="pill">
                    {{ count($this->selectedEvents) }} event
                </flux:badge>
            </div>

            <div class="space-y-2.5">
                @forelse ($this->selectedEvents as $event)
                    @php
                        $accent = $event['color'];
                    @endphp
                    <div wire:key="ev-{{ $loop->index }}-{{ $selectedDate }}"
                        class="group relative rounded-xl border border-zinc-200 bg-white p-3 hover:border-zinc-300 hover:shadow-xs transition">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 w-1 self-stretch rounded-full bg-{{ $accent }}-500"></div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-zinc-900 truncate">{{ $event['title'] }}</p>
                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-zinc-500">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="clock" class="size-3.5" />
                                        {{ $event['time'] }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon name="map-pin" class="size-3.5" />
                                        {{ $event['location'] }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-zinc-200 p-6 text-center">
                        <div class="mx-auto size-10 rounded-full bg-zinc-100 flex items-center justify-center">
                            <flux:icon name="calendar" class="size-5 text-zinc-400" />
                        </div>
                        <p class="mt-2 text-sm text-zinc-500">Tidak ada event di tanggal ini</p>
                    </div>
                @endforelse
            </div>

            <a href="{{ route('events') }}" wire:navigate
                class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-violet-600 hover:text-violet-700">
                Lihat semua event
                <flux:icon name="arrow-right" class="size-3.5" />
            </a>
        </div>
    </div>
</div>
