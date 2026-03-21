<?php


use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {

    public $events = [];
    public $date;
    public $data = [];
    public $hours;
    public $loading = true;

    public function mount(){
        $this->date = now()->format('d/m/Y');
    }
    #[On('darList')]
    public function getEventsProperty($data){

        $colors = ['blue','indigo','orange','green'];
        $today = now()->toDateString();
         $timelineDate = Carbon::createFromFormat('d/m/Y', $this->date);
        $dayStart = $timelineDate->copy()->startOfDay();
        $dayEnd = $timelineDate->copy()->endOfDay();
        $this->events = collect($data)
            ->values()
            ->filter(function ($item) use ($dayStart, $dayEnd) {

                $start = Carbon::parse($item['start_date']);
                $end = Carbon::parse($item['end_date']);

                return $start <= $dayEnd && $end >= $dayStart;
            })
            ->map(function($item,$index) use ($colors){

                $start = Carbon::parse($item['start_date']);
                $end = Carbon::parse($item['end_date']);

                $today = now()->startOfDay();
                $tomorrow = now()->endOfDay();

                // jika event mulai sebelum hari ini
                if ($start->lessThan($today)) {
                    $start = $today->copy()->setTime(8,30);
                }

                // jika event berakhir setelah hari ini
                if ($end->greaterThan($tomorrow)) {
                    $end = $today->copy()->setTime(17,30);
                }

                // convert ke jam decimal
                $startHour = $start->hour + ($start->minute / 60);
                $endHour = $end->hour + ($end->minute / 60);

                // clamp ke jam kerja
                $startHour = max(8.5, $startHour);
                $endHour   = min(17.5, $endHour);

                return [

                    'title' => $item['activity'],

                    // posisi dari jam 08:30
                    'start' => $startHour - 8.5,

                    // durasi
                    'span' => max(0.5, $endHour - $startHour),

                    'row' => $index,

                    'color' => $colors[$index % count($colors)],

                    'user' => $item['user'] ?? null
                ];
            })
            ->toArray();
            $this->hours = $this->generateHours('');

        $this->loading = false;
    }

    public function generateHours($events)
    {
        if (empty($events)) {

            $start = Carbon::parse('08:30');
            $end = Carbon::parse('17:30');

            } else {

            $start = collect($events)
                ->pluck('start_date')
                ->map(fn ($d) => Carbon::parse($d))
                ->min()
                ->copy()
                ->startOfHour();

            $end = collect($events)
                ->pluck('end_date')
                ->map(fn ($d) => Carbon::parse($d))
                ->max()
                ->copy()
                ->addHour();

        }

        $period = CarbonPeriod::create($start, '1 hour', $end);

        return collect($period)
            ->map(fn ($time) => $time->format('h:i A'))
            ->values()
            ->toArray();

    }

}

 ?>

<div>
    <div class="bg-gray-50 border rounded-2xl overflow-hidden">

        {{-- HEADER --}}
        <div class="flex items-center justify-between px-6 py-4 border-b bg-white">
            <div class="flex items-center gap-3">
                <flux:icon name="bars-3" class="w-5 h-5 text-gray-400" />
                <h2 class="text-base font-semibold">Task Timeline</h2>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <flux:icon name="calendar" class="w-4 h-4" />
                    {{ $date }}
                </div>

                <flux:icon name="plus" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
                <flux:icon name="arrows-pointing-out" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
                <flux:icon name="ellipsis-horizontal" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
            </div>
        </div>

        {{-- TIMELINE --}}
        @if(!$this->loading)
        <div class="overflow-x-auto relative">
            <div class="min-w-[1100px]">

                {{-- HOURS HEADER --}}
                <div class="max-h-90 overflow-y-auto border relative">

                    {{-- HEADER --}}
                    <div class="sticky top-0 z-20 bg-zinc-50 border-b" style="display:grid; grid-template-columns: repeat({{ count($hours) }}, minmax(0,1fr));">

                        @foreach($hours as $hour)
                        <div class="py-3 text-center text-sm text-gray-500">
                            {{ $hour }}
                        </div>
                        @endforeach

                    </div>
                    @php
                    $maxRow = collect($events)->max('row');
                    $rowHeight = 70; // tinggi tiap row (sesuaikan dengan top spacing)
                    $containerHeight = ($maxRow + 1) * $rowHeight + 40;
                    @endphp

                    {{-- BODY --}}
                    <div class="relative w-full mx-auto bg-white" style="height: {{ $containerHeight }}px;">

                        {{-- Vertical Lines --}}
                        <div class="absolute inset-0 grid" style="grid-template-columns: repeat({{ count($hours) }}, minmax(0,1fr));">
                            @foreach($hours as $hour)
                            <div class="border-r border-dashed border-gray-200 last:border-r-0"></div>
                            @endforeach
                        </div>

                        {{-- EVENTS --}}
                        @foreach($this->events as $event)

                        @php
                        $totalHours = count($hours);
                        $columnWidth = 100 / $totalHours;

                        $left = ($event['start'] * $columnWidth) + ($columnWidth / 2);
                        $width = $event['span'] * $columnWidth;

                        $colorMap = [
                        'blue' => 'bg-blue-100 text-blue-700 border-blue-400',
                        'indigo' => 'bg-indigo-100 text-indigo-700 border-indigo-400',
                        'orange' => 'bg-orange-100 text-orange-700 border-orange-400',
                        'green' => 'bg-green-100 text-green-700 border-green-400',
                        ];
                        @endphp

                        <div class="absolute px-4 py-2 rounded-lg border-l-4 shadow-sm text-sm font-medium
                                    {{ $colorMap[$event['color']] }}" style="
                                left: {{ $left }}%;
                                width: {{ $width }}%;
                                top: {{ 20 + ($event['row'] * 55) }}px;
                             ">

                            <div class="flex justify-between items-center">
                                <span>{{ $event['title'] }}</span>

                                <div class="flex -space-x-2">
                                    <div class="w-6 h-6 rounded-full bg-gray-300 border-2 border-white"></div>
                                    <div class="w-6 h-6 rounded-full bg-gray-400 border-2 border-white"></div>
                                    <div class="w-6 h-6 rounded-full bg-gray-500 border-2 border-white"></div>
                                </div>
                            </div>
                        </div>

                        @endforeach

                    </div>
                </div>

            </div>
        </div>
        @endif

    </div>
</div>
