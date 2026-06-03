<?php

use Illuminate\Support\Facades\Http;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {



}; ?>

<div class="space-y-4">
    <livewire:cashadvance.widget.ca-widget />

    <div class="bg-white">
        <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <flux:heading class="font-bold">Recent Transaction</flux:heading>
            <div class="flex gap-4">
                <flux:input icon="magnifying-glass" placeholder="Search"></flux:input>
                <flux:dropdown position="top" align="start">
                    <flux:button icon="calendar" iconClasses="w-4 h-4" class="font-light" variant="outline"></flux:button>
                    {{-- <flux:icon name="calendar" class="w-4 h-4 cursor-pointer" /> --}}
                    <flux:menu>
                        <flux:menu.item disabled>
                            <flux:input label="Start Date" wire:model.live='start_date' type="date" wire:model.live='start_date'></flux:input>
                        </flux:menu.item>
                        <flux:menu.item disabled>
                            <flux:input label="End Date" wire:model.live='end_date' type="date" wire:model.live='end_date'></flux:input>
                        </flux:menu.item>
                        <flux:menu.item>
                            <flux:button class="w-full" size="sm" wire:click="resetDate">Reset</flux:button>
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
                <flux:button icon="arrow-down-tray">Download CSV</flux:button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-[900px] md:min-w-full text-sm text-left text-gray-600 ">
                <thead class="bg-zinc-50 text-xs uppercase shadow-sm text-gray-500 ">
                    <tr>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Payment</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Date & Time</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Method</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Type</th>
                        <th class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Status</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">amount</th>
                        <th class="px-3 py-3 md:px-6 font-normal text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody wire:loading.class="pointer-events-none">
                    @for($i = 0; $i < 10; $i++) <tr>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">BRI</td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Nov 12, 2022 09:50</td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Jago **** 241</td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">Send</td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap">
                            <flux:badge size="sm" color="green">Completed</flux:badge>
                        </td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap text-right">Rp 100.000,-</td>
                        <td class="px-3 py-3 md:px-6 font-normal whitespace-nowrap text-right">Action</td>
                        </tr>
                        @endfor
                </tbody>
            </table>
        </div>
    </div>
</div>
