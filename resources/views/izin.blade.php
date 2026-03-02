<x-layouts.app :title="__('Workspace - Izin - Kelola pengajuan izin')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Izin') }}
        </h2>
    </x-slot>

    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
                    <flex:heading class="font-bold text-xl">Izin</flex:heading>
                    <flux:description class="text-sm text-gray-500">Kelola pengajuan izin Anda</flux:description>
                </div>
                <div class="flex w-full flex-col gap-2 sm:flex-row md:w-auto md:gap-4">
                    <flux:modal.trigger name="form-izin-modal">
                        <flux:button icon="plus-circle" href="" variant="primary" class="cursor-pointer w-full sm:w-auto">
                            Pengajuan Izin
                        </flux:button>
                    </flux:modal.trigger>
                    <a href="{{ route('izin.laporan-pengajuan') }}" class="w-full sm:w-auto">
                        <flux:button icon="document-text" variant="outline" class="cursor-pointer w-full sm:w-auto">
                            Laporan Pengajuan Izin
                        </flux:button>
                    </a>
                </div>
            </div>
           <livewire:izin.izin-search-list />
            <div class="grid grid-cols-1 gap-4 md:grid-cols-1 lg:grid-cols-2">
                <livewire:widget.izin.report-izin lazy/>
                <livewire:widget.izin.report-izin-category lazy/>
            </div>
            <livewire:izin.izin-table lazy/>
        </div>

        <livewire:izin.add-izin-modal />
    </div>

</x-layouts.app>
