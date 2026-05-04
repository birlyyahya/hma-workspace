<x-layouts.app :title="__('DAR - HMA Workspace')">

    <div class="bg-zinc-50/50 min-h-screen">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6 space-y-6">
               {{-- HERO HEADER --}}
            <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-linear-to-br from-red-700 via-red-600 to-rose-600 px-6 sm:px-8 py-6 text-white shadow-sm">
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="absolute -left-24 -bottom-28 size-72 rounded-full bg-black/15 blur-3xl"></div>
                </div>

                <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                    <div class="min-w-0 space-y-3">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20 backdrop-blur">
                            <flux:icon name="document-text" class="size-3.5" />
                            <span>Workspace</span>
                            <span class="opacity-50">/</span>
                            <span>DAR</span>
                        </div>
                        <div class="space-y-1">
                            <flux:heading size="xl" class="text-white leading-tight">
                                Daily Activity Report
                            </flux:heading>
                            <p class="text-sm text-white/80">
                                Lihat ringkasan aktivitas harian Anda dan pantau perkembangan tugas dengan mudah.
                            </p>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-3">
                        <flux:modal.trigger name="create-task" class="w-full sm:w-auto">
                            <flux:button
                                icon="plus-circle"
                                variant="primary"
                                class="cursor-pointer w-full sm:w-auto bg-white! text-red-700! hover:bg-white/90!"
                            >
                                Buat Tugas Baru
                            </flux:button>
                        </flux:modal.trigger>
                    </div>
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
