<?php

use Livewire\Volt\Component;
use Carbon\Carbon;

new class extends Component {

    public $currentMonth;
    public $selectedDate = null;
    public $events = [];

    public function mount()
    {
        $this->currentMonth = Carbon::now();
        $this->selectedDate = Carbon::today()->toDateString();
    }


    public function selectDate($date)
    {
        $this->selectedDate = $date;
    }

    public function prevMonth()
    {
        $this->currentMonth = $this->currentMonth->copy()->subMonth();
    }

    public function nextMonth()
    {
        $this->currentMonth = $this->currentMonth->copy()->addMonth();
    }

}; ?>

    @php
    $startOfMonth = $currentMonth->copy()->startOfMonth();
    $startDay = $startOfMonth->dayOfWeek; // 0 (Sun) - 6 (Sat)
    $daysInMonth = $currentMonth->daysInMonth;

    $prevMonth = $currentMonth->copy()->subMonth();
    $daysInPrevMonth = $prevMonth->daysInMonth;
    @endphp
<div class="h-full">
    <div class="bg-white rounded-2xl p-6 shadow-sm w-full h-full">
        <!-- Header -->
        <div class="flex items-center justify-between mb-7">
            <h2 class="text-lg font-semibold">
                {{ $currentMonth->format('F') }}
                <span class="text-gray-400">{{ $currentMonth->year }}</span>
            </h2>

            <div class="flex gap-2">
                <button wire:click="prevMonth" class="w-9 h-9 flex items-center justify-center rounded-full border hover:bg-gray-100">
                    <flux:icon name="chevron-left" class="w-4 h-4" />
                </button>
                <button wire:click="nextMonth" class="w-9 h-9 flex items-center justify-center rounded-full border hover:bg-gray-100">
                    <flux:icon name="chevron-right" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <!-- Day Labels -->
        <div class="grid grid-cols-7 text-sm text-gray-400 mb-4">
            <div>Su</div>
            <div>Mo</div>
            <div>Tu</div>
            <div>We</div>
            <div>Th</div>
            <div>Fr</div>
            <div>Sa</div>
        </div>
        <div class="grid grid-cols-7 gap-y-5 text-sm">

            {{-- Tanggal bulan sebelumnya --}}
            @for ($i = $startDay - 1; $i >= 0; $i--)
                <div class="w-9 h-9 mx-auto flex items-center justify-center rounded-full text-gray-300">
                    {{ $daysInPrevMonth - $i }}
                </div>
            @endfor

            {{-- Tanggal bulan sekarang --}}
            @for ($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $date = $currentMonth->copy()->day($day);
                    $dateString = $date->toDateString();
                    $isToday = $date->isToday();
                    $isSelected = $selectedDate === $dateString;
                    $hasEvent = in_array($day, []); // Masukan tanggal event
                @endphp

                <div class="relative text-center cursor-pointer"
                    wire:click="selectDate('{{ $dateString }}')">

                    <div class="w-9 h-9 mx-auto flex items-center justify-center rounded-full
                        {{ $isSelected ? 'bg-pink-500 text-white font-semibold' : '' }}
                        {{ !$isSelected && $isToday ? 'border border-pink-500' : '' }}">
                        {{ $day }}
                    </div>

                    @if($hasEvent)
                        <span class="w-1.5 h-1.5 bg-pink-500 rounded-full absolute bottom-0 left-1/2 -translate-x-1/2"></span>
                    @endif
                </div>
            @endfor

            {{-- Tanggal bulan berikutnya --}}
            @php
                $totalCells = $startDay + $daysInMonth;
                $nextDays = (7 - ($totalCells % 7)) % 7;
            @endphp

            @for ($i = 1; $i <= $nextDays; $i++)
                <div class="w-9 h-9 mx-auto flex items-center justify-center rounded-full text-gray-300">{{ $i }}</div>
            @endfor
        </div>
    </div>
</div>
