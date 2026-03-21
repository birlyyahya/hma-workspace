<?php

use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    public $project;
    public $spectech;
    public $documents;
    public $loadingDocuments = false;
    public $loadingSpectech = false;

    // #[On('documentLoad')]
    // public function documentLoad($data){
    //     $this->documents = $data;
    //     $this->loadingDocuments = false;
    // }


}

?>

<div>
    <div class="bg-zinc-50 min-h-screen">
        <div class="grid grid-cols-12 gap-6">

            <!-- LEFT COLUMN -->
            <div class="col-span-12 lg:col-span-8">
                <div class="bg-white rounded-2xl p-8 shadow-sm space-y-8">

                    <!-- HEADER -->
                    <div class="flex justify-between items-start">
                        <div>
                            <flux:text class="text-zinc-500">Project Information</flux:text>
                            <flux:heading class="!text-xl font-semibold mt-2">
                                {{ $project['company_name'] }} ({{ $project['code'] }})
                            </flux:heading>
                        </div>

                        <div class="flex gap-2">
                            <flux:button size="sm" variant="outline" icon="plus">
                                Share
                            </flux:button>
                            <flux:button size="sm" variant="outline" icon="ellipsis-vertical" />
                        </div>
                    </div>

                    <!-- META INFO -->
                    <div class="space-y-5 text-sm">

                        <!-- Status -->
                        <div class="flex items-center gap-4">
                            <flux:icon name="building-office" class="w-4 h-4 text-zinc-400" />
                            <span class="text-zinc-600 w-24">Client</span>
                            <flux:badge :color="
                            match($project['status']) {
                                'ON PROGRESS' => 'blue',
                                'WAITING' => 'yellow',
                                'CLOSED' => 'red',
                                default => 'gray'
                            }
                            " class="px-3 py-1 text-xs !rounded-full">
                                {{ $project['client'] }}
                            </flux:badge>
                        </div>

                        <!-- Due Date -->
                        <div class="flex items-center gap-4">
                            <flux:icon name="clock" class="w-4 h-4 text-zinc-400" />
                            <span class="text-zinc-600 w-24">Date</span>
                            <span class="px-3 py-1 text-xs border rounded-full">
                                {{ Carbon::parse($project['start_date'])->locale('id')->translatedFormat('D, d F Y').' - '.Carbon::parse($project['end_date'])->locale('id')->translatedFormat('D, d F Y') }}
                            </span>
                        </div>

                        <!-- Due Date -->
                        <div class="flex items-center gap-4">
                            <flux:icon name="user" class="w-4 h-4 text-zinc-400" />
                            <span class="text-zinc-600 w-24">Director</span>
                            <div class="flex items-center gap-2">
                                <flux:avatar name="{{ $project['company_director_name'] }}" size="xs" color="red" />
                                <div>
                                    <span class="px-3 py-1 text-xs border border-red-500 rounded-lg">
                                        {{ $project['company_director_name'] }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div class="flex items-center gap-4">
                            <flux:icon name="users" class="w-4 h-4 text-zinc-400" />
                            <span class="text-zinc-600 w-24">PPK</span>
                            <div class="flex gap-2">
                                <div class="flex items-center gap-2">
                                    <flux:avatar name="{{ $project['ppk'] }}" size="xs" color="yellow" />
                                    <div>
                                        <span class="px-3 py-1 text-xs border border-yellow-500 rounded-lg">
                                            {{ $project['ppk'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Nilai --}}
                        <div class="flex items-center gap-4">
                            <flux:icon name="banknotes" class="w-4 h-4 text-zinc-400" />
                            <span class="text-zinc-600 w-24">Nilai</span>
                            <span class="px-3 py-1 text-xs border rounded-full">Rp {{ number_format($project['value'], 2, ',', '.') }}</span>
                        </div>
                    </div>

                    <!-- DESCRIPTION -->
                    <div>
                        <flux:heading size="sm" class="mb-3">Address</flux:heading>
                        <div class="p-4 rounded-xl text-sm text-zinc-600 border">
                            {{ $project['company_address'] }}
                        </div>
                    </div>

                    <!-- ATTACHMENT -->
                    <div>
                        <flux:heading size="sm" class="mb-4">Attachment ({{ $this->loadingDocuments ? '0' : count(collect($this->documents)->take(3) ) }})</flux:heading>

                        <div class="space-y-5">
                            @if(!$this->loadingDocuments)
                            @forelse(collect($this->documents)->take(3)  as $doc)
                            <div class="flex justify-between items-center rounded-xl">
                                <div class="flex items-center gap-4">
                                    <flux:avatar name="{{ $doc['title'] }}" color="red" class="w-10 h-10 rounded-lg"></flux:avatar>
                                    <div>
                                        <p class="text-sm font-medium">{{ $doc['title'] }}</p>
                                        <p class="text-xs text-zinc-500">{{ Carbon::parse($doc['created_at'])->locale('id')->translatedFormat('l, d F Y') }}</p>
                                    </div>
                                </div>
                                <div class="flex text-sm text-zinc-500">
                                    <flux:button variant="ghost" icon="eye" iconVariant="outline" class="hover:text-zinc-800" size="sm">View</flux:button>
                                    <flux:button variant="ghost" icon="arrow-down-on-square" iconVariant="outline" class="hover:text-zinc-800 cursor-pointer" size="sm">Download</flux:button>
                                </div>
                            </div>
                            @empty
                            <p class="text-sm font-normal text-gray-500">No attachment</p>
                            @endforelse
                            @else
                            <div class="flex justify-between items-center p-4 border rounded-xl animate-pulse">
                                <div class="flex items-center gap-4">
                                    <!-- Icon Skeleton -->
                                    <div class="w-10 h-10 bg-zinc-200 rounded-lg"></div>

                                    <div class="space-y-2">
                                        <!-- Title Skeleton -->
                                        <div class="w-32 h-4 bg-zinc-200 rounded"></div>

                                        <!-- Date Skeleton -->
                                        <div class="w-24 h-3 bg-zinc-100 rounded"></div>
                                    </div>
                                </div>
                                <!-- Button Skeleton -->
                                <div class="flex gap-3">
                                    <div class="w-16 h-8 bg-zinc-200 rounded-lg"></div>
                                    <div class="w-20 h-8 bg-zinc-200 rounded-lg"></div>
                                </div>
                            </div>
                            @endif

                        </div>
                    </div>

                    <!-- PROJECT GOALS -->
                    <div class="space-y-4">
                        <div class="w-full">
                            <div class="flex justify-between mb-1">
                                <flux:heading size="sm" class="!mb-2">Project Spectech ({{  count($this->spectech) }})</flux:heading>
                                <span class="text-sm font-medium text-body">{{ $this->project['progress'] }}%</span>
                            </div>
                            <div class="w-full bg-zinc-200 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" style="width: {{ $this->project['progress'] }}%"></div>
                            </div>
                        </div>
                        <div class="space-y-3 text-sm max-h-46 overflow-auto">
                            @forelse($this->spectech as $spectech)
                            <div class="p-4 border rounded-lg">
                                <flux:heading size="xs">
                                    {{ $loop->iteration }}. {{ $spectech['name'] }}.
                                </flux:heading>
                                <flux:text class="mt-2 !text-sm">
                                    {{ $spectech['note'] }}
                                </flux:text>
                            </div>
                            @empty
                            <p class="text-sm font-normal text-gray-500">No spectech</p>
                            @endforelse
                        </div>
                    </div>

                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="col-span-12 lg:col-span-4 space-y-6">

                <!-- TEAM SUPPORT -->
                <div class="bg-white rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex justify-between items-center">
                        <flux:heading size="sm">Team Support</flux:heading>
                        <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                    </div>

                    <div class="space-y-4 text-sm max-h-48 overflow-auto">
                        @forelse ($this->project['support_teams'] as $support)
                        <div class="flex items-start gap-3">
                            <flux:avatar name="{{ $support }}" size="sm" color="auto" circle color:seed="{{ $support }}"/>
                            <div>
                                <p class="font-medium">{{ $support }}</p>
                                <p class="text-zinc-500 text-xs">Tim Pendukung PPK</p>
                            </div>
                        </div>
                        @empty
                        <flux:text>No support teams</flux:text>
                        @endforelse
                    </div>
                    <flux:separator />
                    <div class="space-y-4 text-sm">
                         @forelse ($this->project['support_team_internals'] as $support)
                        <div class="flex items-start gap-3">
                            <flux:avatar name="{{ $support['user_name'] }}" color="auto" size="sm" circle color:seed="{{ $support['id'] }}" />
                            <div>
                                <p class="font-medium">{{ $support['user_name'] }}</p>
                                <p class="text-zinc-500 text-xs">Internal Support</p>
                            </div>
                        </div>
                        @empty
                        <flux:text>No support teams</flux:text>
                        @endforelse
                    </div>
                </div>

                <!-- RECENT ACTIVITY -->
                <div class="bg-white rounded-2xl p-6 shadow-sm space-y-5">
                    <div class="flex justify-between items-center">
                        <flux:heading size="sm">Recent Activity</flux:heading>
                        <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                    </div>

                    <div class="space-y-4 text-sm">
                        <div>
                            <p class="font-medium">Leslie Alexander</p>
                            <p class="text-zinc-500 text-xs">You have a new comment from @asifmahmud</p>
                        </div>

                        <div>
                            <p class="font-medium">Jenny Wilson</p>
                            <p class="text-zinc-500 text-xs">A new system update is available.</p>
                        </div>

                        <div>
                            <p class="font-medium">Robert Fox</p>
                            <p class="text-zinc-500 text-xs">Your password was changed successfully.</p>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
