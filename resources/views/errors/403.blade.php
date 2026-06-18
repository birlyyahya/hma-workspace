<x-layouts.auth :title="__('403 — Akses Ditolak')">
    <div class="text-center">
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
            @auth
                <flux:button :href="route('dashboard')" icon="home" variant="primary" wire:navigate>
                    Ke Dashboard
                </flux:button>
                <flux:button x-on:click="window.history.back()" icon="arrow-left" variant="ghost">
                    Kembali
                </flux:button>
            @else
                <flux:button :href="route('login')" icon="arrow-right-end-on-rectangle" variant="primary" wire:navigate>
                    Masuk
                </flux:button>
            @endauth
        </div>
    </div>
</x-layouts.auth>
