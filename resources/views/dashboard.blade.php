<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-screen overflow-auto w-full flex-col gap-4 rounded-xl py-6">
        <div class="flex flex-col gap-4 md:flex-row">
            {{-- widget 1 --}}
            <div class="w-full min-w-0 space-y-4 md:basis-3/4">
                <div class="flex gap-4">
                    <div class="relative w-full overflow-hidden rounded-xl ">
                        <livewire:widget.dashboard.barchart />
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-1 lg:grid-cols-3">
                    <div class="relative overflow-hidden !h-full rounded-xl">
                        <livewire:widget.dashboard.calendar />
                    </div>
                    <div class="relative max-w-full overflow-hidden rounded-xl sm:col-span-1 lg:col-span-2">
                        <livewire:widget.dashboard.profile />
                    </div>
                </div>
            </div>
            {{-- widget 2 --}}
            <div class="w-full space-y-4 md:basis-1/4">
                <div class="relative max-w-full h-[60vh] overflow-hidden rounded-xl sm:h-[70vh] md:h-screen">
                    <livewire:widget.dashboard.task />
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
