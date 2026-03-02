<?php

use Livewire\Volt\Component;

new class extends Component {
    public bool $loading = true;

    public array $items = [];

    public function mount()
    {
        // simulasi load data
        $this->items = [
            [
                'icon' => 'shield-check',
                'title' => 'Safety Center',
                'desc' => 'Get in touch incase of immediate danger.',
                'highlight' => 'Call emergency services.',
                'badge' => 'ALERT',
            ],
            [
                'icon' => 'ticket',
                'title' => 'Ticket Support',
                'desc' => 'Reach out to our dedicated support team.',
                'highlight' => 'Reply within 24hrs.',
                'badge' => 'NEW',
            ],
            [
                'icon' => 'banknotes',
                'title' => 'Dispute and Insurance',
                'desc' => 'Manage disputes and insurance claims.',
                'highlight' => null,
                'badge' => null,
            ],
        ];

        $this->loading = false;
    }

}; ?>


<div>
    <div class="relative mb-6 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-6">{{ __('Support Center') }}</flux:heading>
        <flux:separator variant="subtle" />
    </div>
    <flux:heading class="sr-only">{{ __('Knowledge Hub') }}</flux:heading>

    <x-settings.knowledge-layout :heading="__('Announcements')" :subheading="__('Stay updated with the latest news, system updates, feature releases, and important notices related to the workspace.')" :search="'announcements'">
        <div class="space-y-3 w-full">

            {{-- Skeleton --}}
            @if($loading)
                @for($i=0;$i<3;$i++)
                    <div class="p-4 rounded-xl border bg-white animate-pulse">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-gray-200 rounded-lg"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-4 bg-gray-200 rounded w-1/3"></div>
                                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                @endfor
            @else
                @foreach($items as $item)
                    <div
                        class="group p-4 rounded-xl border bg-white hover:bg-gray-50 hover:shadow-sm transition cursor-pointer flex items-center justify-between w-full">

                        <div class="flex items-start gap-3">

                            {{-- Icon --}}
                            <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-gray-100">
                                <flux:icon :name="$item['icon']" class="w-5 h-5 text-gray-600"/>
                            </div>

                            {{-- Text --}}
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-medium text-gray-900">
                                        {{ $item['title'] }}
                                    </p>

                                    @if($item['badge'])
                                        <span class="text-xs px-2 py-0.5 rounded-full
                                            {{ $item['badge']=='ALERT' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600' }}">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                </div>

                                <p class="text-sm text-gray-500">
                                    {{ $item['desc'] }}
                                </p>
                            </div>

                        </div>

                        {{-- Arrow --}}
                        <flux:icon name="arrow-long-right" class="w-4 h-4 hover:translate-x-5 transition text-gray-400"/>

                    </div>
                @endforeach
            @endif

        </div>

    </x-settings.knowledge-layout>
</div>
