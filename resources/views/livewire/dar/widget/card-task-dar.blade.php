<?php

use App\Livewire\Forms\ActivityForm;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {

    public ActivityForm $form;

    public array $tasks = [];
    public array $projectData = [];
    public array $users = [];
    public array $commentUsers = [];
    public $projectSelected = null;
    public array $timelines = [];
    public string $search = '';

    public bool $loading = true;

    public ?int $pendingDeleteId = null;
    public string $pendingDeleteName = '';

    public function mount(): void
    {
        $this->users = User::whereNotIn('role_id', [1, 2])->get()->toArray();

        try {
            $apiProject = rtrim(config('services.api_project'), '/');
            $this->projectData = Http::get($apiProject.'/projects/search?project_leader_id='.Auth::id())->json() ?? [];
        } catch (\Throwable $e) {
            $this->projectData = [];
        }

        $this->fetchTasks();
        $this->resetForm();
    }

    public function updatedProjectSelected(): void
    {
        collect($this->projectData['data'] ?? [])->firstWhere('id', $this->projectSelected);
        $this->timelines = Http::get(config('services.api_project').'timelines/search?project_id='.$this->projectSelected.'&user_id='.Auth::id())->json()['data'] ?? [];
        $this->form->project_id = $this->projectSelected;
    }

    #[On('updatedCardTaskDar')]
    public function fetchTasks(): void
    {
        $this->loading = true;

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');

            if(Auth::user()->role_id < 3){
                $response = Http::timeout(120)->retry(3, 200)->get(
                    $apiIzin.'/global/dar/list?limit=50000&search='.$this->search
                )->json();
            } else {
            $response = Http::timeout(120)->retry(3, 200)->get(
                $apiIzin.'/global/dar/list?team_user='.Auth::id().'&limit=50000&search='.$this->search
            )->json();
            }
            $this->tasks = $response['data'] ?? [];

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
        } catch (\Throwable $e) {
            $this->tasks = [];
            $this->commentUsers = [];
            Toaster::error('Server DAR Error, silahkan coba lagi atau menghubungi tim IT');
            Log::error('DAR list API failed', ['message' => $e->getMessage()]);
        } finally {
            $this->loading = false;
        }
    }

    public function projectData(): array
    {
        return $this->projectData['data'] ?? [];
    }

    public function teamUser($users): array
    {
        $userMap = collect($this->users)->keyBy('id');

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

        try {
            $apiIzin = rtrim(config('services.api_izin'), '/');
            $response = Http::delete($apiIzin . '/global/dar/activity/' . $id);

            $status = method_exists($response, 'status') ? $response->status() : null;
            $body = method_exists($response, 'json') ? $response->json() : null;

            if ($status === 200 || ($body['success'] ?? false)) {
                Toaster::success('Task berhasil dihapus');
                $this->pendingDeleteId = null;
                $this->pendingDeleteName = '';
                Flux::modal('delete-task')->close();
                $this->dispatch('updatedTimeline');
                $this->fetchTasks();
                return;
            }

            Toaster::error('Menghapus task gagal');
            \Log::error('DAR delete API failed', [
                'status' => $status,
                'body' => method_exists($response, 'body') ? $response->body() : $body,
            ]);
        } catch (\Throwable $e) {
            Toaster::error('Server Error saat menghapus task');
            \Log::error('DAR delete API exception', [
                'message' => $e->getMessage(),
            ]);
        }
    }

}; ?>


<div>
    <style>
        [x-cloak] {
            display: none !important;
        }

    </style>

    <section>
        {{-- Basecamp-ish section header --}}
        <header class="px-5 py-4">
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

                <flux:input x-on:keydown.enter="$wire.fetchTasks()" wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Search task..." class="w-full md:w-64" />
            </div>
        </header>

        <div class="px-5 pb-5">
            @if($loading)
            <div class="rounded-2xl bg-white p-6 text-sm text-slate-600 ring-1 ring-slate-200/70 shadow-sm">
                Loading tasks...
            </div>
            @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" wire:loading.remove wire:target="fetchTasks">
                @forelse(collect($tasks)->sortBy('status') as $task)
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
                @endphp

                <article x-data="{ menuOpen: false }" class="group relative overflow-hidden rounded-2xl bg-white ring-1 ring-slate-200/70 shadow-sm transition hover:z-50 hover:-translate-y-0.5 hover:shadow-lg hover:ring-slate-300/70">
                    <a href="{{ $taskUrl }}" wire:navigate class="absolute inset-0 z-0" aria-label="Open task"></a>

                    {{-- Top status accent --}}
                    <div class="h-1 w-full bg-linear-to-r {{ $statusMeta['accent'] }}"></div>

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 relative z-10">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide {{ $statusMeta['text'] }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusMeta['dot'] }}"></span>
                                        {{ $statusMeta['label'] }}
                                    </span>
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
                                    <button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Edit</button>
                                    <button type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Mark as done</button>
                                    <div class="h-px bg-slate-200/70"></div>
                                    <button type="button" @click="menuOpen = false" wire:click="confirmDeleteTask({{ $taskId }}, @js(ucwords($task['activity'] ?? 'Untitled task')))" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                        Delete
                                    </button>
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

                        <span class="inline-flex items-center gap-1 text-[11px] font-medium text-slate-400">
                            <flux:icon name="clock" class="h-3 w-3" />
                            {{ \Carbon\Carbon::parse($task['start_date'])->subHours(2)->diffForHumans() }}
                        </span>
                    </footer>
                </article>
                @empty
                <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">
                    Belum ada tugas. Klik <span class="font-semibold">Tambah Tugas</span> untuk membuat yang baru.
                </div>
                @endforelse
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

    <flux:modal name="delete-task" class="min-w-md" :dismissible="false">
        <div class="space-y-5">
            <div class="flex items-start gap-4">
                <div class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-red-100 text-red-600">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6h18" />
                        <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                        <path d="M10 11v6" />
                        <path d="M14 11v6" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <flux:heading size="lg">Hapus tugas ini?</flux:heading>
                    <flux:text class="mt-1 text-sm text-slate-600">
                        Tugas <span class="font-semibold text-slate-900">"{{ $pendingDeleteName ?: 'Untitled task' }}"</span>
                        akan dihapus secara permanen beserta seluruh aktivitas terkait. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button variant="ghost" wire:click="cancelDeleteTask">Batal</flux:button>
                <flux:button variant="danger" wire:click="deleteTask" wire:loading.attr="disabled" wire:target="deleteTask">
                    <span wire:loading.remove wire:target="deleteTask">Hapus tugas</span>
                    <span wire:loading wire:target="deleteTask">Menghapus...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

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
                                    <flux:select.option value="{{ $item['id'] }}">{{ $item['name'] }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @error('form.project_id')
                                <flux:error message="{{ $message }}" /> @enderror
                            </div>

                            <div>
                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Timeline</label>
                                @if (! empty($this->timelines))
                                <flux:select wire:model.live="form.timelines_id" placeholder="Pilih timeline...">
                                    <flux:select.option selected>Pilih Timeline...</flux:select.option>
                                    @foreach ($this->timelines as $item)
                                    <flux:select.option value="{{ $item['id'] }}">{{ $item['title'] }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                @elseif ($projectSelected)
                                <div wire:loading wire:target="updatedProjectSelected, projectSelected" class="rounded-xl bg-zinc-100 px-3 py-2.5 text-xs text-zinc-500">
                                    Memuat timeline...
                                </div>
                                <div wire:loading.remove wire:target="updatedProjectSelected, projectSelected" class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-700 ring-1 ring-amber-200">
                                    Tidak ada timeline. Buat dulu di menu project.
                                </div>
                                @else
                                <div class="rounded-xl bg-zinc-100 px-3 py-2.5 text-xs text-zinc-500">
                                    Pilih project dulu.
                                </div>
                                @endif
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
