<x-layouts.app :title="__('Workspace - Projects - Kelola proyek')">

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 ">
            <div class="flex bg-white p-4 rounded-lg shadow-sm mb-6 md:mb-10">
                <flux:input icon="magnifying-glass" placeholder="Search..." class="w-full"></flux:input>
            </div>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
                    <flex:heading class="font-bold text-xl">Workspace</flex:heading>
                    <flux:description>Manage your Projects</flux:description>
                </div>
                <flux:button icon="plus-circle" href="" variant="primary" class="w-full md:w-auto">
                    Add Projects
                </flux:button>
            </div>
        </div>
        <livewire:project.project-cards lazy />
    </div>

</x-layouts.app>
