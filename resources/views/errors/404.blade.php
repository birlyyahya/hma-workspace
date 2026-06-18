@auth
    <x-layouts.app :title="__('404 — Tidak Ditemukan')">
        <div class="min-h-[calc(100vh-1px)] flex items-center justify-center px-4 py-10">
            <x-errors.404 :message="$exception?->getMessage() ?: null" />
        </div>
    </x-layouts.app>
@else
    <x-layouts.auth :title="__('404 — Tidak Ditemukan')">
        <div class="flex flex-col items-center text-center gap-4">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-zinc-100">
                <flux:icon name="magnifying-glass" class="w-8 h-8 text-zinc-500" />
            </div>
            <flux:heading size="xl">Tidak Ditemukan</flux:heading>
            <flux:text>
                {{ $exception?->getMessage() ?: 'Halaman atau data yang kamu cari tidak ditemukan.' }}
            </flux:text>
            <flux:button :href="route('login')" icon="arrow-right-end-on-rectangle" variant="primary" wire:navigate>
                Masuk
            </flux:button>
        </div>
    </x-layouts.auth>
@endauth
