<?php

use App\Services\ProjectCache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;

new class extends Component {
    public function getProjectsProperty(): array
    {
        try {
            return app(ProjectCache::class)->involvedProjects(Auth::id());
        } catch (\Throwable $e) {
            Log::warning('Sidebar projects load failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}; ?>

<div>
    @if (Auth::user()->viewScopeFor('project') === 'all')
        <flux:sidebar.item :href="route('projects')" icon="document-text"
            :current="request()->routeIs('projects')" wire:navigate>
            <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                <div class="truncate">All Project</div>
            </div>
        </flux:sidebar.item>

        <flux:sidebar.item :href="route('perusahaan')" icon="building-office"
            :current="request()->routeIs('perusahaan')" wire:navigate>
            <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                <div class="truncate">Perusahaan</div>
            </div>
        </flux:sidebar.item>

        @if (! empty($this->projects))
        <flux:separator class="my-1"></flux:separator>
        @endif

    @endif

    @if (! empty($this->projects))
        @foreach ($this->projects as $project)
            <flux:sidebar.item wire:key="sidebar-project-{{ $project['id'] }}"
                :href="route('projects.show', $project['id'])" icon="document-text"
                :current="request()->routeIs('projects.show') && request()->route('id') == $project['id']"
                wire:navigate>
                <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                    <div class="truncate">
                        {{ $project['code'] }} - {{ $project['name'] }}
                    </div>
                </div>
            </flux:sidebar.item>
        @endforeach
    @else
        @if (Auth::user()->viewScopeFor('project') === 'own')
        <flux:sidebar.item disabled>No Own Project</flux:sidebar.item>
        @endif
    @endif
</div>
