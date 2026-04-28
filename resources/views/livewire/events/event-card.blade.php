<?php

use Livewire\Volt\Component;

new class extends Component {
    public array $event = [
        'id' => 1,
        'title' => 'Annual Design Conference 2026',
        'category' => 'Conference',
        'banner' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800&auto=format',
        'date' => '22 - 25 Apr 2026',
        'time' => '09:00 - 16:00 WIB',
        'location' => 'Hotel Mulia, Jakarta',
        'speakers' => [
            ['name' => 'Edward James', 'role' => 'Lead Designer', 'avatar' => 'https://i.pravatar.cc/100?img=12'],
            ['name' => 'Sarah Wijaya', 'role' => 'Product Manager', 'avatar' => 'https://i.pravatar.cc/100?img=32'],
            ['name' => 'Andi Pratama', 'role' => 'Engineering', 'avatar' => 'https://i.pravatar.cc/100?img=45'],
        ],
        'participants' => 124,
        'capacity' => 150,
    ];
}; ?>

<div class="bg-white rounded-2xl border border-zinc-200 overflow-hidden hover:shadow-lg transition-all duration-300 max-w-md">
    <div class="relative h-44">
        <img src="{{ $event['banner'] }}" alt="{{ $event['title'] }}" class="w-full h-full object-cover" />
        <div class="absolute inset-0 bg-linear-to-t from-black/70 via-black/20 to-transparent"></div>
        <div class="absolute top-3 left-3">
            <flux:badge color="green" size="sm">Featured</flux:badge>
        </div>
        <div class="absolute bottom-3 left-3 right-3 text-white">
            <p class="text-[10px] uppercase tracking-wider opacity-80 font-medium">{{ $event['category'] }}</p>
            <h3 class="text-lg font-bold leading-snug line-clamp-2">{{ $event['title'] }}</h3>
        </div>
    </div>

    <div class="p-5 space-y-4">
        <div class="grid grid-cols-3 gap-3 text-xs">
            <div class="flex flex-col items-center gap-1 p-2 rounded-lg bg-zinc-50">
                <flux:icon.calendar-days class="w-4 h-4 text-accent" />
                <span class="text-gray-700 text-center">{{ $event['date'] }}</span>
            </div>
            <div class="flex flex-col items-center gap-1 p-2 rounded-lg bg-zinc-50">
                <flux:icon.clock class="w-4 h-4 text-accent" />
                <span class="text-gray-700 text-center">{{ $event['time'] }}</span>
            </div>
            <div class="flex flex-col items-center gap-1 p-2 rounded-lg bg-zinc-50">
                <flux:icon.map-pin class="w-4 h-4 text-accent" />
                <span class="text-gray-700 text-center line-clamp-1">{{ $event['location'] }}</span>
            </div>
        </div>

        <div>
            <p class="text-xs text-gray-500 mb-2">Speakers</p>
            <div class="flex items-center justify-between">
                <div class="flex -space-x-2">
                    @foreach ($event['speakers'] as $speaker)
                        <flux:tooltip content="{{ $speaker['name'] }} — {{ $speaker['role'] }}">
                            <img src="{{ $speaker['avatar'] }}" class="w-9 h-9 rounded-full border-2 border-white" />
                        </flux:tooltip>
                    @endforeach
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-gray-900">
                        {{ $event['participants'] }}/{{ $event['capacity'] }}
                    </p>
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider">Peserta</p>
                </div>
            </div>
        </div>

        <div class="flex gap-2 pt-2">
            <flux:button href="{{ route('event.scan', $event['id']) }}" icon="qr-code" variant="outline"
                size="sm" class="flex-1" wire:navigate>Check-in</flux:button>
            <flux:button href="{{ route('events.show', $event['id']) }}" variant="primary" size="sm"
                class="flex-1" wire:navigate>Open Event</flux:button>
        </div>
    </div>
</div>
