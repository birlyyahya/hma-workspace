<?php


use Livewire\Volt\Component;
use Carbon\CarbonPeriod;
use Carbon\Carbon;

new class extends Component {

    public $events = [];
    public $date;
    public $tasks = [];
    public $taskStatus;

    public $months = [];

    public function mount(){
        $this->events = [
            [
                'title' => 'Planning',
                'start_date' => '2025-01-10',
                'end_date' => '2025-03-20',
                'row' => 0,
                'color' => 'blue'
            ],
            [
                'title' => 'Design',
                'start_date' => '2025-02-01',
                'end_date' => '2025-05-10',
                'row' => 1,
                'color' => 'orange'
            ],
            [
                'title' => 'Development',
                'start_date' => '2025-03-15',
                'end_date' => '2025-12-01',
                'row' => 2,
                'color' => 'green'
            ],
        ];

        $this->tasks = [
            [
                'id' => 1,
                'name' => 'Task 1',
                'due_date' => '12/03/2026',
                'progress' => '40%',
                'status' => 'In Progress',
                'team' => [
                    [
                        'id' => 1,
                        'name' => 'John Doe',
                        'role' => 'Developer',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Jane Doe',
                        'role' => 'Designer',
                    ],
                    [
                        'id' => 3,
                        'name' => 'Rudi Asto',
                        'role' => 'Support',
                    ],
                ],
            ],
            [
                'id' => 2,
                'name' => 'Task 2',
                'due_date' => '13/03/2026',
                'progress' => '60%',
                'status' => 'Completed',
                'team' => [
                    [
                        'id' => 1,
                        'name' => 'John Doe',
                        'role' => 'Developer',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Jane Doe',
                        'role' => 'Designer',
                    ],
                    [
                        'id' => 3,
                        'name' => 'Rudi Asto',
                        'role' => 'Support',
                    ],
                    [
                        'id' => 4,
                        'name' => 'Bagas Setiawan',
                        'role' => 'Support',
                    ]
                ],
            ],
            [
                'id' => 3,
                'name' => 'Task 3',
                'due_date' => '14/03/2026',
                'progress' => '80%',
                'status' => 'Hold',
                'team' => [
                    [
                        'id' => 1,
                        'name' => 'John Doe',
                        'role' => 'Developer',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Jane Doe',
                        'role' => 'Designer',
                    ],
                ],
            ],
            [
                'id' => 4,
                'name' => 'Task 4',
                'due_date' => '15/03/2026',
                'progress' => '100%',
                'status' => 'Pending',
                'team' => [
                    [
                        'id' => 1,
                        'name' => 'John Doe',
                        'role' => 'Developer',
                    ],
                    [
                        'id' => 2,
                        'name' => 'Jane Doe',
                        'role' => 'Designer',
                    ],
                ],
            ]

        ];

        $start = collect($this->events)->min('start_date');
        $end = collect($this->events)->max('end_date');

        $period = CarbonPeriod::create(
            Carbon::parse($start)->startOfMonth(),
            '1 month',
            Carbon::parse($end)->endOfMonth()
        );

        $this->months = collect($period)->map(function ($date) {
            return [
                'label' => $date->format('M Y'),
                'date' => $date->format('Y-m')
            ];
        })->values()->toArray();
    }
}

 ?>

<div class="space-y-6">
    <div class="bg-gray-50 border rounded-2xl overflow-hidden">

        {{-- HEADER --}}
        <div class="flex items-center justify-between px-6 py-4 bg-white">
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
        <div class="overflow-x-auto w-full relative">
            @php
            $totalWidth = count($months) * 100;
            @endphp
            <div style="width: {{ $totalWidth }}px;">

                {{-- HOURS HEADER --}}
                <div class="max-h-[500px] overflow-y-auto relative">
                    {{-- HEADER --}}
                    <div class="sticky top-0 z-20 bg-white border-b" style="display:grid; grid-template-columns: repeat({{ count($months) }}, 100px);">
                        @foreach($months as $month)
                        <div class="py-3 text-center text-sm text-gray-500">
                            {{ $month['label'] }}
                        </div>
                        @endforeach

                    </div>
                    @php
                    $maxRow = collect($events)->max('row');
                    $rowHeight = 100; // tinggi tiap row (sesuaikan dengan top spacing)
                    $containerHeight = ($maxRow + 1) * $rowHeight + 100;
                    @endphp

                    {{-- BODY --}}
                    <div class="relative w-full mx-auto bg-zinc-50" style="height: {{ $containerHeight }}px;">
                        {{-- Vertical Lines --}}
                        <div class="absolute inset-0 grid" style="grid-template-columns: repeat({{ count($months) }}, 100px);">
                            @foreach($months as $month)
                            <div class="border-r border-dashed border-gray-200"></div>
                            @endforeach
                        </div>

                        {{-- EVENTS --}}
                        @foreach($events as $event)

                        @php
                        $timelineStart = Carbon::parse($months[0]['date'].'-01');

                        $start = Carbon::parse($event['start_date']);
                        $end = Carbon::parse($event['end_date']);

                        $offsetMonths = $timelineStart->diffInMonths($start);
                        $durationMonths = $start->diffInMonths($end) + 1;

                        $totalMonths = count($months);
                        $columnWidth = 100 / $totalMonths;

                        $left = $offsetMonths * $columnWidth;
                        $width = $durationMonths * $columnWidth;

                        $colorMap = [
                        'blue' => 'bg-blue-500',
                        'indigo' => 'bg-indigo-500 ',
                        'orange' => 'bg-orange-500 ',
                        'green' => 'bg-green-500 ',
                        ];
                        @endphp

                        <div class="absolute block px-4 py-2 rounded-lg border-l-4 shadow-sm text-sm font-medium bg-white" style="
                        left: {{ $left }}%;
                        width: {{ $width }}%;
                        height: 70px;
                        top: {{ 20 + ($event['row'] * 100) }}px;
                        ">
                            <div class="w-10 bg-gray-200 rounded-full h-1">
                                <div class="{{ $colorMap[$event['color']] }} h-1 rounded-full" style="width: 45%"></div>
                            </div>
                            <div class="mt-2 flex justify-between">
                                {{ $event['title'] }}
                                <flux:tooltip content="{{ $event['title'] }}">
                                <flux:avatar name="{{ $event['title'] }}" size="xs" />
                                </flux:tooltip>
                            </div>
                            <flux:text class="text-xs mt-1 items-center text-gray-500 font-normal flex gap-2">
                                <flux:icon.flag class="size-3" />
                                {{ Carbon::parse($event['start_date'])->locale('id')->translatedFormat('d M Y') }} - {{ Carbon::parse($event['end_date'])->locale('id')->translatedFormat('d M Y') }}
                            </flux:text>

                        </div>

                        @endforeach

                    </div>
                </div>

            </div>
        </div>

    </div>

    <div class="overflow-x-auto bg-white rounded-lg">
        <div class="flex items-center justify-between px-6 py-4 bg-white">
            <div class="flex items-center gap-3">
                <flux:icon name="bars-3" class="w-5 h-5 text-gray-400" />
                <h2 class="text-base font-semibold">Task Assign</h2>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-500">
                <div class="flex items-center gap-2">
                    <flux:icon name="calendar" class="w-4 h-4" />
                </div>

                <flux:icon name="plus" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
                <flux:icon name="arrows-pointing-out" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
                <flux:icon name="ellipsis-horizontal" class="w-4 h-4 cursor-pointer hover:text-gray-700" />
            </div>
        </div>
        <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
            <thead class="bg-zinc-50 border shadow-none text-xs uppercase text-gray-500 ">
                <tr>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">No</th>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">Project Name</th>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">Due Date</th>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">Progress</th>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">Status</th>
                    <th class="px-3 py-3 md:px-6 whitespace-nowrap">Team</th>
                    <th class="px-3 py-3 md:px-6 text-right whitespace-nowrap">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->tasks as $task)
                <tr class="border-b border-gray-200 hover:bg-gray-50 transition">
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['id'] }}</td>
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['name'] }}</td>
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['due_date'] }}</td>
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">{{ $task['progress'] }}</td>
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                        <flux:select wire:model="taskStatus" placeholder="Status" >
                            <flux:select.option value="pending">Pending</flux:select.option>
                            <flux:select.option value="hold">Hold</flux:select.option>
                            <flux:select.option value="in-progress">In Progress</flux:select.option>
                            <flux:select.option value="completed">Completed</flux:select.option>
                        </flux:select>
                    </td>
                    <td class="px-3 py-3 md:px-6 whitespace-nowrap">
                        <flux:avatar.group>
                            @foreach ($task['team'] as $user)
                            @if ($loop->index < 3)
                            <flux:tooltip content="{{ $user['name'] }}">
                            <flux:avatar circle name="{{ $user['name'] }}" color="auto" color:seed="{{ $user['id'] }}" size="sm" />
                            </flux:tooltip>
                            @endif
                            @endforeach
                            @if (count($task['team']) > 3)
                            <flux:tooltip content="{{ join(', ', array_map(fn ($user) => $user['name'], $task['team'])) }}">
                                <flux:avatar circle size="sm">
                                    +{{ count($user) - 2 }}
                                </flux:avatar>
                            </flux:tooltip>
                            @endif
                    </flux:avatar.group>
                    </td>
                    <td class="px-3 py-3 md:px-6 justify-end flex whitespace-nowrap gap-2">
                        <flux:icon name="pencil-square" class="w-5 h-5 cursor-pointer hover:text-gray-700" />
                        <flux:icon name="ellipsis-horizontal" class="w-5 h-5 cursor-pointer hover:text-gray-700" />
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
