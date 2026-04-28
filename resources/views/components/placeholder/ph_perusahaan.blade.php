<div class="space-y-6 animate-pulse px-2 py-4">

     {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading class="text-2xl font-bold">Perusahaan</flux:heading>
            <flux:description>
                Menampilkan...
            </flux:description>
        </div>
        <flux:button icon="plus-circle" wire:click="openCreate" variant="primary" class="w-full sm:w-auto shrink-0">
            Tambah Perusahaan
        </flux:button>
    </div>

  {{-- Search --}}
    <div class="relative">
        <flux:input icon="magnifying-glass" wire:model="search" wire:keydown.enter="applyFilters" wire:loading.attr="disabled" placeholder="Cari nama perusahaan atau direktur..." class="w-full" />
        <div wire:loading wire:target="applyFilters,goToPage" class="absolute right-3 top-1/2 -translate-y-1/2">
            <flux:icon name="arrow-path" class="w-4 h-4 text-zinc-400 animate-spin" />
        </div>
    </div>

    <div class="hidden md:block bg-white dark:bg-zinc-900 rounded-2xl border border-zinc-100 dark:border-zinc-800 shadow-sm overflow-hidden">
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">

            <div class="grid grid-cols-5 gap-4 px-5 py-4 items-center">
                <div class="space-y-2">
                    <div class="h-4 w-32 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-3 w-48 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
                <div class="h-4 w-24 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="h-12 w-20 bg-zinc-200 dark:bg-zinc-700 rounded-md"></div>
                <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="flex justify-end gap-2">
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-4 px-5 py-4 items-center">
                <div class="space-y-2">
                    <div class="h-4 w-32 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-3 w-48 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
                <div class="h-4 w-24 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="h-12 w-20 bg-zinc-200 dark:bg-zinc-700 rounded-md"></div>
                <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="flex justify-end gap-2">
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
            </div>

            <div class="grid grid-cols-5 gap-4 px-5 py-4 items-center">
                <div class="space-y-2">
                    <div class="h-4 w-32 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-3 w-48 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
                <div class="h-4 w-24 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="h-12 w-20 bg-zinc-200 dark:bg-zinc-700 rounded-md"></div>
                <div class="h-4 w-20 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                <div class="flex justify-end gap-2">
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                    <div class="h-8 w-16 bg-zinc-200 dark:bg-zinc-700 rounded"></div>
                </div>
            </div>

        </div>
    </div>
</div>
