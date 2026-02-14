<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-screen overflow-auto w-full flex-col gap-4 rounded-xl py-6">
        <div class="flex gap-4">
            {{-- widget 1 --}}
            <div class="w-full basis-3/4 min-w-0 space-y-4">
                <div class="flex gap-4">
                    <div class="relative w-full overflow-hidden rounded-xl ">
                        <livewire:widget.barchart />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div class="relative overflow-hidden !h-full rounded-xl">
                        <livewire:widget.calendar />
                    </div>
                    <div class="relative max-w-full col-span-2 overflow-hidden rounded-xl ">
                        <livewire:widget.profile />
                    </div>
                </div>
            </div>
            {{-- widget 2 --}}
            <div class="space-y-4 basis-1/4">
                <div class="grid grid-cols-1 gap-4">
                    <div class="relative max-w-full overflow-hidden rounded-xl">
                        <livewire:widget.task />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
