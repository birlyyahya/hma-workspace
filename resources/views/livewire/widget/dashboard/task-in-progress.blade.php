<?php

use Carbon\Carbon;
use Livewire\Volt\Component;

new class extends Component {

    public function getTasksProperty()
    {
        $response = Http::get(env('API_IZIN'). '/global/dar/list?user_id='.Auth::user()->id.'&limit=1000000&status=1')->json();

        return $response['data'] ?? [];
    }


}; ?>

{{-- Recent Task --}}
<div class="space-y-6 mb-6">
    <!-- Section -->
    <div>
        <h3 class="font-semibold text-gray-800 mb-4">Task In Progress</h3>

        <!-- Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            @forelse($this->tasks as $item)
            @php
            $isLate = now()->gt(Carbon::parse($item['end_date'])) && $item['status'] != 4;
            @endphp
            <!-- CARD 1 -->
            <div class="bg-white rounded-xl border p-4 shadow-sm space-y-3">
                <div class="flex justify-between items-start">
                    <h3 class="font-semibold text-gray-800">{{ $item['activity'] }}</h3>
                    <flux:icon name="star" class="w-4 h-4 text-yellow-400" />
                </div>

                <!-- team -->
                <div class="flex items-center justify-between gap-1 text-sm text-gray-500">
                    <div class="space-y-2">
                        <flux:text>Assign</flux:text>
                        <flux:avatar circle name="Isabella Silva" size="xs" />
                    </div>

                    <div class="space-y-2 text-right">
                        <flux:text>Support</flux:text>
                        <flux:avatar.group>
                            <flux:avatar circle name="Isabella Silva" size="xs" />
                            <flux:avatar circle name="Isabella Silva" size="xs" />
                            <flux:avatar circle name="Isabella Silva" size="xs" />
                            <flux:avatar circle name="Isabella Silva" size="xs" />
                        </flux:avatar.group>
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-1">
                    <p class="text-xs text-gray-500">Status Kegiatan</p>
                    <flux:badge :color="$isLate ? 'red' : 'blue'" size="sm">
                        {{ $isLate ? 'Terlambat — ' . Carbon::parse($item['end_date'])->diffForHumans() : 'In Progress' }}
                    </flux:badge>
                </div>

                <hr>

                <!-- Info -->
                <div class="grid grid-cols-2 gap-3 text-sm text-gray-600">
                    <div>
                        <p class="text-xs text-gray-400">Dimulai</p>
                        <p>{{ Carbon::parse($item['start_date'])->format('d/m/Y') }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Berakhir</p>
                        <p class="{{ $isLate ? 'text-red-600' : '' }}">{{ Carbon::parse($item['end_date'])->format('d/m/Y') }}</p>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-span-full text-center mb-6 text-gray-500">
                <p class="text-sm">No tasks in progress.</p>
            </div>
            @endforelse

        </div>
    </div>
</div>
