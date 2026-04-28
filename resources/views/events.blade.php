<x-layouts.app.event>
    <x-slot name="header">All Events</x-slot>
    <x-slot name="description">
        Kelola seluruh event {{ today()->format('F Y') }} — registrasi, check-in, reimbursement, hingga sertifikat.
    </x-slot>

    <x-slot name="searchButton">
        <x-slot name="action">Event</x-slot>
    </x-slot>

    @php
        $events = [
            [
                'id' => 1,
                'title' => 'Annual Design Conference 2026',
                'category' => 'Conference',
                'type' => 'hybrid',
                'banner' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800&auto=format',
                'date' => '22 - 25 Apr 2026',
                'time' => '09:00 - 16:00 WIB',
                'location' => 'Hotel Mulia, Jakarta',
                'status' => 'ongoing',
                'participants' => 124,
                'capacity' => 150,
                'days' => 4,
            ],
            [
                'id' => 2,
                'title' => 'Workshop Penanganan Perkara Pidana Khusus',
                'category' => 'Workshop',
                'type' => 'offline',
                'banner' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&auto=format',
                'date' => '02 - 03 May 2026',
                'time' => '08:30 - 17:00 WIB',
                'location' => 'Kejati Riau, Pekanbaru',
                'status' => 'upcoming',
                'participants' => 45,
                'capacity' => 60,
                'days' => 2,
            ],
            [
                'id' => 3,
                'title' => 'Webinar Kepatuhan Internal & Anti Korupsi',
                'category' => 'Webinar',
                'type' => 'online',
                'banner' => 'https://images.unsplash.com/photo-1591115765373-5207764f72e7?w=800&auto=format',
                'date' => '15 May 2026',
                'time' => '13:00 - 15:30 WIB',
                'location' => 'Zoom Meeting',
                'status' => 'upcoming',
                'participants' => 320,
                'capacity' => 500,
                'days' => 1,
            ],
            [
                'id' => 4,
                'title' => 'Rapat Koordinasi Triwulan Q2',
                'category' => 'Meeting',
                'type' => 'hybrid',
                'banner' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800&auto=format',
                'date' => '20 May 2026',
                'time' => '09:00 - 12:00 WIB',
                'location' => 'Kantor Pusat & Zoom',
                'status' => 'upcoming',
                'participants' => 28,
                'capacity' => 40,
                'days' => 1,
            ],
            [
                'id' => 5,
                'title' => 'Diklat Fungsional Jaksa Angkatan IV',
                'category' => 'Training',
                'type' => 'offline',
                'banner' => 'https://images.unsplash.com/photo-1517048676732-d65bc937f952?w=800&auto=format',
                'date' => '01 - 14 Jun 2026',
                'time' => '07:30 - 17:00 WIB',
                'location' => 'Badiklat Kejaksaan, Ragunan',
                'status' => 'upcoming',
                'participants' => 80,
                'capacity' => 80,
                'days' => 14,
            ],
            [
                'id' => 6,
                'title' => 'Sosialisasi SOP Reimbursement Baru',
                'category' => 'Sosialisasi',
                'type' => 'online',
                'banner' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?w=800&auto=format',
                'date' => '10 Mar 2026',
                'time' => '10:00 - 11:30 WIB',
                'location' => 'Microsoft Teams',
                'status' => 'completed',
                'participants' => 180,
                'capacity' => 200,
                'days' => 1,
            ],
        ];

        $statusColor = [
            'ongoing' => 'green',
            'upcoming' => 'blue',
            'completed' => 'zinc',
        ];

        $typeIcon = [
            'offline' => 'building-office-2',
            'online' => 'video-camera',
            'hybrid' => 'globe-alt',
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 mt-6">
        @foreach ($events as $event)
            <div wire:key="event-{{ $event['id'] }}"
                class="group bg-white rounded-2xl border border-zinc-200 overflow-hidden flex flex-col hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300">

                {{-- Banner --}}
                <div class="relative h-40 overflow-hidden">
                    <img src="{{ $event['banner'] }}" alt="{{ $event['title'] }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition duration-500" />
                    <div class="absolute inset-0 bg-linear-to-t from-black/60 to-transparent"></div>
                    <div class="absolute top-3 left-3 flex gap-2">
                        <flux:badge :color="$statusColor[$event['status']]" size="sm" class="capitalize">
                            {{ $event['status'] }}
                        </flux:badge>
                        <flux:badge color="zinc" size="sm" class="capitalize bg-white/90!">
                            <flux:icon :name="$typeIcon[$event['type']]" class="w-3 h-3 mr-1" />
                            {{ $event['type'] }}
                        </flux:badge>
                    </div>
                    <div class="absolute bottom-3 left-3 right-3">
                        <p class="text-[10px] uppercase tracking-wider text-white/80 font-medium">
                            {{ $event['category'] }} · {{ $event['days'] }} hari
                        </p>
                    </div>
                </div>

                {{-- Body --}}
                <div class="p-5 flex flex-col flex-1">
                    <h3 class="font-semibold text-gray-900 leading-snug line-clamp-2 mb-3 min-h-12">
                        {{ $event['title'] }}
                    </h3>

                    <div class="space-y-1.5 text-xs text-gray-500 mb-4">
                        <div class="flex items-center gap-2">
                            <flux:icon.calendar-days class="w-4 h-4 text-gray-400" />
                            {{ $event['date'] }}
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.clock class="w-4 h-4 text-gray-400" />
                            {{ $event['time'] }}
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:icon.map-pin class="w-4 h-4 text-gray-400" />
                            <span class="line-clamp-1">{{ $event['location'] }}</span>
                        </div>
                    </div>

                    {{-- Capacity --}}
                    <div class="mb-4">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-500">
                                <flux:icon.users class="w-3.5 h-3.5 inline -mt-0.5" />
                                {{ $event['participants'] }}/{{ $event['capacity'] }} peserta
                            </span>
                            <span class="font-medium text-gray-700">
                                {{ round($event['participants'] / $event['capacity'] * 100) }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-accent h-full rounded-full transition-all"
                                style="width: {{ $event['participants'] / $event['capacity'] * 100 }}%"></div>
                        </div>
                    </div>

                    <flux:separator class="my-3" />

                    <div class="flex gap-2 mt-auto">
                        <flux:tooltip content="Copy invite link">
                            <flux:button iconVariant="outline" icon="link" variant="outline" size="sm"
                                class="cursor-pointer" />
                        </flux:tooltip>
                        <flux:button href="{{ route('event.scan', $event['id']) }}" iconVariant="outline"
                            icon="qr-code" variant="outline" size="sm" class="flex-1 cursor-pointer" wire:navigate>
                            Check-in
                        </flux:button>
                        <flux:button href="{{ route('events.show', $event['id']) }}" variant="primary" size="sm"
                            class="flex-1 cursor-pointer" wire:navigate>
                            Open
                        </flux:button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.app.event>
