<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading class="text-2xl font-bold">Project</flux:heading>
            <flux:description>
                Menampilkan...
            </flux:description>
        </div>
        <flux:button icon="plus-circle" :href="route('projects.create')" wire:navigate variant="primary" class="w-full sm:w-auto shrink-0">
            Tambah Proyek
        </flux:button>
    </div>

    {{-- Search Bar --}}
    <div class="relative">
        <flux:input icon="magnifying-glass" wire:model="search" wire:keydown.enter="applyFilters" wire:loading.attr="disabled" placeholder="Cari proyek berdasarkan nama..." class="w-full" />
        <div wire:loading wire:target="applyFilters,goToPage" class="absolute right-3 top-1/2 -translate-y-1/2">
            <svg class="animate-spin h-4 w-4 text-zinc-400" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" class="opacity-25" />
                <path fill="currentColor" class="opacity-75" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a10 10 0 00-10 10h4z" />
            </svg>
        </div>
    </div>
    <div class="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach(range(1, 4) as $_)
        <flux:skeleton.group animate="shimmer" class="flex flex-col rounded-2xl border border-zinc-100 dark:border-zinc-800 overflow-hidden">
            <flux:skeleton class="h-1.5 w-full rounded-none" />
            <div class="p-5 space-y-4">
                <div class="flex justify-between">
                    <flux:skeleton class="h-6 w-14 rounded-md" />
                    <flux:skeleton class="h-6 w-20 rounded-full" />
                </div>
                <flux:skeleton class="h-4 w-full rounded" />
                <flux:skeleton class="h-4 w-3/4 rounded" />
                <flux:skeleton class="h-4 w-1/2 rounded" />
                <flux:skeleton class="h-1.5 w-full rounded-full" />
                <div class="space-y-2 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                    <flux:skeleton class="h-3 w-full rounded" />
                    <flux:skeleton class="h-3 w-3/4 rounded" />
                </div>
            </div>
            <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-800 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <flux:skeleton class="w-6 h-6 rounded-full" />
                    <flux:skeleton class="h-3 w-28 rounded" />
                </div>
                <flux:skeleton class="h-7 w-7 rounded-lg" />
            </div>
        </flux:skeleton.group>
        @endforeach
    </div>
</div>
