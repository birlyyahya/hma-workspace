<x-layouts.app.event>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Upcoming Events') }}
        </h2>
    </x-slot>

    <x-slot name="description">
        Stay updated with upcoming events this {{ today()->format('F') }}
    </x-slot>

    <x-slot name="searchButton">
        <x-slot name="action">
            Add Events
        </x-slot>
    </x-slot>

    <div class="grid [grid-template-columns:repeat(auto-fit,minmax(320px,320px))] justify-center gap-4 mt-6 transition-all duration-300">
        @for ($i=0;$i < 10; $i++) <div class="bg-zinc-50 rounded-2xl border border-gray-100 p-5 flex flex-col justify-between hover:shadow-accent/50  hover:shadow-sm transition duration-500">

            <!-- Header -->
            <div class="flex justify-between items-center mb-3">
                <img src="https://i.pravatar.cc/100" class="w-8 h-8" />
                <div class="flex items-center gap-1 text-xs text-gray-500">
                    <flux:icon.calendar-days class="w-4 h-4" />
                    15 June
                </div>
            </div>

            <!-- Time -->
            <div class="flex items-center gap-2 text-xs text-gray-400 my-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                10:30 AM - 02:00 PM
            </div>


            <!-- Title -->
            <h3 class="font-semibold text-gray-800 leading-snug mb-3">
                UI/UX Designs: Event with Edward James
            </h3>

            <!-- Participants -->
            <div class="flex items-center mb-4">
                <img class="w-7 h-7 rounded-full border-2 border-white" src="https://i.pravatar.cc/100?img=1">
                <img class="w-7 h-7 rounded-full border-2 border-white -ml-2" src="https://i.pravatar.cc/100?img=2">
                <img class="w-7 h-7 rounded-full border-2 border-white -ml-2" src="https://i.pravatar.cc/100?img=3">
                <span class="text-xs text-gray-500 ml-2">+2</span>
            </div>

            <flux:separator class="my-4"></flux:separator>

            <!-- Footer Buttons -->
            <div class="flex gap-2 mt-2">
                <flux:button iconVariant="outline" icon="clipboard" variant="outline" class="flex-1 cursor-pointer">
                </flux:button>
                <flux:button href="" iconVariant="outline" icon="pencil-square" variant="outline" class="flex-1 cursor-pointer">
                    Edit Event
                </flux:button>
                <flux:button href="{{ route('events.show', 1) }}" variant="primary" class="flex-1 cursor-pointer" wire:navigate>
                    Join Event
                </flux:button>
            </div>
    </div>
    @endfor
    </div>

</x-layouts.app.event>
