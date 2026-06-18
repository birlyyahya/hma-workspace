@auth
    <x-layouts.app :title="__('403 — Akses Ditolak')">
        <div class="min-h-[calc(100vh-1px)] flex items-center justify-center px-4 py-10">
            <x-errors.403 :message="$exception?->getMessage() ?: null" />
        </div>
    </x-layouts.app>
@else
    <x-layouts.auth :title="__('403 — Akses Ditolak')">
        <div class="flex flex-col items-center text-center gap-4">
            <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-red-50">
                <flux:icon name="lock-closed" class="w-8 h-8 text-red-500" />
            </div>
            <flux:heading size="xl">Akses Ditolak</flux:heading>
            <flux:text>
                {{ $exception?->getMessage() ?: 'Kamu tidak memiliki izin untuk membuka halaman ini.' }}
            </flux:text>
            <flux:button :href="route('login')" icon="arrow-right-end-on-rectangle" variant="primary" wire:navigate>
                Masuk
            </flux:button>
        </div>
    </x-layouts.auth>
@endauth
