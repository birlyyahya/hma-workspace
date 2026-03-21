<?php

use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public function getProjectsProperty()
    {
        return Cache::remember(
        'projects_sidebar_' . Auth::user()->username,
        now()->addDay(),
        function () {
            try {

                $response = Http::timeout(5)
                    ->get(env('API_PROJECT') . 'projects/search?project_leader_id=' . Auth::user()->id)
                    ->json();

                if (($response['status'] ?? null) === 200) {
                    return $response['data'];
                }

                return [];

            } catch (\Exception $e) {
                Toaster::error('Server PM Error, silahkan coba lagi atau menghubungi tim IT');
                return [];
            }
        }
    );
    }
}; ?>

<div>
    @if(Auth::user()->role_id <= 5) <flux:sidebar.item :href="route('projects')" icon="document-text" :current="
                request()->routeIs('projects')" wire:navigate>
            <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                <div class="truncate">
                    All Project
                </div>
            </div>
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('projects')" icon="document-text" :current="
                request()->routeIs('projects')" wire:navigate>
                <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                    <div class="truncate">
                        Perusahaan
                    </div>
                </div>
            </flux:sidebar.item>
            <flux:sidebar.item># Project</flux:sidebar.item>
            @endif
    @if(!empty($this->projects))
            @foreach ($this->projects as $project)
        <flux:sidebar.item :href="route('projects.show', $project['id'])" icon="document-text" :current="
        request()->routeIs('projects.show')
        && request()->route('id') == $project['id']" wire:navigate>
            <div class="flex-1 min-w-0 max-w-39 overflow-hidden">
                <div class="truncate">
                    {{ $project['code'] }} - {{ $project['name'] }}
                </div>
            </div>
        </flux:sidebar.item>
        @endforeach
    @else

    @endif
</div>
