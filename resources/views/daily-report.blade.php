<x-layouts.app :title="__('DAR - HMA Workspace')">

    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
                    <flex:heading class="font-bold text-xl">Daily Report Activity</flex:heading>
                    <flux:text>Monitoring in one unified dashboard</flux:text>
                </div>
            </div>

            {{-- <livewire:dar.widget.kpi-overview-dar lazy/> --}}

            {{-- <livewire:dar.widget.chart-activity-task/> --}}

            <livewire:dar.widget.timeline-today-dar lazy />

            <livewire:dar.widget.board-overview-dar lazy/>

            {{-- <livewire:dar.dar-table-task lazy/> --}}
            <livewire:dar.widget.card-task-dar lazy/>
        </div>
    </div>
</x-layouts.app>
