<x-layouts.app :title="__('Workspace - Izin - Kelola pengajuan izin')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Izin') }}
        </h2>
    </x-slot>

    <div class="bg-zinc-50/50 min-h-screen">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 py-6 space-y-6">

            {{-- HERO HEADER --}}
            <div class="relative overflow-hidden rounded-2xl border border-white/15 bg-linear-to-br from-red-700 via-red-600 to-rose-600 px-6 sm:px-8 py-6 text-white shadow-sm">
                <div class="pointer-events-none absolute inset-0">
                    <div class="absolute -right-20 -top-24 size-72 rounded-full bg-white/10 blur-3xl"></div>
                    <div class="absolute -left-24 -bottom-28 size-72 rounded-full bg-black/15 blur-3xl"></div>
                </div>

                <div class="relative flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                    <div class="min-w-0 space-y-3">
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-xs text-white/90 ring-1 ring-white/20 backdrop-blur">
                            <flux:icon name="document-text" class="size-3.5" />
                            <span>Workspace</span>
                            <span class="opacity-50">/</span>
                            <span>Izin</span>
                        </div>
                        <div class="space-y-1">
                            <flux:heading size="xl" class="text-white leading-tight">
                                Pengajuan Izin
                            </flux:heading>
                            <p class="text-sm text-white/80">
                                Ajukan dan pantau seluruh permohonan izin Anda dalam satu tempat.
                            </p>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-3">
                        @can('izin.create')
                        <flux:modal.trigger name="form-izin-modal">
                            <flux:button icon="plus-circle" variant="primary" class="cursor-pointer w-full sm:w-auto bg-white! text-red-700! hover:bg-white/90!">
                                Ajukan Izin
                            </flux:button>
                        </flux:modal.trigger>
                        @endcan
                        @can('izin.view.all')
                        <a href="{{ route('izin.laporan-pengajuan') }}" class="w-full sm:w-auto">
                            <flux:button icon="document-text" variant="primary" class="cursor-pointer w-full sm:w-auto bg-white! text-red-700! hover:bg-white/90!">
                                Laporan Pengajuan
                            </flux:button>
                        </a>
                        @endcan
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <livewire:izin.widget.report-izin lazy />
                <livewire:izin.widget.report-izin-category lazy />
            </div>
        @if(Auth::user()->isInDepartment('it') && Auth::user()->hasPermission('izin.create'))
            <div x-data="{ tab: 'izin' }" class="space-y-4">
                <div class="flex justify-center">
                    <div class="inline-flex w-full rounded-xl bg-zinc-100 p-1 shadow-sm">
                        <button @click="tab = 'izin'" :class="tab === 'izin'
                ? 'bg-white text-zinc-900 shadow-sm'
                : 'text-zinc-500 hover:text-zinc-700'" class="px-4 w-full py-2 text-sm font-medium rounded-lg transition-all duration-200">
                            Izin
                        </button>

                        <button @click="tab = 'spd'" :class="tab === 'spd'
                ? 'bg-white text-zinc-900 shadow-sm'
                : 'text-zinc-500 hover:text-zinc-700'" class="px-4 w-full py-2 text-sm font-medium rounded-lg transition-all duration-200">
                            SPD
                        </button>

                    </div>
                </div>

                <!-- 🔹 Tab Content -->
                <div>

                    <!-- IZIN -->
                    <div x-show="tab === 'izin'" class="space-y-3" x-transition>
                        <livewire:izin.izin-search-list />
                        <livewire:izin.izin-table lazy />
                    </div>

                    <!-- SPD -->
                    <div x-show="tab === 'spd'" x-transition>
                        <livewire:izin.spd-list lazy />
                    </div>

                </div>
        </div>
        @else
        <livewire:izin.spd-list lazy />
        @endif
    </div>
    <livewire:izin.add-izin-modal />
    </div>
</x-layouts.app>
