<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="py-0! px-0! bg-white">
        <div class="bg-zinc-50 border my-3 mr-2 rounded-2xl border-zinc-200 dark:border-zinc-700">
            <div class="border-b px-6 py-2 flex items-center justify-between">
                <flux:breadcrumbs class="gap-1">
                    <flux:breadcrumbs.item class="text-zinc-500" href="#">Home</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item href="#">Blog</flux:breadcrumbs.item>
                    <flux:breadcrumbs.item>Post</flux:breadcrumbs.item>
                </flux:breadcrumbs>
                <livewire:components.notification-header/>

            </div>
            <div class="h-180 overflow-auto px-5">
                {{ $slot }}
            </div>
        </div>

    </flux:main>
</x-layouts.app.sidebar>
