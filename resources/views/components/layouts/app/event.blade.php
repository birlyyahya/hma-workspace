<x-layouts.app>
    <div class="py-8 max-h-screen overflow-auto">
        <div class="bg-white rounded-xl shadow-md justify-between flex items-center p-4 gap-6">
            <div class="space-y-2">
                <flux:heading size="lg" class="text-[1.25rem] font-semibold">{{ $header ?? 'Upcoming Events' }}</flux:heading>
                <flux:description class="font-light ml-auto text-zinc-500">{{ $description ?? 'Stay updated with upcoming events this '. today()->format('F') }}</flux:description>
            </div>
            <div x-data="{search:false}" class="flex items-center justify-end flex-1">
                @isset($searchButton)
                <flux:button variant="outline" icon="magnifying-glass" class="rounded-full! bg-zinc-100" x-on:click="search=!search"></flux:button>
                <div x-show="search" x-cloak x-transition:enter="transition-all duration-300 ease-out" x-transition:enter-start="opacity-0 translate-x-6 w-0" x-transition:enter-end="opacity-100 translate-x-0 w-96" x-transition:leave="transition-all duration-200 ease-in" x-transition:leave-start="opacity-100 translate-x-0 w-96" x-transition:leave-end="opacity-0 translate-x-6 w-0" class="overflow-hidden origin-right ml-2">
                    <flux:input placeholder="Search..." class="w-96!" />
                </div>
                @endisset
                @isset($action)
                <flux:button class="ml-4 self-center" icon="plus-circle" href="" variant="primary">
                    Add {{ $action }}
                </flux:button>
                @endisset
            </div>
        </div>


        <div class="rounded-xl mt-6 py-4 px-4 sticky w-full -top-5 z-10 mx-auto bg-white shadow-lg">
            <div class="flex justify-center items-center mt-10 mb-15">
                <a href="" class=" border-accent border-b-2 pb-2 px-10">
                    <div class="flex flex-col items-center gap-2">
                        <flux:icon.folder class="size-6" variant="outline" />
                        <p class="text-xs">Events</p>
                    </div>
                </a>
                <a href="" class=" border-b-2 pb-2 px-10">
                    <div class="flex flex-col items-center gap-2 ">
                        <flux:icon.user-group class="size-6" variant="outline" />
                        <p class="text-xs">Peserta</p>
                    </div>
                </a>
                <a href="" class="border-b-2 pb-2 px-10">
                    <div class="flex flex-col items-center gap-2 ">
                        <flux:icon.banknotes class="size-6" variant="outline" />
                        <p class="text-xs">Reimbursement</p>
                    </div>
                </a>
                <a href="" class=" border-b-2 pb-2 px-10">
                    <div class="flex flex-col items-center gap-2 ">
                        <flux:icon.check-badge class="size-6" variant="outline" />
                        <p class="text-xs">Certificate</p>
                    </div>
                </a>
                <a href="" class=" border-b-2 pb-2 px-10">
                    <div class="flex flex-col items-center gap-2 ">
                        <flux:icon.clock class="size-6" variant="outline" />
                        <p class="text-xs">Sesi Events</p>
                    </div>
                </a>
            </div>

            {{ $slot }}
        </div>

</x-layouts.app>
