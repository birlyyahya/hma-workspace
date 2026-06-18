@auth
    <x-layouts.app :title="__('403 — Akses Ditolak')">
        <div class="min-h-[calc(100vh-1px)] flex items-center justify-center px-4 py-10">
            <div class="max-w-md w-full text-center">
                {{-- Icon --}}
                <div class="flex justify-center mb-6">
                    <div class="relative flex items-center justify-center w-20 h-20 rounded-3xl bg-red-50 ring-8 ring-red-50/60">
                        <flux:icon name="lock-closed" class="w-9 h-9 text-red-500" />
                    </div>
                </div>

                {{-- Code --}}
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-500 mb-2">
                    Error 403
                </p>

                {{-- Title --}}
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-zinc-900">
                    Akses Ditolak
                </h1>

                {{-- Message --}}
                <p class="mt-3 text-sm md:text-base text-zinc-600">
                    {{ $exception?->getMessage() ?: 'Kamu tidak memiliki izin untuk membuka halaman ini. Hubungi pemilik project atau administrator jika kamu merasa ini sebuah kesalahan.' }}
                </p>

                {{-- Divider --}}
                <div class="mt-6 flex items-center justify-center gap-2">
                    <span class="h-px w-10 bg-zinc-200"></span>
                    <span class="text-xs text-zinc-400 uppercase tracking-wide">HMA Workspace</span>
                    <span class="h-px w-10 bg-zinc-200"></span>
                </div>

                {{-- Actions --}}
                <div class="mt-8 flex flex-wrap justify-center gap-3">
                    <flux:button :href="route('dashboard')" icon="home" variant="primary" wire:navigate>
                        Ke Dashboard
                    </flux:button>
                    <flux:button x-on:click="window.history.back()" icon="arrow-left" variant="ghost">
                        Kembali
                    </flux:button>
                </div>
            </div>
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
