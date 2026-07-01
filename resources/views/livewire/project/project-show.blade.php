<?php

use App\Services\ProjectCache;
use App\Services\ProjectWriter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Lazy] class extends Component {
    public $id;
    public $project;
    public $document;
    public bool $forbidden = false;

    public function deleteProject(): void
    {
        if (! Auth::user()->hasPermission('project.delete')) {
            Toaster::warning('Anda tidak memiliki akses untuk menghapus project.');

            return;
        }

        $result = app(ProjectWriter::class)->deleteProject((int) $this->id);

        if ($result['ok']) {
            Toaster::success('Proyek berhasil dihapus');
            $this->redirect(route('projects'), navigate: true);

            return;
        }

        Toaster::error($result['body']['message'] ?? 'Gagal menghapus proyek');
    }

    protected function fetchProject(): void
    {
        $project = app(ProjectCache::class)->projectFor((int) $this->id);

        if (! empty($project)) {
            $this->project = $project;
        }
    }

    /**
     * Detail project hanya boleh dibuka oleh pemilik project (leader), user yang
     * tergabung di tim internal, atau role dengan scope project 'all'.
     */
    protected function canViewProject(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->viewScopeFor('project') === 'all') {
            return true;
        }

        if (empty($this->project)) {
            return false;
        }

        $isLeader = (int) ($this->project['project_leader_id'] ?? 0) === (int) $user->id;

        $isMember = collect($this->project['support_team_internals'] ?? [])
            ->contains(fn ($member) => (int) ($member['user_id'] ?? 0) === (int) $user->id);

        return $isLeader || $isMember;
    }

    public function mount(): void
    {
        $this->fetchProject();

        if (! $this->canViewProject()) {
            // Render state error tanpa melempar exception, supaya saat lazy load
            // tampil sebagai kartu 403 di dalam layout (bukan modal overlay).
            // Kosongkan project agar datanya tidak ikut ter-serialisasi ke client.
            $this->forbidden = true;
            $this->project = null;

            return;
        }

        $this->dispatch('timelineLoad');
        $this->dispatch('documentLoad');
    }

    #[On('projectLoad')]
    public function refreshProject(): void
    {
        $this->fetchProject();

        if (! empty($this->project['progress'])) {
            $this->dispatch('updateProgress', progress: $this->project['progress']);
        }
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_project_show');
    }
}; ?>

@php
    $statusBadge = match($this->project['status'] ?? null) {
        'ON PROGRESS' => 'blue',
        'WAITING'     => 'yellow',
        'CLOSED'      => 'red',
        default       => 'zinc',
    };
    $statusDot = match($this->project['status'] ?? null) {
        'ON PROGRESS' => 'bg-blue-500',
        'WAITING'     => 'bg-amber-500',
        'CLOSED'      => 'bg-red-500',
        default       => 'bg-zinc-400',
    };

    $tabs = [
        ['key' => 'overview', 'label' => 'Overview',     'icon' => 'layout-grid'],
        ['key' => 'spectech', 'label' => 'Spektek',     'icon' => 'cube'],
        ['key' => 'timeline', 'label' => 'Timeline',     'icon' => 'calendar-days'],
        ['key' => 'team',     'label' => 'Tim',          'icon' => 'users'],
        ['key' => 'file',     'label' => 'File',         'icon' => 'folder'],
    ];
    $supportTeams = $this->project['support_teams'] ?? [];
    $supportCount = count($supportTeams);
@endphp

<div>
    @if ($forbidden)
        <div class="min-h-[60vh] flex items-center justify-center px-4 py-10">
            <x-errors.403 />
        </div>
    @else
    <div class="max-h-screen overflow-auto">
        <div
            x-data="{
                active: 'overview',
                init() {
                    const fromHash = window.location.hash.replace('#', '');
                    if (fromHash) this.active = fromHash;
                    this.$watch('active', (v) => {
                        history.replaceState(null, '', '#' + v);
                    });
                }
            }"
        >
            {{-- ============ STICKY HEADER ============ --}}
            <div class="bg-white/90 backdrop-blur ">
                <div class="px-4 sm:px-6 pt-4 pb-4">
                    {{-- Breadcrumb --}}
                    <nav class="flex items-center gap-1.5 text-xs text-zinc-500 mb-3">
                        @if(!Auth::user()->level >= 60)
                        <a class=" transition inline-flex items-center gap-1">
                            <flux:icon.ellipsis-vertical class="w-3.5 h-3.5" />
                            Project
                        </a>
                        @else
                        <a href="{{ route('projects') }}" class="hover:text-red-600 transition inline-flex items-center gap-1">
                            <flux:icon.chevron-left class="w-3.5 h-3.5" />
                            Project
                        </a>
                        @endif
                        <span class="text-zinc-300">/</span>
                        <span class="text-zinc-700 font-medium truncate max-w-md">
                            {{ $this->project['code'] ?? '—' }}
                        </span>
                    </nav>

                    {{-- Title row --}}
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if(!empty($this->project['code']))
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-red-50 text-red-700 ring-1 ring-red-100">
                                        {{ $this->project['code'] }}
                                    </span>
                                @endif
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $statusDot }}"></span>
                                    {{ $this->project['status'] ?? 'UNKNOWN' }}
                                </span>
                            </div>
                            <h1 class="mt-2 text-lg sm:text-xl font-bold text-zinc-900 leading-snug line-clamp-2">
                                {{ ucwords(Str::lower($this->project['name'])) ?? '-' }}
                            </h1>

                            {{-- Quick meta row --}}
                            <div class="mt-2 flex items-center gap-x-4 gap-y-1 flex-wrap text-xs text-zinc-500">
                                @if(!empty($this->project['client']))
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon.building-office class="w-3.5 h-3.5" />
                                        {{ $this->project['client'] }}
                                    </span>
                                @endif
                                @if(!empty($this->project['start_date']) && !empty($this->project['end_date']))
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon.calendar class="w-3.5 h-3.5" />
                                        {{ Carbon::parse($this->project['start_date'])->locale('id')->translatedFormat('d M Y') }}
                                        <span class="text-zinc-300">–</span>
                                        {{ Carbon::parse($this->project['end_date'])->locale('id')->translatedFormat('d M Y') }}
                                    </span>
                                @endif
                                @if(isset($this->project['progress']))
                                    <span class="inline-flex items-center gap-1.5">
                                        <flux:icon.chart-bar class="w-3.5 h-3.5" />
                                        Progress {{ $this->project['progress'] }}%
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Right side: avatars + actions --}}
                        <div class="flex items-center gap-3 shrink-0">
                            @if($supportCount > 0)
                                <flux:avatar.group>
                                    @foreach (array_slice($supportTeams, 0, 4) as $user)
                                        <flux:tooltip content="{{ $user }}">
                                            <flux:avatar circle name="{{ $user }}" color="auto" color:seed="{{ $user }}" size="sm" />
                                        </flux:tooltip>
                                    @endforeach

                                    @if($supportCount > 4)
                                        <flux:tooltip content="{{ implode(', ', array_slice($supportTeams, 4)) }}">
                                            <flux:avatar circle size="sm" class="ring-2 ring-white">
                                                +{{ $supportCount - 4 }}
                                            </flux:avatar>
                                        </flux:tooltip>
                                    @endif
                                </flux:avatar.group>
                            @endif

                            <flux:button
                                size="sm"
                                variant="outline"
                                icon="pencil-square"
                                :href="route('projects.edit', $this->id)"
                                wire:navigate
                            >
                                <span class="hidden sm:inline">Edit</span>
                            </flux:button>

                            <flux:dropdown align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('projects.edit', $this->id)" wire:navigate>
                                        Edit Proyek
                                    </flux:menu.item>
                                    @can('project.delete')
                                    <flux:menu.separator />
                                    <flux:menu.item
                                        icon="trash"
                                        variant="danger"
                                        x-on:click="$flux.modal('delete-project-modal').show()"
                                    >
                                        Hapus Proyek
                                    </flux:menu.item>
                                    @endcan
                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </div>

                </div>
            </div>
            <div class="md:sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-zinc-200  px-4 sm:px-6 pb-0">
                {{-- ============ TAB NAVIGATION ============ --}}
                <nav class=" -mb-px flex items-center gap-1 overflow-x-auto">
                    @foreach($tabs as $tab)
                        <button
                            type="button"
                            @click="active = '{{ $tab['key'] }}'"
                            :class="active === '{{ $tab['key'] }}'
                                ? 'text-red-600 border-red-600'
                                : 'text-zinc-500 border-transparent hover:text-zinc-800 hover:border-zinc-300'"
                            class="flex items-center gap-2 px-3 py-2.5 border-b-2 text-sm font-medium transition cursor-pointer whitespace-nowrap"
                        >
                            <flux:icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                            {{ $tab['label'] }}
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- ============ TAB CONTENT ============ --}}
            <div class="px-4 sm:px-6 py-6">
                <div x-show="active === 'overview'" wire:key="overview-{{ $this->id }}" x-transition.opacity.duration.150ms>
                    <livewire:project.components.project-overview-tabs defer :project="$this->project" />
                </div>

                <div x-show="active === 'spectech'" wire:key="spectech-{{ $this->id }}" x-transition.opacity.duration.150ms>
                    <livewire:project.components.project-spectech-tabs lazy
                        :totalproject="$this->project['value']"
                        :id="$this->project['id']"
                        :progress="$this->project['progress']" />
                </div>
                <div x-show="active === 'timeline'" wire:key="timeline-{{ $this->id }}" x-transition.opacity.duration.150ms>
                    <div class="space-y-6">
                        <livewire:project.components.project-timeline-gantt lazy :id="$this->id" :user_id="$this->project['project_leader_id']"  />
                        <livewire:project.components.project-timeline-tabs lazy :id="$this->id"  :user_id="$this->project['project_leader_id']"  />
                    </div>
                </div>

                <div x-show="active === 'team'" wire:key="team-{{ $this->id }}" x-transition.opacity.duration.150ms>
                    <livewire:project.components.project-team-tabs lazy
                        :id="$this->id"
                        :leader-id="$this->project['project_leader_id']"
                        :internal="$this->project['support_team_internals'] ?? []"
                        :timduk="$this->project['support_teams'] ?? []" />
                </div>

                <div x-show="active === 'file'" wire:key="files-{{ $this->id }}" x-transition.opacity.duration.150ms>
                    <livewire:project.components.project-files-tabs lazy :id="$this->id" />
                </div>
            </div>
        </div>
    </div>

    {{-- Delete confirmation modal --}}
    <x-confirm-modal name="delete-project-modal" confirm="deleteProject" title="Hapus proyek ini?" confirm-label="Hapus Proyek" />
    @endif
</div>
