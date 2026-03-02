<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-6">{{ __('Support Center') }}</flux:heading>
        <flux:separator variant="subtle" />
    </div>
     <flux:heading class="sr-only">{{ __('Knowledge Hub') }}</flux:heading>

     <x-settings.knowledge-layout :heading="__('Documentation')" :subheading="__('Manage documentation for your workspace')">

     </x-settings.knowledge-layout>
</div>
