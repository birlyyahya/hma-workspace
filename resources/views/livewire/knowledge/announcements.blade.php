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
    <div class="relative mb-8 w-full py-6 px-2">
        <flux:heading size="xl" level="1" class="mb-4">
            {{ __('Support Center') }}
        </flux:heading>
        <flux:separator variant="subtle" />
    </div>

    <flux:heading class="sr-only">{{ __('Knowledge Hub') }}</flux:heading>

    <x-settings.knowledge-layout
        :heading="__('Announcements')"
        :subheading="__('Stay updated with the latest news, system updates, feature releases, and important notices from the organization.')"
        :search="'announcements'"
    >

        <div class="space-y-4 w-full">

            {{-- Skeleton --}}
            @if($loading)
                @for($i=0;$i<3;$i++)
                    <div class="flex items-center gap-4 p-5 rounded-xl border border-gray-100 bg-white animate-pulse">
                        <div class="w-11 h-11 rounded-lg bg-gray-200"></div>

                        <div class="flex-1 space-y-2">
                            <div class="h-4 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                        </div>
                    </div>
                @endfor
            @else

                @foreach($items as $item)
                    <div
                        class="group flex items-center justify-between gap-4 p-5 rounded-xl border border-gray-100 bg-white hover:bg-gray-50 hover:shadow-sm transition-all cursor-pointer">

                        {{-- Left content --}}
                        <div class="flex items-start gap-4">

                            {{-- Icon --}}
                            <div class="flex items-center justify-center w-11 h-11 rounded-lg bg-gray-100 group-hover:bg-gray-200 transition">
                                <flux:icon :name="$item['icon']" class="w-5 h-5 text-gray-600"/>
                            </div>

                            {{-- Text --}}
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">

                                    <p class="text-sm font-semibold text-gray-900">
                                        {{ $item['title'] }}
                                    </p>

                                    @if($item['badge'])
                                        <span
                                            class="text-xs font-medium px-2 py-0.5 rounded-full
                                            {{ $item['badge']=='ALERT'
                                                ? 'bg-red-100 text-red-600'
                                                : 'bg-blue-100 text-blue-600'
                                            }}">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif

                                </div>

                                <p class="text-sm text-gray-500 leading-relaxed">
                                    {{ $item['desc'] }}
                                </p>

                                @if($item['highlight'])
                                    <p class="text-xs text-gray-400">
                                        {{ $item['highlight'] }}
                                    </p>
                                @endif
                            </div>

                        </div>

                        {{-- Arrow --}}
                        <flux:icon
                            name="arrow-long-right"
                            class="w-5 h-5 text-gray-400 transition-transform duration-200 group-hover:translate-x-1"
                        />

                    </div>
                @endforeach

            @endif
        </div>

    </x-settings.knowledge-layout>
</div>
