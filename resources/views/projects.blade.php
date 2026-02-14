<x-layouts.app>

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>
    <div class="max-h-screen overflow-auto py-4 px-2">
        <div class="p-4 mb-5 ">
            <div class="flex bg-white p-4 rounded-lg shadow-sm mb-10">
                <flux:input icon="magnifying-glass" placeholder="Search..."></flux:input>
            </div>
            <div class="flex justify-between items-center">
                <div class="flex items-end gap-4">
                    <flex:heading class="font-bold text-xl">Workspace</flex:heading>
                    <flux:description>Manage your Projects</flux:description>
                </div>
                <flux:button icon="plus-circle" href="" variant="primary">
                    Add Projects
                </flux:button>
            </div>
        </div>
        <livewire:projects lazy />
    </div>

</x-layouts.app>
