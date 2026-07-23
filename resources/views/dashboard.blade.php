<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-screen w-full flex-col gap-5 overflow-y-auto overflow-x-hidden rounded-xl py-6">

        {{-- Welcome banner --}}
        <div class="relative w-full rounded-xl">
            <livewire:widget.dashboard.welcome lazy />
        </div>

        {{-- Mobile-only quick shortcuts --}}
        <div class="md:hidden grid grid-cols-2 gap-3">
            <a href="{{ route('izin.quick') }}" wire:navigate
                class="group relative overflow-hidden rounded-2xl border border-red-200 bg-linear-to-br from-red-600 to-rose-600 p-4 text-white shadow-sm active:scale-[0.98] transition">
                <div class="pointer-events-none absolute -right-6 -bottom-6 size-24 rounded-full bg-white/15 blur-2xl"></div>
                <div class="relative flex flex-col gap-2">
                    <div class="size-10 rounded-xl bg-white/20 ring-1 ring-white/30 backdrop-blur flex items-center justify-center">
                        <flux:icon name="document-plus" class="size-5 text-white" />
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-white/80 uppercase tracking-wide">Shortcut</p>
                        <p class="text-sm font-semibold leading-tight">Ajukan Izin</p>
                        <p class="text-[11px] text-white/75 leading-snug">Cepat & langsung</p>
                    </div>
                </div>
            </a>

            <a href="{{ route('izin') }}" wire:navigate
                class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-4 active:scale-[0.98] transition">
                <div class="flex flex-col gap-2">
                    <div class="size-10 rounded-xl bg-zinc-100 ring-1 ring-zinc-200 flex items-center justify-center">
                        <flux:icon name="list-bullet" class="size-5 text-zinc-700" />
                    </div>
                    <div>
                        <p class="text-[11px] font-medium text-zinc-400 uppercase tracking-wide">Lihat</p>
                        <p class="text-sm font-semibold text-zinc-900 leading-tight">Riwayat Izin</p>
                        <p class="text-[11px] text-zinc-500 leading-snug">Lacak status pengajuan</p>
                    </div>
                </div>
            </a>
        </div>

        {{-- Announcement (full width — important) --}}
        <livewire:widget.dashboard.announcement lazy />

        <livewire:widget.dashboard.project-activity lazy />

        <livewire:widget.dashboard.event-calendar lazy />

        {{-- Latest Articles --}}
        <livewire:widget.dashboard.latest-articles lazy />

        {{-- Knowledge Hub --}}
        <livewire:widget.dashboard.knowledge-hub lazy />

    </div>
</x-layouts.app>
