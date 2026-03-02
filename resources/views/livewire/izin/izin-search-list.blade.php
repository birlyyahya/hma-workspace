<?php

use Livewire\Volt\Component;

new class extends Component {

    public $searchQuery = '';
    public function updatedSearchQuery(){
        $this->dispatch('izinSearchUpdated', $this->searchQuery);
    }

}; ?>

<div>
    <div class="flex flex-col bg-white p-4 rounded-lg shadow-sm space-y-2">

        <flux:input icon="magnifying-glass" wire:model.live.debounce.400ms="searchQuery" placeholder="Search..." />

        <div wire:loading.delay wire:target="searchQuery" class="text-sm text-gray-400 animate-pulse">
        </div>
    </div>
</div>
