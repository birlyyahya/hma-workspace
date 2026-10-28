<x-layouts.app :title="__('DAR - HMA Workspace')">

    <div class="bg-zinc-50/50 min-h-screen">
        <div class="max-w-screen-2xl mx-auto md:p-5 md:space-y-6 space-y-4">
            {{-- HERO HEADER --}}
            <header class="border-b border-zinc-200 pb-5 pt-2 dark:border-zinc-800 sm:pb-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between sm:gap-8">
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2 text-[11px] font-medium text-red-800 dark:text-red-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-800 dark:bg-red-400"></span>
                            <span>Daily workspace</span>
                        </div>
                        <flux:heading size="xl" class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl dark:text-white">
                            Daily Activity Report
                        </flux:heading>
                        <p class="mt-1.5 max-w-xl text-sm leading-5 text-zinc-600 dark:text-zinc-300">
                            Lihat aktivitas hari ini dan pantau perkembangan tugas Anda.
                        </p>
                    </div>

                    <flux:modal.trigger name="create-task" class="w-full shrink-0 sm:w-auto">
                        <flux:button
                            icon="plus-circle"
                            variant="primary"
                            class="w-full cursor-pointer bg-red-800! text-white! hover:bg-red-900! sm:w-auto dark:bg-red-700! dark:hover:bg-red-800!"
                        >
                            Buat Tugas Baru
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            </header>

            <livewire:dar.widget.timeline-today-dar lazy/>

            <livewire:dar.widget.board-overview-dar lazy/>

            <livewire:dar.widget.card-task-dar lazy/>
        </div>
    </div>
</x-layouts.app>
