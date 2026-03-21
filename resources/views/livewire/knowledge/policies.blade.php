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

    <x-settings.knowledge-layout :heading="__('Company Policies & Regulations')" :subheading="__('Access and review the official policies and workplace regulations applicable to all employees.')">
        <div class=" bg-white rounded-lg shadow-sm ">

            <!-- Policy 1 -->
            <details class="group p-5 border-b">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    <div class="space-y-1">
                        <flux:heading size="base" class="font-semibold text-gray-800">
                            Code of Conduct
                        </flux:heading>
                        <flux:description>
                            Pedoman perilaku dan etika kerja bagi seluruh karyawan.
                        </flux:description>
                        <p class="text-xs text-gray-400">
                            Last updated: 12 March 2026
                        </p>
                    </div>

                    <span class=" transition group-open:rotate-180">
                        <flux:icon name="chevron-down" variant="solid" class="w-4 h-4 hover:translate-x-5 transition text-gray-900" />
                    </span>
                </summary>

                <div class="mt-4 text-sm text-gray-700 space-y-3">
                    <p>
                        Semua karyawan diwajibkan menjaga profesionalisme dan menghormati sesama rekan kerja.
                    </p>

                    <ul class="list-disc ml-5 space-y-1">
                        <li>Menjaga komunikasi yang profesional</li>
                        <li>Menghormati keberagaman di tempat kerja</li>
                        <li>Menghindari konflik kepentingan</li>
                    </ul>

                </div>
            </details>


            <!-- Policy 2 -->
            <details class="group p-5">
                <summary class="flex cursor-pointer list-none items-center justify-between">
                    <div class="space-y-1">
                        <flux:heading size="base" class="font-semibold text-gray-800">
                            Work From Home Policy
                        </flux:heading>
                        <flux:description>
                            Aturan dan ketentuan bekerja dari rumah.
                        </flux:description>
                        <p class="text-xs text-gray-400">
                            Last updated: 10 March 2026
                        </p>
                    </div>

                    <span class="text-gray-400 transition group-open:rotate-180">
                        <flux:icon name="chevron-down" variant="solid" class="w-4 h-4 hover:translate-x-5 transition text-gray-900" />
                    </span>
                </summary>

                <div class="mt-4 text-sm text-gray-700 space-y-3">
                    <p>
                        Karyawan dapat mengajukan WFH dengan persetujuan atasan langsung.
                    </p>

                    <ul class="list-disc ml-5 space-y-1">
                        <li>Jam kerja tetap mengikuti jam kerja kantor</li>
                        <li>Wajib online selama jam kerja</li>
                        <li>Melaporkan progress pekerjaan harian</li>
                    </ul>


                </div>
            </details>
        </div>
    </x-settings.knowledge-layout>
</div>

</div>
