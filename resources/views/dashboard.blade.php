<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-screen w-full flex-col gap-5 overflow-auto rounded-xl py-6">

        {{-- Welcome banner --}}
        <div class="relative w-full rounded-xl">
            <livewire:widget.dashboard.welcome />
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
        <livewire:widget.dashboard.announcement />

        <livewire:widget.dashboard.event-calendar />
        {{-- Cash Advance + Inventory --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <livewire:widget.dashboard.summary-cash-advance />
            <livewire:widget.dashboard.summary-inventory />
        </div>

        {{-- DAR + Izin --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2">
                <livewire:widget.dashboard.summary-dar />
            </div>
            <div class="lg:col-span-1">
                <livewire:izin.widget.report-izin />
            </div>
        </div>

        {{-- Latest Articles --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs space-y-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-blue-50 ring-1 ring-blue-100 flex items-center justify-center">
                        <flux:icon name="newspaper" class="size-5 text-blue-600" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 leading-tight">Latest Articles</flux:heading>
                        <flux:description class="text-xs text-zinc-500">Bacaan & panduan terbaru</flux:description>
                    </div>
                </div>
                <a href="{{ route('knowledge.articles') }}" wire:navigate
                    class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
                    Lihat semua <flux:icon name="arrow-right" class="size-3.5" />
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @php
                    $articles = [
                        ['tag' => 'Guide',         'tagColor' => 'blue',   'title' => 'Cara Mengajukan Izin di Sistem',  'desc' => 'Panduan lengkap untuk membuat permohonan izin melalui aplikasi manajemen izin.', 'author' => 'Admin', 'time' => '2 days ago'],
                        ['tag' => 'Productivity',  'tagColor' => 'green',  'title' => 'Tips Mengelola Jadwal Kerja',     'desc' => 'Cara mengatur agenda kerja agar lebih efisien dan produktif.',                  'author' => 'Birly', 'time' => '5 days ago'],
                        ['tag' => 'Documentation', 'tagColor' => 'purple', 'title' => 'Panduan Sistem Manajemen Izin',   'desc' => 'Dokumentasi fitur utama yang tersedia di dalam aplikasi.',                     'author' => 'Admin', 'time' => '1 week ago'],
                    ];
                @endphp

                @foreach ($articles as $article)
                    <div wire:key="art-{{ $loop->index }}"
                        class="group rounded-xl border border-zinc-100 bg-zinc-50/50 hover:bg-zinc-50 hover:border-zinc-200 p-4 transition cursor-pointer">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs bg-{{ $article['tagColor'] }}-100 text-{{ $article['tagColor'] }}-600 px-2 py-1 rounded-md">
                                {{ $article['tag'] }}
                            </span>
                            <flux:icon name="arrow-up-right" class="size-4 text-zinc-400 group-hover:text-zinc-600 transition" />
                        </div>
                        <h3 class="font-semibold text-zinc-900 text-sm leading-snug">{{ $article['title'] }}</h3>
                        <p class="text-xs text-zinc-500 mt-2 line-clamp-2">{{ $article['desc'] }}</p>
                        <div class="flex items-center justify-between mt-4 text-xs text-zinc-400">
                            <span>{{ $article['author'] }}</span>
                            <span>{{ $article['time'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Knowledge Hub --}}
        <div class="bg-white rounded-2xl border border-zinc-200 p-5 sm:p-6 shadow-xs space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-xl bg-zinc-100 ring-1 ring-zinc-200 flex items-center justify-center">
                        <flux:icon name="book-open" class="size-5 text-zinc-700" />
                    </div>
                    <div>
                        <flux:heading size="lg" class="text-zinc-900 leading-tight">Knowledge Hub</flux:heading>
                        <flux:description class="text-xs text-zinc-500">Dokumentasi & SOP perusahaan</flux:description>
                    </div>
                </div>
                <a href="{{ route('knowledge') }}" wire:navigate
                    class="inline-flex items-center gap-1 text-xs font-medium text-zinc-500 hover:text-zinc-900">
                    Explore <flux:icon name="arrow-right" class="size-3.5" />
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @php
                    $hub = [
                        ['icon' => 'document-text', 'title' => 'SOP Pengajuan Izin', 'desc' => 'Dokumentasi prosedur izin', 'route' => 'knowledge.documentation'],
                        ['icon' => 'shield-check',  'title' => 'Code of Conduct',    'desc' => 'Company policy',           'route' => 'knowledge.policies'],
                        ['icon' => 'newspaper',     'title' => 'Cara Mengajukan Izin', 'desc' => 'Article',                'route' => 'knowledge.articles'],
                    ];
                @endphp
                @foreach ($hub as $hub_item)
                    <a href="{{ route($hub_item['route']) }}" wire:navigate wire:key="hub-{{ $loop->index }}"
                        class="flex items-center justify-between gap-3 rounded-xl border border-zinc-100 p-3 hover:border-zinc-200 hover:bg-zinc-50 transition">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="size-9 rounded-lg bg-zinc-100 flex items-center justify-center">
                                <flux:icon :name="$hub_item['icon']" class="size-4.5 text-zinc-600" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-zinc-900 truncate">{{ $hub_item['title'] }}</p>
                                <p class="text-xs text-zinc-500 truncate">{{ $hub_item['desc'] }}</p>
                            </div>
                        </div>
                        <flux:icon name="arrow-right" class="size-4 text-zinc-400" />
                    </a>
                @endforeach
            </div>
        </div>

    </div>
</x-layouts.app>
