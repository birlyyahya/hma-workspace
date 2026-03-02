<?php

use function Livewire\Volt\{state};

//

?>

<div>
    <div class=" bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100">

        {{-- Header --}}
        <div class="px-5 pt-4 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Upcoming Event</h3>
            <button class="text-gray-400 hover:text-gray-600">
                <flux:icon name="ellipsis-horizontal" class="w-5 h-5" />
            </button>
        </div>

        {{-- Image --}}
        <div class="relative mt-3">
            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=870&q=80" alt="Event Image" class="w-full h-38 object-cover">
            <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-xs font-medium shadow">
                🎵 Music
            </span>
        </div>

        {{-- Body --}}
        <div class="p-5 space-y-3">
            <div>
                <h2 class="text-base font-bold text-gray-900 leading-tight">
                    Lorem, ipsum.
                </h2>
                <p class="text-xs text-gray-500">
                    Jakarta
                </p>
            </div>

            <p class=" text-gray-600 line-clamp-2 text-xs">
                Lorem ipsum dolor, sit amet consectetur adipisicing elit. Beatae, rerum?
            </p>

            {{-- Date & Button --}}
            <div class="flex items-center justify-between pt-2">
                <div class="flex items-start gap-2">
                    <div class="bg-gray-100 p-2 rounded-lg">
                        <flux:icon name="calendar-days" class="w-5 h-5 text-gray-600" />
                    </div>
                    <div class="text-sm">
                        <p class="font-semibold text-gray-800 text-xs">22 Jun 2025</p>
                        <p class="text-gray-500 text-xs">10:00 AM </p>
                    </div>
                </div>

                <flux:button variant="primary" size="sm" class="rounded-full px-5">
                    View Details
                </flux:button>
            </div>
        </div>
    </div>

</div>
