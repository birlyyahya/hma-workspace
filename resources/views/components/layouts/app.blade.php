<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="flex min-h-0 flex-col overflow-hidden bg-white px-0! py-3! md:pr-2!">
        <div class="relative flex min-h-0 flex-1 flex-col rounded-2xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700">
            <div class="hidden shrink-0 items-center justify-between rounded-t-2xl border-b bg-zinc-50 px-6 py-2 md:flex">
                <livewire:components.breadcrumb />
                <livewire:components.notification-header/>
            </div>
            <div class="min-h-0 flex-1 overflow-auto px-4">
                {{ $slot }}
            </div>
        </div>
    </flux:main>
</x-layouts.app.sidebar>
