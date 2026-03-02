<x-layouts.app :title="__('User Management - HMA Workspace')">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('User Management') }}
        </h2>
    </x-slot>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <livewire:users.user-datatables lazy />
        </div>
    </div>

</x-layouts.app>
