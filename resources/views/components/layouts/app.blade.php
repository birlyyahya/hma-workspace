<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="py-0! px-0! bg-white">
        <div class="bg-zinc-50 border relative my-3 md:mr-2 rounded-2xl border-zinc-200 dark:border-zinc-700">
            <div class="border-b px-6 py-2 items-center justify-between hidden md:flex sticky top-0 z-10 bg-zinc-50 rounded-t-2xl">
                <livewire:components.breadcrumb />
                <livewire:components.notification-header/>
            </div>
            <div class="h-full max-h-196 overflow-auto px-4">
                {{ $slot }}
            </div>
        </div>
    </flux:main>
</x-layouts.app.sidebar>
