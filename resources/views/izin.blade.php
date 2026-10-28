<x-layouts.app :title="__('Workspace - Izin - Kelola pengajuan izin')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Izin') }}
        </h2>
    </x-slot>

    <div class="bg-zinc-50/50 min-h-screen">
        <div class="max-w-screen-2xl mx-auto p-0 md:p-5 space-y-4 md:space-y-6">

            {{-- HERO HEADER --}}
            <header class="border-b border-zinc-200 pb-5 pt-2 dark:border-zinc-800 sm:pb-6">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between sm:gap-8">
                    <div class="min-w-0">
                        <div class="mb-2 flex items-center gap-2 text-[11px] font-medium text-red-800 dark:text-red-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-red-800 dark:bg-red-400"></span>
                            <span>Izin workspace</span>
                        </div>
                        <flux:heading size="xl" class="text-2xl font-semibold tracking-tight text-zinc-950 sm:text-3xl dark:text-white">
                            Pengajuan Izin
                        </flux:heading>
                        <p class="mt-1.5 max-w-xl text-sm leading-5 text-zinc-600 dark:text-zinc-300">
                            Ajukan dan pantau seluruh permohonan izin Anda dalam satu tempat.
                        </p>
                    </div>

                    <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        @can('izin.create')
                        <flux:modal.trigger name="form-izin-modal" class="w-full sm:w-auto">
                            <flux:button
                                icon="plus-circle"
                                variant="primary"
                                class="w-full cursor-pointer bg-red-800! text-white! hover:bg-red-900! sm:w-auto dark:bg-red-700! dark:hover:bg-red-800!"
                            >
                                Ajukan Izin
                            </flux:button>
                        </flux:modal.trigger>
                        @endcan
                        @can('izin.view.all')
                        <a href="{{ route('izin.laporan-pengajuan') }}" class="w-full sm:w-auto">
                            <flux:button
                                icon="document-text"
                                variant="ghost"
                                class="w-full cursor-pointer text-zinc-700! hover:bg-zinc-100! sm:w-auto dark:text-zinc-200! dark:hover:bg-zinc-800!"
                            >
                                Laporan Pengajuan
                            </flux:button>
                        </a>
                        @endcan
                    </div>
                </div>
            </header>
            <div class="grid grid-cols-1 gap-4 md:gap-5 lg:grid-cols-2">
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
