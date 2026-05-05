<x-layouts.app :title="__('Dashboard')">
    <div class="flex h-screen overflow-auto w-full flex-col gap-4 rounded-xl py-6">

        <div class="flex flex-col gap-4 md:flex-row">

            <!-- Kolom Kiri -->
            <div class="w-full md:basis-3/4 space-y-4">

                <!-- Welcome -->
                <div class="relative w-full overflow-hidden rounded-xl">
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

                <!-- Announcement -->
                <livewire:widget.dashboard.announcement />


               <livewire:widget.dashboard.task-in-progress>


                <!-- Articles -->
                <div class="bg-white rounded-xl p-6 shadow-sm space-y-5">

                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Latest Articles</h3>

                        <flux:button size="sm" variant="ghost">
                            View all
                        </flux:button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

                        <!-- Article Card -->
                        <div class="group bg-gray-50 hover:bg-gray-100 rounded-xl p-4 transition cursor-pointer">

                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded-md">
                                    Guide
                                </span>

                                <flux:icon name="arrow-up-right" class="w-4 h-4 text-gray-400 group-hover:text-gray-600" />
                            </div>

                            <h3 class="font-semibold text-gray-900 text-sm leading-snug">
                                Cara Mengajukan Izin di Sistem
                            </h3>

                            <p class="text-xs text-gray-500 mt-2 line-clamp-2">
                                Panduan lengkap untuk membuat permohonan izin melalui aplikasi manajemen izin.
                            </p>

                            <div class="flex items-center justify-between mt-4 text-xs text-gray-400">
                                <span>Admin</span>
                                <span>2 days ago</span>
                            </div>

                        </div>


                        <!-- Article Card -->
                        <div class="group bg-gray-50 hover:bg-gray-100 rounded-xl p-4 transition cursor-pointer">

                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs bg-green-100 text-green-600 px-2 py-1 rounded-md">
                                    Productivity
                                </span>

                                <flux:icon name="arrow-up-right" class="w-4 h-4 text-gray-400 group-hover:text-gray-600" />
                            </div>

                            <h3 class="font-semibold text-gray-900 text-sm leading-snug">
                                Tips Mengelola Jadwal Kerja
                            </h3>

                            <p class="text-xs text-gray-500 mt-2 line-clamp-2">
                                Cara mengatur agenda kerja agar lebih efisien dan produktif.
                            </p>

                            <div class="flex items-center justify-between mt-4 text-xs text-gray-400">
                                <span>Birly</span>
                                <span>5 days ago</span>
                            </div>

                        </div>


                        <!-- Article Card -->
                        <div class="group bg-gray-50 hover:bg-gray-100 rounded-xl p-4 transition cursor-pointer">

                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs bg-purple-100 text-purple-600 px-2 py-1 rounded-md">
                                    Documentation
                                </span>

                                <flux:icon name="arrow-up-right" class="w-4 h-4 text-gray-400 group-hover:text-gray-600" />
                            </div>

                            <h3 class="font-semibold text-gray-900 text-sm leading-snug">
                                Panduan Sistem Manajemen Izin
                            </h3>

                            <p class="text-xs text-gray-500 mt-2 line-clamp-2">
                                Dokumentasi fitur utama yang tersedia di dalam aplikasi.
                            </p>

                            <div class="flex items-center justify-between mt-4 text-xs text-gray-400">
                                <span>Admin</span>
                                <span>1 week ago</span>
                            </div>

                        </div>

                    </div>

                </div>

                {{-- Knowledge Hub --}}
                <div class="bg-white rounded-xl p-6 shadow-sm space-y-4">

                    <div class="flex items-center justify-between">
                        <flux:heading size="base" class="font-semibold">
                            Knowledge Hub
                        </flux:heading>

                        <flux:button size="sm" variant="ghost" href="#">
                            Explore
                        </flux:button>
                    </div>

                    <div class="space-y-3">

                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <flux:icon name="document-text" class="w-5 h-5 text-gray-500" />

                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        SOP Pengajuan Izin
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Dokumentasi prosedur izin
                                    </p>
                                </div>
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <flux:icon name="shield-check" class="w-5 h-5 text-gray-500" />

                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        Code of Conduct
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Company policy
                                    </p>
                                </div>
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                        </div>

                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 cursor-pointer">
                            <div class="flex items-center gap-3">
                                <flux:icon name="newspaper" class="w-5 h-5 text-gray-500" />

                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        Cara Mengajukan Izin
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        Article
                                    </p>
                                </div>
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                        </div>

                    </div>

                </div>


            </div>

            <!-- Kolom Kanan / Sidebar -->
            <div class="w-full md:basis-1/4 space-y-4">

                <!-- Upcoming Events -->
                <div class="bg-white rounded-xl p-5 shadow-sm space-y-4">

                    <h3 class="font-semibold text-gray-800">Upcoming Events</h3>

                    <div class="space-y-3">

                        <!-- Event -->
                        <div class="flex items-start gap-3">

                            <div class="bg-blue-100 text-blue-600 text-xs font-semibold px-2 py-1 rounded-md">
                                Mar 25
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Eid al-Fitr Holiday
                                </p>
                                <p class="text-xs text-gray-500">
                                    Office closed for holiday
                                </p>
                            </div>

                        </div>

                        <!-- Event -->
                        <div class="flex items-start gap-3">

                            <div class="bg-purple-100 text-purple-600 text-xs font-semibold px-2 py-1 rounded-md">
                                Mar 28
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    Team Weekly Meeting
                                </p>
                                <p class="text-xs text-gray-500">
                                    10:00 AM
                                </p>
                            </div>

                        </div>

                        <!-- Event -->
                        <div class="flex items-start gap-3">

                            <div class="bg-amber-100 text-amber-600 text-xs font-semibold px-2 py-1 rounded-md">
                                Apr 02
                            </div>

                            <div>
                                <p class="text-sm font-medium text-gray-900">
                                    System Maintenance
                                </p>
                                <p class="text-xs text-gray-500">
                                    22:00 WIB
                                </p>
                            </div>

                        </div>

                    </div>

                </div>

                <!-- Calendar -->
                <div class="bg-white rounded-xl p-4 shadow-sm">
                    <livewire:widget.dashboard.calendar />
                </div>



                <!-- Today's Schedule -->
                <div class="bg-white rounded-xl p-5 shadow-sm space-y-4">

                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800">Today's Schedule</h3>

                        <flux:button size="sm" variant="ghost">
                            View all
                        </flux:button>
                    </div>

                    <div class="space-y-4">

                        <!-- Event -->
                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-2.5 h-2.5 bg-blue-500 rounded-full"></div>
                                <div class="flex-1 w-px bg-gray-200"></div>
                            </div>

                            <div class="flex-1 pb-4">
                                <p class="text-xs text-gray-500">10:00 AM</p>
                                <p class="text-sm font-medium text-gray-900">
                                    Meeting Project A
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-2.5 h-2.5 bg-green-500 rounded-full"></div>
                                <div class="flex-1 w-px bg-gray-200"></div>
                            </div>

                            <div class="flex-1 pb-4">
                                <p class="text-xs text-gray-500">13:00 PM</p>
                                <p class="text-sm font-medium text-gray-900">
                                    Review Dokumen SOP
                                </p>
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex flex-col items-center">
                                <div class="w-2.5 h-2.5 bg-purple-500 rounded-full"></div>
                            </div>

                            <div>
                                <p class="text-xs text-gray-500">15:00 PM</p>
                                <p class="text-sm font-medium text-gray-900">
                                    Call dengan Client
                                </p>
                            </div>
                        </div>

                    </div>

                </div>

            </div>
        </div>
    </div>
</x-layouts.app>
