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

    <x-settings.knowledge-layout :heading="__('Articles')" :subheading="__('Browse articles and insights shared by users across the platform.')" :search="'articles'" :button="''">
        <div class="space-y-8 w-full">
            {{-- Featured Article --}}
            <div class="rounded-xl border border-gray-100 bg-white p-6 hover:shadow-sm transition cursor-pointer">
                <div class="flex items-start justify-between gap-4">

                    <div class="space-y-2">
                        <flux:badge color="blue" class="text-xs font-medium px-2 py-1 rounded-full">
                            Featured
                        </flux:badge>

                        <flux:heading size="lg" class="text-gray-900">
                            Getting Started with the Support Platform
                        </flux:heading>

                        <p class="text-sm text-gray-500 max-w-xl">
                            Learn how to use the support platform effectively, manage requests,
                            publish articles, and collaborate with your team.
                        </p>

                        <div class="flex items-center gap-3 text-xs text-gray-400">
                            <span>By John Doe</span>
                            <span>•</span>
                            <span>5 min read</span>
                            <span>•</span>
                            <span>2 days ago</span>
                        </div>
                    </div>

                    <flux:icon name="arrow-long-right" class="w-5 h-5 text-gray-400" />
                </div>
            </div>


            {{-- Articles Grid --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

                {{-- Article Card --}}
                <div class="group rounded-xl border border-gray-100 bg-white p-5 hover:shadow-sm hover:bg-gray-50 transition cursor-pointer">

                    <div class="space-y-3">

                        <flux:heading size="base" class="font-semibold text-gray-900">
                            How to Submit a Support Ticket
                        </flux:heading>

                        <p class="text-sm text-gray-500 line-clamp-3">
                            A quick guide on how users can submit support tickets,
                            track requests, and communicate with the support team.
                        </p>

                        <div class="flex items-center justify-between pt-2">

                            <div class="text-xs text-gray-400">
                                Jane Smith • 4 min read
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400 transition group-hover:translate-x-1" />
                        </div>

                    </div>

                </div>


                {{-- Article Card --}}
                <div class="group rounded-xl border border-gray-100 bg-white p-5 hover:shadow-sm hover:bg-gray-50 transition cursor-pointer">

                    <div class="space-y-3">

                        <flux:heading size="base" class="font-semibold text-gray-900">
                            Managing Your Workspace Notifications
                        </flux:heading>

                        <p class="text-sm text-gray-500 line-clamp-3">
                            Configure notification settings to ensure you receive
                            important updates without unnecessary distractions.
                        </p>

                        <div class="flex items-center justify-between pt-2">

                            <div class="text-xs text-gray-400">
                                Alex Carter • 3 min read
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400 transition group-hover:translate-x-1" />
                        </div>
                    </div>
                </div>
                <div class="group rounded-xl border border-gray-100 bg-white p-5 hover:shadow-sm hover:bg-gray-50 transition cursor-pointer">
                    <div class="space-y-3">
                        <flux:heading size="base" class="font-semibold text-gray-900">
                            Managing Your Workspace Notifications
                        </flux:heading>

                        <p class="text-sm text-gray-500 line-clamp-3">
                            Configure notification settings to ensure you receive
                            important updates without unnecessary distractions.
                        </p>

                        <div class="flex items-center justify-between pt-2">

                            <div class="text-xs text-gray-400">
                                Alex Carter • 3 min read
                            </div>

                            <flux:icon name="arrow-right" class="w-4 h-4 text-gray-400 transition group-hover:translate-x-1" />
                        </div>
                    </div>
                </div>
            </div>


            {{-- Load More --}}
            <div class="flex justify-center">
                <button class="px-4 py-2 text-sm rounded-lg border border-gray-200 hover:bg-gray-50 transition">
                    Load more articles
                </button>
            </div>

        </div>
    </x-settings.knowledge-layout>
</div>
