<?php

use App\Livewire\Forms\ActivityForm;
use App\Models\User;
use App\Services\DarCache;
use App\Services\DarWriter;
use App\Services\ProjectCache;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public ActivityForm $form;

    public array $tasks = [];
    public array $projectData = [];
    public array $allProjects = [];
    public array $accessibleProjectIds = [];
    public array $users = [];
    public array $allUsers = [];
    public array $commentUsers = [];
    public $projectSelected = null;
    public array $timelines = [];
    public string $search = '';

    public string $tab = 'all';
    public string $statusFilter = 'all';
    public string $projectFilter = '';
    public string $userFilter = '';

    public bool $loading = true;

    public int $perPage = 21;
    public int $page = 1;
    public bool $hasMore = true;
    public bool $loadingMore = false;
    public ?int $total = 0;

    public ?int $pendingDeleteId = null;
    public string $pendingDeleteName = '';

    public function mount(): void
    {
        $this->users = User::whereNotIn('role_id', [1, 2])->orderBy('name')->get(['id', 'name'])->toArray();
        $this->allUsers = User::orderBy('name')->get(['id', 'name'])->toArray();

        try {
            $cache = app(ProjectCache::class);
            $source = Auth::user()->viewScopeFor('project') === 'all'
                ? $cache->allProjects()
                : $cache->involvedProjects(Auth::id());

            $this->projectData = $this->trimProjects($source);
            $this->allProjects = $this->trimProjects($cache->allProjects());
        } catch (\Throwable $e) {
            $this->projectData = [];
            $this->allProjects = [];
        }

        $this->fetchTasks();
        $this->resetForm();
    }

    /**
     * Sisakan hanya kolom yang dipakai card/dropdown agar snapshot Livewire tetap ramping.
     *
     * @param  array<int, array<string, mixed>>  $projects
     * @return array<int, array{id:int,code:?string,name:?string}>
     */
    private function trimProjects(array $projects): array
    {
        return collect($projects)
            ->map(fn ($p) => [
                'id' => $p['id'] ?? null,
                'code' => $p['code'] ?? null,
                'name' => $p['name'] ?? null,
            ])
            ->filter(fn ($p) => $p['id'] !== null)
            ->values()
            ->all();
    }

    /**
     * Buang payload berat (file komentar & kolom tak terpakai) dari respons API DAR.
     *
     * @param  array<int, array<string, mixed>>  $tasks
     * @return array<int, array<string, mixed>>
     */
    private function trimTasks(array $tasks): array
    {
        return collect($tasks)->map(fn ($task) => [
            'id' => $task['id'] ?? null,
            'user_id' => $task['user_id'] ?? null,
            'activity' => $task['activity'] ?? null,
            'description' => $task['description'] ?? null,
            'status' => (int) ($task['status'] ?? 0),
            'project_id' => $task['project_id'] ?? null,
            'start_date' => $task['start_date'] ?? null,
            'end_date' => $task['end_date'] ?? null,
            'team_user' => collect($task['team_user'] ?? [])
                ->map(fn ($member) => ['user_id' => $member['user_id'] ?? null])
                ->all(),
            'comments' => collect($task['comments'] ?? [])
                ->map(fn ($comment) => [
                    'user_id' => $comment['user_id'] ?? null,
                    'body' => $comment['body'] ?? '',
                    'created_at' => $comment['created_at'] ?? '',
                ])
                ->all(),
        ])->all();
    }

    public function updatedProjectFilter(): void
    {
        $this->fetchTasks();
    }

    public function updatedUserFilter(): void
    {
        $this->fetchTasks();
    }

    public function updatedProjectSelected(): void
    {
        $this->timelines = app(ProjectCache::class)->timelines((int) $this->projectSelected);

        $this->form->project_id = $this->projectSelected;
    }

    public function updatedFormIsproject($value): void
    {
        if (! $value) {
            $this->projectSelected = null;
            $this->timelines = [];
            $this->form->project_id = null;
            $this->form->timelines_id = null;
        }
    }

    /**
     * Bangun query param untuk endpoint list DAR, termasuk halaman aktif.
     *
     * @return array<string, mixed>
     */
    private function taskParams(): array
    {
        $params = [
            'perPage' => $this->perPage,
            'page' => $this->page,
        ];

        if ($this->search !== '') {
            $params['search'] = $this->search;
        }

        if (Auth::user()->viewScopeFor('dar') === 'all') {

            // $params['role'] = Auth::user()->role->name ?? null;

            if ($this->userFilter !== '') {
                $params['team_user'] = $this->userFilter;
            }
        } else {
            $params['team_user'] = Auth::id();
        }

        if ($this->projectFilter !== '') {
            $params['project_id'] = $this->projectFilter;
        }

        return $params;
    }

    /**
     * Muat ulang dari halaman pertama (dipicu filter, search, atau setelah write).
     */
    #[On('updatedCardTaskDar')]
    public function fetchTasks(): void
    {
        $this->loading = true;
        $this->page = 1;
        $this->hasMore = true;

        try {
            $response = app(DarCache::class)->tasks($this->taskParams());
            $response['total'] = (int) ($response['total'] ?? 0);

            $this->tasks = $this->trimTasks($response['data'] ?? []);
            $this->total = $response['total'];
            $this->syncPagination($response);
            $this->hydrateDerivedState();
        } catch (\Throwable $e) {
            $this->tasks = [];
            $this->commentUsers = [];
            $this->hasMore = false;
            $this->total = 0;
            Toaster::error('Server DAR Error, silahkan coba lagi atau menghubungi tim IT');
            Log::error('DAR list API failed', ['message' => $e->getMessage()]);
        } finally {
            $this->loading = false;
            $this->dispatch('dar-tasks-updated');
        }
    }

    /**
     * Ambil halaman berikutnya dan tempelkan ke daftar (infinite scroll).
     */
    public function loadMore(): void
    {
        if ($this->loadingMore || $this->loading || ! $this->hasMore) {
            return;
        }

        $this->loadingMore = true;
        $this->page++;

        try {
            $response = app(DarCache::class)->tasks($this->taskParams());

            $this->tasks = collect($this->tasks)
                ->concat($this->trimTasks($response['data'] ?? []))
                ->keyBy('id')
                ->values()
                ->all();

            $this->syncPagination($response);
            $this->hydrateDerivedState();
        } catch (\Throwable $e) {
            $this->page--;
            Toaster::error('Server DAR Error, silahkan coba lagi atau menghubungi tim IT');
            Log::error('DAR loadMore API failed', ['message' => $e->getMessage()]);
        } finally {
            $this->loadingMore = false;
            $this->dispatch('dar-tasks-updated');
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function syncPagination(array $response): void
    {
        $current = (int) ($response['current_page'] ?? $this->page);
        $last = (int) ($response['last_page'] ?? $current);

        $this->hasMore = $current < $last;
    }

    /**
     * Turunkan state pendukung (project yang bisa difilter + nama commenter) dari task termuat.
     */
    private function hydrateDerivedState(): void
    {
        if ($this->projectFilter === '' && $this->search === '') {
            $this->accessibleProjectIds = collect($this->tasks)
                ->pluck('project_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        $commenterIds = collect($this->tasks)
            ->flatMap(fn ($task) => collect($task['comments'] ?? [])->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        $this->commentUsers = User::whereIn('id', $commenterIds)
            ->get(['id', 'name'])
            ->keyBy('id')
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
            ->toArray();
    }

    public function projectData(): array
    {
        return $this->projectData;
    }

    public function visibleTasks(): \Illuminate\Support\Collection
    {
        return collect($this->tasks)->values();
    }

    public function teamUser($users): array
    {
        $userMap = collect($this->allUsers)->keyBy('id');

        return collect($users)
            ->map(fn ($id) => $userMap[$id]['name'] ?? null)
            ->filter()
            ->all();
    }

    public function toggleTeamUser(int $userId): void
    {
        $current = collect($this->form->team_user ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();

        if (in_array($userId, $current, true)) {
            $this->form->team_user = array_values(array_filter($current, fn ($id) => $id !== $userId));
        } else {
            $current[] = $userId;
            $this->form->team_user = $current;
        }
    }

    public function createActivity(): void
    {
        try {
            $response = $this->form->store($this->projectSelected);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Toaster::error('Server Error saat membuat task');
            Log::error('DAR create exception', ['message' => $e->getMessage()]);

            return;
        }

        $success = is_array($response) ? ($response['success'] ?? false) : ($response['success'] ?? false);

        if (! $success) {
            $message = is_array($response) ? ($response['message'] ?? 'Create Task failed') : 'Create Task failed';
            Toaster::error($message);
            Log::error('DAR create API failed', [
                'message' => $message,
                'errors' => $response['errors'] ?? null,
            ]);

            return;
        }

        Toaster::success('Create Activity successfully');

        app(DarCache::class)->flush();

        $this->resetForm();
        $this->projectSelected = null;
        $this->timelines = [];

        Flux::modals()->close('create-task');

        $this->dispatch('updatedCardTaskDar');
        $this->dispatch('updatedTimeline');

        $this->fetchTasks();
    }

    private function resetForm(): void
    {
        $this->form->resetForm();
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_task_dar');
    }

    public function confirmDeleteTask(int $id, string $name = ''): void
    {

        $task = collect($this->tasks)->firstWhere('id', $id);

        if (Auth::user()->id !== $task['user_id'] || !Auth::user()->hasPermission('dar.delete')) {
            Toaster::error('You do not have permission to delete this activity.');
            return;
        }
        $this->pendingDeleteId = $id;
        $this->pendingDeleteName = $name;
        Flux::modal('delete-task')->show();
    }

    public function cancelDeleteTask(): void
    {

        $this->pendingDeleteId = null;
        $this->pendingDeleteName = '';
        Flux::modal('delete-task')->close();
    }

    public function deleteTask()
    {


        $id = $this->pendingDeleteId;

        if (empty($id)) {
            Toaster::error('Invalid task id');
            return;
        }

        $result = app(DarWriter::class)->deleteActivity((int) $id);

        if ($result['ok']) {
            Toaster::success('Task berhasil dihapus');
            $this->pendingDeleteId = null;
            $this->pendingDeleteName = '';
            Flux::modal('delete-task')->close();
            $this->dispatch('updatedTimeline');
            $this->fetchTasks();
            return;
        }

        if ($result['error'] !== null) {
            Toaster::error('Server Error saat menghapus task');
            \Log::error('DAR delete API exception', [
                'message' => $result['error'],
            ]);
            return;
        }

        Toaster::error('Menghapus task gagal');
        \Log::error('DAR delete API failed', [
            'status' => $result['status'],
            'body' => $result['body'],
        ]);
    }

}; ?>


<div>
    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>

    @php
        $isSuperadmin = (int) (Auth::user()->level ?? 0) >= 100;
        $statusOptions = [
            'all' => ['label' => 'Semua', 'active' => 'bg-slate-900 text-white ring-slate-900'],
            '1' => ['label' => 'Open', 'active' => 'bg-blue-600 text-white ring-blue-600'],
            '2' => ['label' => 'Pending', 'active' => 'bg-amber-500 text-white ring-amber-500'],
            '3' => ['label' => 'Cancelled', 'active' => 'bg-rose-600 text-white ring-rose-600'],
            '4' => ['label' => 'Closed', 'active' => 'bg-emerald-600 text-white ring-emerald-600'],
        ];
        $tabOptions = [
            'all' => 'Semua',
            'project' => 'Project',
            'nonproject' => 'Non-Project',
        ];
    @endphp

    <section x-data="{
        tab: @js($tab),
        status: @js($statusFilter),
        counts: { all: 0, 1: 0, 2: 0, 3: 0, 4: 0 },
        visible: 0,
        filter() {
            const c = { all: 0, 1: 0, 2: 0, 3: 0, 4: 0 };
            let vis = 0;
            this.$root.querySelectorAll('[data-dar-card]').forEach(el => {
                const s = el.dataset.status;
                const isProject = el.dataset.type === 'project';
                const inTab = this.tab === 'all' || (this.tab === 'project' && isProject) || (this.tab === 'nonproject' && !isProject);
                if (!inTab) { return; }
                c.all++;
                c[s] = (c[s] ?? 0) + 1;
                if (this.status === 'all' || this.status === s) { vis++; }
            });
            this.counts = c;
            this.visible = vis;
        },
        switchTab(next) {
            this.tab = next;
            if (next !== 'project' && this.$wire.projectFilter) {
                this.$wire.set('projectFilter', '');
            }
            this.filter();
        },
    }" x-init="$nextTick(() => filter())" @dar-tasks-updated.window="$nextTick(() => filter())">
        {{-- Basecamp-ish section header --}}
        <header class="space-y-4 px-5 py-4">
            <div class="flex items-center gap-3">
                <flux:modal.trigger name="create-task">
                    <flux:button icon="plus-circle" iconClasses="size-6" variant="outline">
                        Tambah Tugas
                    </flux:button>
                </flux:modal.trigger>

                <div class="flex flex-1 items-center gap-4">
                    <div class="h-px flex-1 bg-slate-200/70"></div>
                    <h2 class="text-lg font-semibold tracking-tight text-slate-800">Tugas</h2>
                    <div class="h-px flex-1 bg-slate-200/70"></div>
                </div>

                <flux:input x-on:keydown.enter="$wire.fetchTasks()" wire:model="search" icon="magnifying-glass" placeholder="Search task..." class="w-full md:w-64" />
            </div>

            {{-- Tabs tipe + filter project/user --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex items-center rounded-xl bg-slate-100 p-1">
                    @foreach ($tabOptions as $key => $label)
                        <button type="button" @click="switchTab('{{ $key }}')"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="tab === '{{ $key }}' ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' : 'text-slate-600 hover:text-slate-900'">
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @php
                        $filterProjects = $isSuperadmin
                            ? $allProjects
                            : collect($allProjects)->whereIn('id', $accessibleProjectIds)->values()->toArray();
                        $projectLabel = $projectFilter !== ''
                            ? (collect($allProjects)->firstWhere('id', (int) $projectFilter)['name'] ?? 'Semua project')
                            : 'Semua project';
                    @endphp
                    <div x-show="tab === 'project'" x-cloak class="relative w-56" x-data="{ open: false, query: '' }" @click.away="open = false" @keydown.escape.window="open = false">
                            <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.projectSearch?.focus())"
                                class="flex w-full items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 transition hover:border-zinc-300">
                                <span class="truncate">{{ $projectLabel }}</span>
                                <flux:icon name="chevron-down" class="h-4 w-4 shrink-0 text-zinc-400" />
                            </button>

                            <div x-show="open" x-cloak x-transition.origin.top class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70">
                                <div class="border-b border-zinc-100 p-2">
                                    <input x-ref="projectSearch" x-model="query" type="text" placeholder="Cari project..."
                                        class="w-full rounded-lg border-0 bg-zinc-50 px-2 py-1.5 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200" />
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1">
                                    <button type="button" wire:click="$set('projectFilter', '')" @click="open = false; query = ''"
                                        class="block w-full rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ $projectFilter === '' ? 'bg-zinc-50 font-semibold text-zinc-900' : 'text-zinc-600' }}">
                                        Semua project
                                    </button>
                                    @foreach ($filterProjects as $p)
                                        <button wire:key="filter-project-{{ $p['id'] }}" type="button"
                                            x-show="query === '' || '{{ addslashes(strtolower($p['name'] ?? '')) }}'.includes(query.toLowerCase())"
                                            wire:click="$set('projectFilter', '{{ $p['id'] }}')" @click="open = false; query = ''"
                                            class="block w-full rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ (string) $projectFilter === (string) $p['id'] ? 'bg-zinc-50 font-semibold text-zinc-900' : 'text-zinc-700' }}">
                                            {{ $p['name'] ?? 'Untitled' }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                    @if ($isSuperadmin)
                        @php
                            $userLabel = $userFilter !== ''
                                ? (collect($allUsers)->firstWhere('id', (int) $userFilter)['name'] ?? 'Semua user')
                                : 'Semua user';
                        @endphp
                        <div class="relative w-56" x-data="{ open: false, query: '' }" @click.away="open = false" @keydown.escape.window="open = false">
                            <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.userSearch?.focus())"
                                class="flex w-full items-center justify-between gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2 text-sm text-zinc-700 transition hover:border-zinc-300">
                                <span class="truncate">{{ $userLabel }}</span>
                                <flux:icon name="chevron-down" class="h-4 w-4 shrink-0 text-zinc-400" />
                            </button>

                            <div x-show="open" x-cloak x-transition.origin.top class="absolute left-0 right-0 z-30 mt-1 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70">
                                <div class="border-b border-zinc-100 p-2">
                                    <input x-ref="userSearch" x-model="query" type="text" placeholder="Cari user..."
                                        class="w-full rounded-lg border-0 bg-zinc-50 px-2 py-1.5 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200" />
                                </div>
                                <div class="max-h-64 overflow-y-auto p-1">
                                    <button type="button" wire:click="$set('userFilter', '')" @click="open = false; query = ''"
                                        class="block w-full rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ $userFilter === '' ? 'bg-zinc-50 font-semibold text-zinc-900' : 'text-zinc-600' }}">
                                        Semua user
                                    </button>
                                    @foreach ($allUsers as $u)
                                        <button wire:key="filter-user-{{ $u['id'] }}" type="button"
                                            x-show="query === '' || '{{ addslashes(strtolower($u['name'])) }}'.includes(query.toLowerCase())"
                                            wire:click="$set('userFilter', '{{ $u['id'] }}')" @click="open = false; query = ''"
                                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ (string) $userFilter === (string) $u['id'] ? 'bg-zinc-50 font-semibold text-zinc-900' : 'text-zinc-700' }}">
                                            <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                            <span class="truncate">{{ $u['name'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Status pills dengan counter --}}
            <div class="flex flex-wrap items-center gap-2">
              @foreach ($statusOptions as $key => $opt)
    <button
        type="button"
        @click="status = '{{ $key }}'; filter()"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 transition"
        :class="status === '{{ $key }}'
            ? '{{ $opt['active'] }} shadow-sm'
            : 'bg-white text-slate-600 ring-slate-200 hover:bg-slate-50'">

        <span>{{ $opt['label'] }}</span>

        <span
            class="rounded-full px-1.5 text-[10px] font-bold"
            :class="status === '{{ $key }}' ? 'bg-white/25' : 'bg-slate-100'"
            @if($key === 'all')
                x-text="$wire.total"
            @else
                x-text="counts['{{ $key }}'] ?? 0"
            @endif
        ></span>
    </button>
@endforeach
            </div>
        </header>

        <div class="px-5 pb-5">
            @if($loading)
            <div class="rounded-2xl bg-white p-6 text-sm text-slate-600 ring-1 ring-slate-200/70 shadow-sm">
                Loading tasks...
            </div>
            @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.remove wire:target="fetchTasks">
                @php
                    $projectMap = collect($allProjects)->keyBy('id');
                    $userMap = collect($allUsers)->keyBy('id');
                @endphp
                @forelse($this->visibleTasks() as $task)
                @php
                $status = $task['status'] ?? 0;
                $taskId = $task['id'] ?? null;
                $taskUrl = $taskId ? route('dar.dar-show', $taskId) : '#';

                $statusMeta = match ($status) {
                    1 => ['label' => 'Open',      'dot' => 'bg-blue-500',    'text' => 'text-blue-700',    'accent' => 'from-blue-400/80 to-blue-500'],
                    2 => ['label' => 'Pending',   'dot' => 'bg-amber-500',   'text' => 'text-amber-700',   'accent' => 'from-amber-400/80 to-amber-500'],
                    3 => ['label' => 'Cancelled', 'dot' => 'bg-rose-500',    'text' => 'text-rose-700',    'accent' => 'from-rose-400/80 to-rose-500'],
                    4 => ['label' => 'Closed', 'dot' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'accent' => 'from-emerald-400/80 to-emerald-500'],
                    default => ['label' => 'Draft', 'dot' => 'bg-slate-400', 'text' => 'text-slate-600',   'accent' => 'from-slate-300 to-slate-400'],
                };

                $assignees = $this->teamUser(collect($task['team_user'])->pluck('user_id')) ?? [];
                if (empty($assignees)) {
                    $assignees = [Auth::user()->name];
                }

                $lastComment = collect($task['comments'] ?? [])
                    ->sortByDesc(fn ($c) => $c['created_at'] ?? '')
                    ->first();
                $lastCommentUser = $lastComment ? ($this->commentUsers[$lastComment['user_id']] ?? null) : null;
                $lastCommentName = $lastCommentUser['name'] ?? 'Pengguna';
                $lastCommentBody = $lastComment ? trim(strip_tags($lastComment['body'] ?? '')) : '';

                $endDate = !empty($task['end_date']) ? \Carbon\Carbon::parse($task['end_date']) : null;
                $isOverdue = $endDate && $status !== 4 && $endDate->isPast();

                $taskProjectId = $task['project_id'] ?? null;
                $taskProjectName = $taskProjectId ? ($projectMap[$taskProjectId]['name'] ?? 'Project') : null;
                $ownerName = $isSuperadmin && ! empty($task['user_id']) ? ($userMap[$task['user_id']]['name'] ?? null) : null;
                @endphp

                <article wire:key="dar-card-{{ $taskId }}"
                    x-data="{ menuOpen: false }"
                    data-dar-card
                    data-status="{{ $status }}"
                    data-type="{{ $taskProjectId ? 'project' : 'nonproject' }}"
                    wire:key='{{ $taskProjectId }}'
                    x-show="(tab === 'all' || (tab === 'project' ? {{ $taskProjectId ? 'true' : 'false' }} : !{{ $taskProjectId ? 'true' : 'false' }})) && (status === 'all' || status === '{{ $status }}')"
                    class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-sm transition hover:z-50 hover:-translate-y-0.5 hover:shadow-lg hover:ring-slate-300/70">
                    <a href="{{ $taskUrl }}" wire:navigate class="absolute inset-0 z-0" aria-label="Open task"></a>

                    {{-- Top status accent --}}
                    <div class="h-1 w-full bg-linear-to-r {{ $statusMeta['accent'] }}"></div>

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 relative z-10">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide {{ $statusMeta['text'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusMeta['dot'] }}"></span>
                                        {{ $statusMeta['label'] }}
                                    </span>
                                    @if ($taskProjectName)
                                        <span class="inline-flex max-w-56 items-center gap-1 truncate rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 ring-1 ring-indigo-100">
                                            <flux:icon name="folder" class="h-3 w-3 shrink-0" />
                                            <span class="truncate">{{ $taskProjectName }}</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 px-2 py-0.5 text-[10px] font-semibold text-zinc-600 ring-1 ring-zinc-200">
                                            Non-Project
                                        </span>
                                    @endif
                                </div>

                                <a href="{{ $taskUrl }}" wire:navigate class="mt-1.5 block text-base font-semibold leading-snug text-slate-900 line-clamp-1 group-hover:text-slate-950">
                                    {{ ucwords($task['activity'] ?? 'Untitled task') }}
                                </a>
                                <a href="{{ $taskUrl }}" wire:navigate class="mt-1 block text-sm leading-relaxed text-slate-500 line-clamp-1 truncate">
                                    {{ strip_tags($task['description'])  ?? 'Tidak ada deskripsi.' }}
                                </a>
                            </div>

                            {{-- Ellipsis menu --}}
                            <div class="relative shrink-0 z-20" @keydown.escape.window="menuOpen = false">
                                <button type="button" @click="menuOpen = !menuOpen" class="grid h-8 w-8 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Task menu">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                        <circle cx="5" cy="12" r="1.6" />
                                        <circle cx="12" cy="12" r="1.6" />
                                        <circle cx="19" cy="12" r="1.6" />
                                    </svg>
                                </button>

                                <div x-cloak x-show="menuOpen" @click.away="menuOpen = false" x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200/70">
                                    <a href="{{ $taskUrl }}" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Open</a>
                                    <div class="h-px bg-slate-200/70"></div>
                                    @if(Auth::user()->id === $task['user_id'] && Auth::user()->hasPermission('dar.delete'))
                                    <button type="button" @click="menuOpen = false" wire:click="confirmDeleteTask({{ $taskId }}, @js(ucwords($task['activity'] ?? 'Untitled task')))" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                        Delete
                                    </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Meta row --}}
                        <div class="relative z-10 mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-500">
                            @if($endDate)
                            <span class="inline-flex items-center gap-1.5 {{ $isOverdue ? 'text-rose-600 font-semibold' : '' }}">
                                <flux:icon name="calendar" class="h-3.5 w-3.5" />
                                {{ $endDate->format('d M Y') }}
                                @if($isOverdue)
                                    <span class="rounded-full bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-600 ring-1 ring-rose-200">Overdue</span>
                                @endif
                            </span>
                            @endif

                            <span class="inline-flex items-center gap-1.5">
                                <flux:icon name="chat-bubble-left-right" class="h-3.5 w-3.5" />
                                {{ count($task['comments'] ?? []) }}
                            </span>

                            <span class="inline-flex items-center gap-1.5">
                                <flux:icon name="users" class="h-3.5 w-3.5" />
                                {{ count($assignees) }}
                            </span>
                        </div>

                        {{-- Last comment --}}
                        <div class="relative z-10 mt-4 rounded-xl bg-slate-50/80 px-3 py-2.5 ring-1 ring-slate-100">
                            @if($lastComment)
                                <div class="flex items-center gap-2">
                                    <flux:avatar name="{{ $lastCommentName }}" circle size="xs" class="shrink-0" />
                                    <div class="min-w-0 flex-1 truncate text-xs text-slate-600">
                                        <span class="font-semibold text-slate-700">{{ $lastCommentName }}</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="text-slate-500">{{ $lastCommentBody !== '' ? $lastCommentBody : 'Melampirkan berkas.' }}</span>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-2 text-xs text-slate-400">
                                    <flux:icon name="chat-bubble-left-ellipsis" class="h-3.5 w-3.5" />
                                    <span>Belum ada komentar</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <footer class="relative z-10 flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
                        <div class="flex -space-x-2">
                            @php
                                $maxVisible = 4;
                                $visible = array_slice($assignees, 0, $maxVisible);
                                $remaining = count($assignees) - $maxVisible;
                            @endphp

                            @foreach($visible as $assignee)
                                <flux:avatar name="{{ $assignee }}" circle class="size-7 text-xs ring-2 ring-white" />
                            @endforeach

                            @if($remaining > 0)
                                <flux:avatar circle class="size-7 text-[11px] font-semibold ring-2 ring-white bg-slate-100 text-slate-600">
                                    +{{ $remaining }}
                                </flux:avatar>
                            @endif
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($ownerName)
                                <span class="inline-flex max-w-32 items-center gap-1 truncate text-[11px] font-medium text-slate-500">
                                    <flux:icon name="user" class="h-3 w-3 shrink-0" />
                                    <span class="truncate">{{ $ownerName }}</span>
                                </span>
                            @endif
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400">
                                <flux:icon name="clock" class="h-3 w-3" />
                                {{ \Carbon\Carbon::parse($task['start_date'])->subHours(2)->diffForHumans() }}
                            </span>
                        </div>
                    </footer>
                </article>
                @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    Belum ada tugas. Klik <span class="font-semibold">Tambah Tugas</span> untuk membuat yang baru.
                </div>
                @endforelse

                @if (! empty($this->tasks))
                <div x-show="visible === 0" x-cloak class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    Tidak ada tugas pada filter ini.
                </div>
                @endif

                {{-- Infinite scroll: sentinel dipantau IntersectionObserver (Alpine x-intersect) --}}
                @if ($hasMore)
                <div wire:key="dar-scroll-sentinel" x-intersect.margin.300px="$wire.loadMore()"
                    class="col-span-full flex items-center justify-center py-4 text-sm text-slate-500">
                    <span wire:loading.flex wire:target="loadMore" class="items-center gap-2">
                        <flux:icon name="arrow-path" class="h-4 w-4 animate-spin" />
                        Memuat lebih banyak...
                    </span>
                </div>
                @endif
            </div>
            @endif
            <div class="w-1/3" wire:loading wire:target="fetchTasks">
                <div class="rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-sm animate-pulse">
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">

                            {{-- Title + description --}}
                            <div class="flex-1 space-y-2">
                                <div class="h-4 w-3/4 rounded bg-slate-200"></div>
                                <div class="h-3 w-full rounded bg-slate-200"></div>
                                <div class="h-3 w-5/6 rounded bg-slate-200"></div>
                            </div>

                            {{-- Menu button --}}
                            <div class="h-9 w-9 rounded-full bg-slate-200"></div>
                        </div>

                        {{-- Badges --}}
                        <div class="mt-4 flex gap-2">
                            <div class="h-5 w-16 rounded-full bg-slate-200"></div>
                            <div class="h-5 w-20 rounded-full bg-slate-200"></div>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200/70 px-5 py-4">

                        {{-- Avatars --}}
                        <div class="flex -space-x-2">
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                            <div class="h-7 w-7 rounded-full bg-slate-200"></div>
                        </div>

                        {{-- Date --}}
                        <div class="h-3 w-24 rounded bg-slate-200"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <x-confirm-modal name="delete-task" confirm="deleteTask" title="Hapus aktivitas ini?"
        description="Aktivitas DAR akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan." />

    <flux:modal name="create-task" class="min-w-screen overflow-auto md:min-w-3xl lg:min-w-5xl xl:min-w-6xl">
        <form wire:submit="createActivity" class="space-y-5">
            {{-- ── Header ── --}}
            <div class="flex items-start gap-3 border-b border-zinc-100 pb-4">
                <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-zinc-100 text-zinc-700">
                    <flux:icon name="clipboard-document-list" class="h-5 w-5" />
                </div>
                <div>
                    <flux:heading size="lg" class="mb-0!">Buat Tugas Baru</flux:heading>
                    <p class="mt-0.5 text-sm text-zinc-500">Lengkapi detail tugas, jadwal, dan anggota tim.</p>
                </div>
            </div>

            {{-- ── Body grid: 2 kolom di desktop, stacked di mobile ── --}}
            <div class="grid grid-cols-1 gap-x-8 gap-y-5 lg:grid-cols-2">

                {{-- ── Section: Detail (kolom kiri, span 2 di desktop) ── --}}
                <div class="space-y-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Detail Tugas</p>

                    <div>
                        <flux:input wire:model="form.activity" placeholder="Nama tugas" />
                        @error('form.activity')
                        <flux:error message="{{ $message }}" /> @enderror
                    </div>

                    <div>
                        <flux:textarea wire:model="form.description" rows="3" placeholder="Jelaskan lebih detail apa yang akan dikerjakan..." />
                        @error('form.description')
                        <flux:error message="{{ $message }}" /> @enderror
                    </div>
                </div>

                {{-- ── Section: Kategori ── --}}
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Kategori</p>

                    <div class="rounded-xl border bg-zinc-50/60 p-4 transition {{ $form->isproject ? 'border-zinc-300' : 'border-zinc-200' }}">
                        <label class="flex cursor-pointer items-start gap-3">
                            <input wire:model.live="form.isproject" type="checkbox" class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400" />
                            <span class="flex-1">
                                <span class="block text-sm font-semibold text-zinc-900">Kegiatan Project</span>
                                <span class="block text-xs text-zinc-500">Centang jika tugas ini terkait dengan project tertentu.</span>
                            </span>
                        </label>

                        {{-- Project & timeline selectors — hanya muncul kalau checkbox dicentang --}}
                        @if ($form->isproject)
                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Project</label>
                                <flux:select wire:model.live="projectSelected" placeholder="Pilih project...">
                                    <flux:select.option selected>Pilih Project...</flux:select.option>
                                    @foreach ($this->projectData() as $item)
                                    <flux:select.option value="{{ $item['id'] }}">{{ $item['code'] }} - {{ $item['name'] }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('form.project_id')
                                <flux:error message="{{ $message }}" /> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Timeline</label>

                                {{-- Loading selalu di DOM agar wire:loading bisa menampilkannya saat fetch timeline --}}
                                <div wire:loading.flex wire:target="updatedProjectSelected, projectSelected" class="items-center gap-2 rounded-xl bg-zinc-100 px-3 py-2.5 text-xs text-zinc-500">
                                    <flux:icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" />
                                    Memuat timeline...
                                </div>

                                <div wire:loading.remove wire:target="updatedProjectSelected, projectSelected">
                                    @if (! empty($this->timelines))
                                    <flux:select wire:model.live="form.timelines_id" placeholder="Pilih timeline...">
                                        <flux:select.option selected>Pilih Timeline...</flux:select.option>
                                        @foreach ($this->timelines as $item)
                                        <flux:select.option value="{{ $item['id'] }}">{{ $item['title'] }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    @elseif ($projectSelected)
                                    <div class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-700 ring-1 ring-amber-200">
                                        Tidak ada timeline. Buat dulu di menu project.
                                    </div>
                                    @else
                                    <div class="rounded-xl bg-zinc-100 px-3 py-2.5 text-xs text-zinc-500">
                                        Pilih project dulu.
                                    </div>
                                    @endif
                                </div>
                                @error('form.timelines_id')
                                <flux:error message="{{ $message }}" /> @enderror
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- ── Section: Jadwal ── --}}
                <div class="space-y-3">
                    <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Jadwal</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <flux:input wire:model="form.start_date" label="Mulai" type="datetime-local" />
                        <flux:input wire:model="form.end_date" label="Berakhir" type="datetime-local" />
                    </div>
                    @error('form.start_date')
                    <flux:error message="{{ $message }}" /> @enderror
                    @error('form.end_date')
                    <flux:error message="{{ $message }}" /> @enderror
                </div>


                {{-- ── Section: Tim & Status ── --}}
                <div class="space-y-3 lg:col-span-2">
                    <div class="grid grid-cols-2 gap-4 items-start">
                        <div x-data="{ open: false, query: '', matches(name) { return !this.query || name.toLowerCase().includes(this.query.toLowerCase()); } }" @click.away="open = false" @keydown.escape.window="open = false" class="relative">
                            @php
                            $selectedTeam = collect($this->form->team_user ?? [])->map(fn ($id) => (int) $id)->all();
                            $userById = collect($this->users)->keyBy('id');
                            @endphp

                            <div class="mb-1.5 flex items-center justify-between">
                                <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500">Tim</p>
                                <span class="text-[11px] text-zinc-400">{{ count($selectedTeam) }} dipilih</span>
                            </div>

                            {{-- Chip area --}}
                            <div @click="open = true; $nextTick(() => $refs.teamSearch.focus())" class="flex min-h-11.5 cursor-text flex-wrap items-center gap-1.5 rounded-xl border border-zinc-200 bg-white px-2 py-1.5 transition focus-within:border-zinc-400 focus-within:ring-2 focus-within:ring-zinc-200">
                                @foreach ($selectedTeam as $uid)
                                @php $u = $userById[$uid] ?? null; @endphp
                                @if ($u)
                                <span wire:key="create-team-chip-{{ $uid }}" class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200">
                                    <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                    {{ $u['name'] }}
                                    <button type="button" @click.stop="$wire.toggleTeamUser({{ $uid }})" class="grid h-4 w-4 place-items-center rounded-full text-zinc-400 hover:bg-white hover:text-red-600" aria-label="Hapus">
                                        <flux:icon name="x-mark" class="h-3 w-3" />
                                    </button>
                                </span>
                                @endif
                                @endforeach

                                <input x-ref="teamSearch" x-model="query" @focus="open = true" @keydown.enter.prevent type="text" placeholder="@if (empty($selectedTeam)) Pilih anggota tim... @else Tambah anggota lain... @endif" class="min-w-30 flex-1 border-0 bg-transparent px-2 py-1 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-0" />
                            </div>

                            {{-- Dropdown --}}
                            <div x-show="open" x-cloak x-transition.origin.top class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white p-1 shadow-lg ring-1 ring-zinc-200/70">
                                @forelse ($this->users as $u)
                                @php $isSelected = in_array((int) $u['id'], $selectedTeam, true); @endphp
                                <button wire:key="create-team-opt-{{ $u['id'] }}" type="button" x-show="matches('{{ addslashes($u['name']) }}')" @click="$wire.toggleTeamUser({{ $u['id'] }}); query = ''; $refs.teamSearch.focus()" class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ $isSelected ? 'bg-zinc-50' : '' }}">
                                    <span class="inline-flex min-w-0 items-center gap-2">
                                        <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                        <span class="truncate text-zinc-800">{{ $u['name'] }}</span>
                                    </span>
                                    @if ($isSelected)
                                    <flux:icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" />
                                    @endif
                                </button>
                                @empty
                                <div class="px-3 py-2 text-xs text-zinc-500">Tidak ada user.</div>
                                @endforelse
                            </div>

                            @error('form.team_user')
                            <flux:error message="{{ $message }}" /> @enderror
                        </div>

                        {{-- Status awal --}}
                        <div>
                            <p class="text-[11px] mb-1.5  font-semibold uppercase tracking-widest text-zinc-500">Status</p>
                            <flux:select wire:model="form.status" placeholder="Pilih status...">
                                <flux:select.option value="1">Open</flux:select.option>
                                <flux:select.option value="2">Pending</flux:select.option>
                                <flux:select.option value="3">Cancelled</flux:select.option>
                                <flux:select.option value="4">Completed</flux:select.option>
                            </flux:select>
                            @error('form.status')
                            <flux:error message="{{ $message }}" /> @enderror
                        </div>
                    </div>
                </div>

            </div>
            {{-- ── /Body grid ── --}}

            {{-- ── Footer ── --}}
            <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4">
                <flux:modal.close>
                    <flux:button variant="ghost" type="button">Batal</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="createActivity">
                    <span wire:loading.remove wire:target="createActivity">Buat tugas</span>
                    <span wire:loading wire:target="createActivity">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </flux:modal>
</div>
