<?php

use Livewire\Volt\Component;

new class extends Component {
      public $searchQuery = '';
    public function updatedSearchQuery(){
        sleep(1); // Simulate delay for loading state
            $this->dispatch('izinSearchUpdated', $this->searchQuery);
    }
}; ?>

<div>
    <div class="max-h-screen overflow-auto py-4 px-2">
        <div class="py-4 mb-5 space-y-4">
            <div class="flex justify-between items-center">
                <div class="flex items-end gap-4">
                    <flex:heading class="font-bold text-xl">Laporan Pengajuan Izin</flex:heading>
                    <flux:description class="text-sm text-gray-500">Ringkasan status pengajuan izin</flux:description>
                </div>
            </div>
            <div class="flex flex-col bg-white p-4 rounded-lg shadow-sm space-y-2">

                <flux:input icon="magnifying-glass" wire:model.live.debounce.400ms="searchQuery" placeholder="Search name..." />

                <div wire:loading.delay wire:target="searchQuery" class="text-sm text-gray-400 animate-pulse">
                </div>
            </div>
            <livewire:izin.izin-table :laporan="true" lazy/>
        </div>
    </div>
</div>
