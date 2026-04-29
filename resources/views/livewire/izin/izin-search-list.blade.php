<?php

use Livewire\Volt\Component;

new class extends Component {
    public string $searchQuery = '';

    public function updatedSearchQuery(): void
    {
        $this->dispatch('izinSearchUpdated', $this->searchQuery);
    }
}; ?>

<div>
    <div class="bg-white rounded-2xl border border-zinc-200 p-3">
        <flux:input
            icon="magnifying-glass"
            wire:model.live.debounce.400ms="searchQuery"
            placeholder="Cari berdasarkan alasan izin..."
        >
            <x-slot name="iconTrailing">
                <div wire:loading.flex wire:target="searchQuery" class="items-center pr-2">
                    <div class="size-3.5 border-2 border-red-600 border-t-transparent rounded-full animate-spin"></div>
                </div>
            </x-slot>
        </flux:input>
    </div>
</div>
