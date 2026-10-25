<x-layouts.app :title="__('Workspace - Projects - Kelola proyek')">

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Projects') }}
        </h2>
    </x-slot>
    <div class="max-h-screen overflow-auto p-5">
        <livewire:project.project-cards lazy />
    </div>

</x-layouts.app>
