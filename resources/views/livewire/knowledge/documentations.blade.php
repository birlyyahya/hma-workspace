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

    <x-settings.knowledge-layout :heading="__('Workplace Documentation')" :subheading="__('Access operational procedures, guidelines, and internal documentation used within the organization.')" :search="'documentation'" :button="__('Documentation')">

        <div class="space-y-8 w-full">

            {{-- Documentation Categories --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 border rounded-xl bg-white hover:bg-gray-50 cursor-pointer transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                            <flux:icon name="users" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">HR Procedures</p>
                            <p class="text-xs text-gray-500">Employee related SOP</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 border rounded-xl bg-white hover:bg-gray-50 cursor-pointer transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                            <flux:icon name="computer-desktop" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">IT Guidelines</p>
                            <p class="text-xs text-gray-500">Technology procedures</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 border rounded-xl bg-white hover:bg-gray-50 cursor-pointer transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                            <flux:icon name="clipboard-document-list" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Operations</p>
                            <p class="text-xs text-gray-500">Daily workflow SOP</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 border rounded-xl bg-white hover:bg-gray-50 cursor-pointer transition">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                            <flux:icon name="shield-check" class="w-5 h-5 text-gray-600" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Compliance</p>
                            <p class="text-xs text-gray-500">Company regulations</p>
                        </div>
                    </div>
                </div>

            </div>


            {{-- Documentation List --}}
            <div class="space-y-3">

                <div class="flex items-center justify-between p-4 border rounded-xl bg-white hover:bg-gray-50 transition cursor-pointer">
                    <div class="flex items-center gap-3">
                        @if(Auth::user()->role->level > 60)
                        <flux:button icon="x-mark" variant="ghost" size="xs" name="x-mark" class="w-5 h-5 text-red-500 cursor-pointer" />
                        @endif
                        <a class="flex items-center gap-3">
                            <flux:icon name="document-text" class="w-5 h-5 text-gray-500" />
                            <div>
                                <p class="text-sm font-medium text-gray-900">Employee Onboarding Procedure</p>
                                <p class="text-xs text-gray-500">HR Procedures</p>
                            </div>
                        </a>
                    </div>

                    <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                </div>

                <div class="flex items-center justify-between p-4 border rounded-xl bg-white hover:bg-gray-50 transition cursor-pointer">
                    <div class="flex items-center gap-3">
                        <flux:icon name="document-text" class="w-5 h-5 text-gray-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-900">IT Equipment Request Procedure</p>
                            <p class="text-xs text-gray-500">IT Guidelines</p>
                        </div>
                    </div>

                    <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                </div>

                <div class="flex items-center justify-between p-4 border rounded-xl bg-white hover:bg-gray-50 transition cursor-pointer">
                    <div class="flex items-center gap-3">
                        <flux:icon name="document-text" class="w-5 h-5 text-gray-500" />
                        <div>
                            <p class="text-sm font-medium text-gray-900">Daily Operational Reporting</p>
                            <p class="text-xs text-gray-500">Operations</p>
                        </div>
                    </div>

                    <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400" />
                </div>

            </div>

        </div>

    </x-settings.knowledge-layout>
</div>
