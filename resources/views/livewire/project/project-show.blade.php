<?php

use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Lazy] class extends Component {
    public $id;
    public $project;
    public $document;

    #[On('projectLoad')]
    public function mount()
    {
        $this->project = Cache::remember(
            'project_data_show_' . $this->id,
            now()->addMinutes(30),
            function () {
                $response = Http::get(env('API_PROJECT') . 'projects/' . $this->id)->json();

                 if ($response['status'] === 200) {
                    return collect($response['data'])->first(); // langsung ambil object
                }

                return null;
            }
        );
        $this->documentLoad();
    }



    public function placeholder(){
        return view('components.placeholder.ph_project_show');
    }

    public function documentLoad()
    {
        $this->document = Cache::remember(
            'document_project_'.$this->project['id'],
            now()->addMinutes(30),
            function () {
                $response = Http::get(
                    env('API_PROJECT').'admin-docs/search?project_id='.$this->project['id']
                )->json();

                return collect($response['data'] ?? [])
                    ->values()
                    ->toArray();
            }
        );
        $this->dispatch('documentLoad', $this->document);
    }

}; ?>

<div>
    <div class="max-h-screen overflow-auto px-2 py-4">
        <div class="py-4 mb-5 space-y-4">
            <div class="flex flex-col gap-8 md:flex-row md:items-start md:justify-between">
                <div class="flex flex-col items-start gap-1 md:flex-row md:items-end md:gap-4">
                    <flex:heading class="font-bold text-xl leading-10">{{ $this->project['name'] }}
                        <flux:badge :color="
                            match($this->project['status']){
                                'ON PROGRESS' => 'blue',
                                'WAITING' => 'yellow',
                                'CLOSED' => 'red',
                                default => 'gray'
                            }
                        " icon="lock-open" class="rounded-xl ml-4 !px-4">{{ $this->project['status'] }}</flux:badge>
                    </flex:heading>
                </div>
                <div class="flex items-center gap-2">
                    <flux:avatar.group class="md:mt-2">
                        @foreach ($this->project['support_teams'] as $user)
                        @if ($loop->index < 4) <flux:tooltip content="{{ $user}}">
                            <flux:avatar circle name="{{ $user }}" color="auto" color:seed="{{ $user }}" size="sm" />
                            </flux:tooltip>
                            @endif
                            @endforeach

                            @if (count($this->project['support_teams']) > 4)
                            <flux:tooltip content="{{ join(', ', array_map(fn ($user) => $user, $this->project['support_teams'])) }}">
                                <flux:avatar circle size="sm">
                                    +{{ count($this->project['support_teams']) - 2 }}
                                </flux:avatar>
                            </flux:tooltip>
                            @endif
                    </flux:avatar.group>
                    <flux:button icon="plus" variant="primary" size="sm" class="!rounded-full ml-auto md:mt-2 cursor-pointer"></flux:button>
                </div>
            </div>

            <div x-data="{ active: 'overview' }" class=" border-b border-zinc-200 pt-4">

                <!-- TAB NAVIGATION -->
                <div class="flex items-center px-6 gap-8">
                    <!-- Overview -->
                    <button @click="active = 'overview'" :class="active === 'overview'
                ? 'text-red-600 border-red-500'
                : 'text-zinc-500 border-transparent cursor-pointer hover:text-zinc-800 hover:border-zinc-300'" class="flex items-center gap-2 pb-3 border-b-2 text-sm font-medium transition">
                        <flux:icon name="layout-grid" class="w-4 h-4" />
                        Overview
                    </button>

                    <!-- spectech -->
                    <button @click="active = 'spectech'" :class="active === 'spectech'
                ? 'text-red-600 border-red-500'
                : 'text-zinc-500 border-transparent cursor-pointer hover:text-zinc-800 hover:border-zinc-300'" class="flex items-center gap-2 pb-3 border-b-2 text-sm font-medium transition">
                        <flux:icon name="plus" class="w-4 h-4" />
                        Spectech
                    </button>

                    <!-- Dashboard -->
                    <button @click="active = 'timeline'" :class="active === 'timeline'
                ? 'text-red-600 border-red-500'
                : 'text-zinc-500 border-transparent cursor-pointer hover:text-zinc-800 hover:border-zinc-300'" class="flex items-center gap-2 pb-3 border-b-2 text-sm font-medium transition">
                        <flux:icon name="calendar-days" class="w-4 h-4" />
                        Timeline
                    </button>

                    <!-- Calendar -->
                    <button @click="active = 'team'" :class="active === 'team'
                ? 'text-red-600 border-red-500'
                : 'text-zinc-500 border-transparent cursor-pointer hover:text-zinc-800 hover:border-zinc-300'" class="flex items-center gap-2 pb-3 border-b-2 text-sm font-medium transition">
                        <flux:icon name="users" class="w-4 h-4" />
                        Team Members
                    </button>

                    <!-- File -->
                    <button @click="active = 'file'" :class="active === 'file'
                ? 'text-red-600 border-red-500'
                : 'text-zinc-500 border-transparent cursor-pointer hover:text-zinc-800 hover:border-zinc-300'" class="flex items-center gap-2 pb-3 border-b-2 text-sm font-medium transition">
                        <flux:icon name="folder" class="w-4 h-4" />
                        Files
                    </button>

                </div>

                <!-- TAB CONTENT -->
                <div class="py-6">
                    <div x-show="active === 'overview'" wire:key="overview-{{ $this->id }}" x-transition>
                        <livewire:project.components.project-overview-tabs lazy :project="$this->project" :spectech="$this->project['specktech']" :documents="$this->document" />
                    </div>

                    <div x-show="active === 'spectech'" wire:key="spectech-{{ $this->id }}" x-transition >
                        <livewire:project.components.project-spectech-tabs lazy :totalproject="$this->project['value']" :spectech="$this->project['specktech']" :id="$this->project['id']" :progress="$this->project['progress']" />
                    </div>

                    <div x-show="active === 'timeline'" wire:key="project-{{ $this->id }}" x-transition>
                        <livewire:project.components.project-timeline-tabs lazy />
                    </div>

                    <div x-show="active === 'team'" wire:key="team-{{ $this->id }}" x-transition>
                        <livewire:project.components.project-team-tabs lazy :internal="$this->project['support_team_internals']" :timduk="$this->project['support_teams']" />
                    </div>

                    <div x-show="active === 'file'" wire:key="files-{{ $this->id }}" x-transition wire:init="documentLoad">
                        <livewire:project.components.project-files-tabs :id="$this->id" lazy />
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
