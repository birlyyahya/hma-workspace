<?php

use App\Models\User;
use App\Notifications\DarCommentReceived;
use App\Services\DarCache;
use App\Services\DarNotifier;
use App\Services\DarWriter;
use App\Services\ProjectCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Lazy;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Masmerise\Toaster\Toaster;

new #[Lazy] #[Layout('components.layouts.app', ['title' => 'DAR - Task Detail'])]
class extends Component
{
    use WithFileUploads;

    public $id;

    public bool $notFound = false;

    public bool $forbidden = false;

    public array $task = [];

    public array $comments = [];

    public array $commentUsers = [];

    public array $logs = [];

    public string $comment = '';

    public array $newFiles = [];

    // Edit task
    public bool $editing = false;

    public string $editActivity = '';

    public string $editDescription = '';

    public int|string $editStatus = 1;

    public string $editStartDate = '';

    public string $editEndDate = '';

    public array $editTeamUser = [];

    public bool $editIsProject = false;

    public ?int $editProjectId = null;

    public ?int $editProjectCategoryId = null;

    public array $availableUsers = [];

    public array $projectData = [];

    public array $editTimelines = [];

    // Edit comment
    public ?int $editingCommentId = null;

    public string $editingCommentBody = '';

    // Delete comment
    public ?int $pendingDeleteCommentId = null;

    public function mount(): void
    {
        $this->loadTask();

        if (empty($this->task)) {
            $this->notFound = true;

            return;
        }

        if (! $this->canViewTask()) {
            $this->forbidden = true;
            $this->task = [];

            return;
        }
    }

    public function placeholder()
    {
        return view('components.placeholder.ph_dar_show');
    }

    /**
     * Data form edit (daftar user untuk team picker & daftar project) dimuat saat
     * tombol edit ditekan agar render awal halaman detail tetap ringan.
     */
    protected function loadEditFormData(): void
    {
        if (empty($this->availableUsers)) {
            $this->availableUsers = User::whereNotIn('role_id', [1, 2])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->toArray();
        }

        if (empty($this->projectData)) {
            try {
                $this->projectData = app(ProjectCache::class)->involvedProjects(Auth::id());
            } catch (\Throwable $e) {
                $this->projectData = [];
                Log::warning('Failed to load project list for DAR edit', ['message' => $e->getMessage()]);
            }
        }
    }

    /**
     * Detail DAR hanya boleh dibuka oleh pemilik task, user yang tergabung di
     * team_user, atau role dengan scope dar 'all'.
     */
    protected function canViewTask(): bool
    {
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        if ($user->viewScopeFor('dar') === 'all') {
            return true;
        }

        if (empty($this->task)) {
            return false;
        }

        $isOwner = (int) ($this->task['user_id'] ?? 0) === (int) $user->id;

        $isMember = collect($this->task['team_user'] ?? [])
            ->contains(fn ($member) => (int) ($member['user_id'] ?? 0) === (int) $user->id);

        return $isOwner || $isMember;
    }

    protected function loadTimelines(?int $projectId): void
    {
        if (! $projectId) {
            $this->editTimelines = [];

            return;
        }

        $this->editTimelines = app(ProjectCache::class)->timelines($projectId);
    }

    public function updatedEditProjectId($value): void
    {
        $this->editProjectCategoryId = null;
        $this->loadTimelines($value ? (int) $value : null);
    }

    public function updatedEditIsProject($value): void
    {
        if (! $value) {
            $this->editProjectId = null;
            $this->editProjectCategoryId = null;
            $this->editTimelines = [];
        }
    }

    protected function loadTask(): void
    {
        $response = app(DarCache::class)->activity((int) $this->id);

        $this->task = $response['data'] ?? [];
        $this->comments = $this->task['comments'] ?? [];

        $userIds = collect($this->comments)->pluck('user_id')->unique()->filter()->values();

        $this->commentUsers = User::with('role')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id')
            ->map(fn ($u) => ['name' => $u->name, 'role_name' => $u->role?->name])
            ->toArray();
    }

    public function loadLogs(): void
    {
        $response = app(DarCache::class)->logs((int) $this->id);
        $this->logs = $response['data'] ?? [];
    }

    public function getUserProperty(): ?User
    {
        return User::find($this->task['user_id'] ?? null);
    }

    public function getTeamUserProperty()
    {
        $teamUserIds = collect($this->task['team_user'] ?? [])
            ->pluck('user_id')
            ->filter()
            ->values();

        return User::whereIn('id', $teamUserIds)->get();
    }

    public function getAllAttachmentsProperty()
    {
        return collect($this->comments)->pluck('files')->flatten(1)->filter();
    }

    public function startEditing(): void
    {
        $this->loadEditFormData();

        $this->editActivity = $this->task['activity'] ?? '';
        $this->editDescription = $this->task['description'] ?? '';
        $this->editStatus = $this->task['status'] ?? 1;
        $this->editStartDate = ! empty($this->task['start_date'])
            ? Carbon::parse($this->task['start_date'])->format('Y-m-d\TH:i')
            : '';
        $this->editEndDate = ! empty($this->task['end_date'])
            ? Carbon::parse($this->task['end_date'])->format('Y-m-d\TH:i')
            : '';
        $this->editTeamUser = collect($this->task['team_user'] ?? [])
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->toArray();

        $this->editProjectId = ! empty($this->task['project_id']) ? (int) $this->task['project_id'] : null;
        $this->editProjectCategoryId = ! empty($this->task['project_category_id']) ? (int) $this->task['project_category_id'] : null;
        $this->editIsProject = (bool) $this->editProjectId;

        $this->loadTimelines($this->editProjectId);

        $this->editing = true;
    }

    public function toggleTeamUser(int $userId): void
    {
        if (in_array($userId, $this->editTeamUser, true)) {
            $this->editTeamUser = array_values(array_filter($this->editTeamUser, fn ($id) => $id !== $userId));
        } else {
            $this->editTeamUser[] = $userId;
        }
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
    }

    public function updateTask(): void
    {
        $rules = [
            'editActivity' => ['required', 'min:3'],
            'editStatus' => ['required', 'integer'],
            'editStartDate' => ['required'],
            'editEndDate' => ['required'],
            'editTeamUser' => ['nullable', 'array'],
            'editTeamUser.*' => ['integer'],
        ];

        if ($this->editIsProject) {
            $rules['editProjectId'] = ['required', 'integer'];
            $rules['editProjectCategoryId'] = ['nullable', 'integer'];
        }

        $this->validate($rules);

        try {
            $teamUser = collect($this->editTeamUser)->map(fn ($id) => (int) $id)->values()->toArray();
            $projectId = $this->editIsProject && $this->editProjectId ? (int) $this->editProjectId : null;
            $categoryId = $this->editIsProject && $this->editProjectCategoryId ? (int) $this->editProjectCategoryId : null;

            $startDate = Carbon::parse($this->editStartDate)->format('Y-m-d H:i:s');
            $endDate = Carbon::parse($this->editEndDate)->format('Y-m-d H:i:s');
            $date = ! empty($this->task['date'])
                ? Carbon::parse($this->task['date'])->format('Y-m-d H:i:s')
                : null;

            $result = app(DarWriter::class)->updateActivity((int) $this->id, [
                'user_id' => $this->task['user_id'] ?? null,
                'activity' => $this->editActivity,
                'description' => $this->editDescription,
                'status' => $this->editStatus,
                'date' => $date,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'team' => $teamUser,
                'team_user' => $teamUser,
                'project_id' => $projectId,
                'timelines_id' => $categoryId,
                'project_category_id' => $categoryId,
            ]);

            if ($result['ok']) {
                $wasClosed = (int) ($this->task['status'] ?? 0) === 4;

                $this->task = [
                    ...$this->task,
                    'activity' => $this->editActivity,
                    'description' => $this->editDescription,
                    'status' => (int) $this->editStatus,
                    'date' => $date,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'team_user' => collect($teamUser)->map(fn ($id) => ['user_id' => $id])->toArray(),
                    'project_id' => $projectId,
                    'project_category_id' => $categoryId,
                ];
                if (! $wasClosed && (int) $this->editStatus === 4) {
                    app(DarNotifier::class)->activityClosed([...$this->task, 'id' => (int) $this->id], (int) Auth::id());
                }

                $this->editing = false;
                $this->loadLogs();
                Toaster::success('Task updated successfully!');

                return;
            }

            if ($result['error'] !== null) {
                Toaster::error('An error occurred while updating the task.');
                Log::error('Failed to update task', ['system' => $result['error']]);

                return;
            }

            Toaster::error(getErrorMessages($result['body']['errors'] ?? []));
        } catch (Exception $e) {
            Toaster::error('An error occurred while updating the task.');
            Log::error('Failed to update task', [
                'system' => $e->getMessage(),
            ]);
        }
    }

    public function markAsDone(): void
    {
        $result = app(DarWriter::class)->updateStatus((int) $this->id, 4);

        if ($result['ok']) {
            $wasClosed = (int) ($this->task['status'] ?? 0) === 4;
            $this->task['status'] = 4;

            if (! $wasClosed) {
                app(DarNotifier::class)->activityClosed([...$this->task, 'id' => (int) $this->id], (int) Auth::id());
            }

            Toaster::success('Task marked as done!');

            return;
        }

        if ($result['error'] !== null) {
            Toaster::error('An error occurred while updating status.');
            Log::error('Failed to mark task as done', ['system' => $result['error']]);

            return;
        }

        Toaster::error($result['body']['message'] ?? 'Failed to update status.');
    }

    public function removeNewFile(int $index): void
    {
        if (isset($this->newFiles[$index])) {
            array_splice($this->newFiles, $index, 1);
        }
    }

    public function addComment(): void
    {
        $this->validate([
            'comment' => ['required_without:newFiles', 'nullable', 'string'],
            'newFiles' => ['nullable', 'array'],
            'newFiles.*' => ['file', 'max:10240'],
        ]);

        try {
            $files = [];

            foreach ($this->newFiles as $upload) {
                $files[] = [
                    'contents' => file_get_contents($upload->getRealPath()),
                    'name' => $upload->getClientOriginalName(),
                ];
            }

            $result = app(DarWriter::class)->addComment(
                (int) $this->id,
                (int) Auth::id(),
                $this->comment,
                $files,
            );

            if ($result['ok']) {
                $userId = Auth::id();

                $newComment = $result['body']['data'] ?? [];

                $taskOwnerId = (int) ($this->task['user_id'] ?? 0);
                $teamUserIds = collect($this->task['team_user'] ?? [])
                    ->pluck('user_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id);

                $allScopeUserIds = User::query()
                    ->where(function ($query) {
                        $query->whereHas('role', fn ($role) => $role->where('slug', 'super-admin'))
                            ->orWhereHas('role.permissions', fn ($permission) => $permission->where('name', 'dar.view.all'));
                    })
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

                $recipientIds = $teamUserIds
                    ->push($taskOwnerId)
                    ->merge($allScopeUserIds)
                    ->filter()
                    ->reject(fn ($id) => $id === (int) $userId)
                    ->unique()
                    ->values();

                if ($recipientIds->isNotEmpty()) {
                    $commenter = Auth::user();
                    $recipients = User::whereIn('id', $recipientIds)->get();

                    Notification::send($recipients, new DarCommentReceived(
                        activityId: (int) $this->id,
                        activityTitle: (string) ($this->task['activity'] ?? ''),
                        commentId: (int) ($newComment['id'] ?? 0),
                        commenterId: (int) $userId,
                        commenterName: (string) ($commenter->name ?? 'Unknown'),
                        body: (string) $this->comment,
                    ));

                    $this->dispatch('darCommentAdded');
                }

                $this->comment = '';
                $this->newFiles = [];
                $this->loadTask();
                $this->dispatch('comment-added');
                Toaster::success('Comment added successfully!');

                return;
            }

            if ($result['error'] !== null) {
                Toaster::error('Ada serror pada server!');
                Log::error('Failed to add comment', ['system' => $result['error']]);

                return;
            }

            Toaster::error(getErrorMessages($result['body']['message'] ?? []));
        } catch (Exception $e) {
            Toaster::error('Ada serror pada server!');
            Log::error('Failed to add comment', [
                'system' => $e->getMessage(),
            ]);
        }
    }

    public function startEditingComment(int $commentId, string $body): void
    {
        $this->editingCommentId = $commentId;
        $this->editingCommentBody = $body;
    }

    public function cancelEditingComment(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentBody = '';
    }

    public function updateComment(): void
    {
        $this->validate([
            'editingCommentBody' => ['required', 'min:1'],
        ]);

        $result = app(DarWriter::class)->updateComment((int) $this->editingCommentId, $this->editingCommentBody);

        if ($result['ok']) {
            $this->comments = array_map(function ($c) {
                if ($c['id'] === $this->editingCommentId) {
                    $c['body'] = $this->editingCommentBody;
                }

                return $c;
            }, $this->comments);

            $this->cancelEditingComment();
            Toaster::success('Comment updated successfully!');

            return;
        }

        if ($result['error'] !== null) {
            Toaster::error('An error occurred while updating the comment.');
            Log::error('Failed to update comment', ['system' => $result['error']]);

            return;
        }

        Toaster::error($result['body']['message'] ?? 'Failed to update comment.');
    }

    public function confirmDeleteComment(int $commentId): void
    {
        $this->pendingDeleteCommentId = $commentId;
        \Flux\Flux::modal('delete-comment-modal')->show();
    }

    public function cancelDeleteComment(): void
    {
        $this->pendingDeleteCommentId = null;
        \Flux\Flux::modal('delete-comment-modal')->close();
    }

    public function deleteComment(): void
    {
        if (! $this->pendingDeleteCommentId) {
            return;
        }

        $commentId = $this->pendingDeleteCommentId;

        $result = app(DarWriter::class)->deleteComment((int) $commentId);

        if ($result['ok']) {
            $this->comments = array_values(array_filter($this->comments, fn ($c) => ($c['id'] ?? null) !== $commentId));
            $this->pendingDeleteCommentId = null;
            \Flux\Flux::modal('delete-comment-modal')->close();
            Toaster::success('Comment deleted successfully!');

            return;
        }

        if ($result['error'] !== null) {
            Toaster::error('An error occurred while deleting the comment.');
            Log::error('Failed to delete comment', ['system' => $result['error']]);

            return;
        }

        Toaster::error(getErrorMessages($result['body']['errors'] ?? []));
    }
}; ?>

<div>
    @if ($notFound)
        <div class="min-h-[60vh] flex items-center justify-center px-4 py-10">
            <x-errors.404 />
        </div>
    @elseif ($forbidden)
        <div class="min-h-[60vh] flex items-center justify-center px-4 py-10">
            <x-errors.403 />
        </div>
    @else
    <style>
        [x-cloak] { display: none !important; }

        /* Description prose styling — survives Tailwind reset */
        .dar-prose { color: rgb(63 63 70); line-height: 1.7; font-size: 0.95rem; }
        .dar-prose p { margin-top: 0.5rem; margin-bottom: 0.5rem; }
        .dar-prose ul { list-style: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
        .dar-prose ol { list-style: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
        .dar-prose li { margin: 0.25rem 0; }
        .dar-prose strong { font-weight: 600; color: rgb(24 24 27); }
        .dar-prose a { color: rgb(37 99 235); text-decoration: underline; }
        .dar-prose blockquote { border-left: 3px solid rgb(228 228 231); padding-left: 1rem; color: rgb(82 82 91); font-style: italic; margin: 0.75rem 0; }
        .dar-prose code { background: rgb(244 244 245); padding: 0.125rem 0.375rem; border-radius: 0.375rem; font-size: 0.85em; }
    </style>

    <div
        x-data="darShow"
        x-on:comment-added.window="scrollToLatestComment()"
        class="min-h-screen bg-linear-to-b from-zinc-50 to-white md:py-4 py-6 lg:px-8"
    >
        <div class="mx-auto max-w-6xl">

            {{-- ── Top bar ── --}}
            <div class="mb-4 flex items-center justify-between gap-3 sm:mb-6 sm:gap-4">
                <div class="flex min-w-0 items-center gap-2 text-sm">
                    <a
                        href="{{ route('dar') }}"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3 py-1.5 font-medium text-zinc-700 ring-1 ring-zinc-200 shadow-sm hover:bg-zinc-50"
                    >
                        <flux:icon name="arrow-left" class="h-4 w-4" />
                        Back
                    </a>
                    <span class="hidden text-zinc-300 sm:inline">/</span>
                    <span class="hidden font-medium text-zinc-500 sm:inline">DAR</span>
                    <span class="text-zinc-300">/</span>
                    <span class="truncate font-semibold text-zinc-800">Task #{{ $task['id'] ?? $id }}</span>
                </div>

                @if (! empty($task))
                    <div x-data="{ open: false }" class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            class="grid h-9 w-9 place-items-center rounded-full bg-white text-zinc-600 ring-1 ring-zinc-200 shadow-sm hover:bg-zinc-50"
                            aria-label="Task actions"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.6" />
                                <circle cx="12" cy="12" r="1.6" />
                                <circle cx="19" cy="12" r="1.6" />
                            </svg>
                        </button>
                        <div
                            x-cloak
                            x-show="open"
                            @click.away="open = false"
                            x-transition.origin.top.right
                            class="absolute right-0 z-20 mt-2 w-48 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-200/70"
                        >
                            @if ($editing)
                                <button wire:click="cancelEditing" @click="open = false" type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50">
                                    <flux:icon name="x-mark" class="h-4 w-4" /> Cancel Edit
                                </button>
                            @else
                                <button wire:click="startEditing" @click="open = false"
                                    wire:loading.attr="disabled" wire:target="startEditing" type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50 disabled:opacity-50">
                                    <flux:icon name="pencil-square" class="h-4 w-4" />
                                    <span wire:loading.remove wire:target="startEditing">Edit task</span>
                                    <span wire:loading wire:target="startEditing" class="animate-pulse">Loading...</span>
                                </button>
                                <button wire:click="markAsDone" @click="open = false" wire:loading.attr="disabled"
                                    wire:target="markAsDone" type="button"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-zinc-50 disabled:opacity-50">
                                    <flux:icon name="check-circle" class="h-4 w-4" />
                                    <span wire:loading.remove wire:target="markAsDone">Mark as done</span>
                                    <span wire:loading wire:target="markAsDone" class="animate-pulse">Updating...</span>
                                </button>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            @if (empty($task))
                <div class="rounded-2xl bg-white p-8 text-center shadow-sm ring-1 ring-zinc-200/70">
                    <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-500">
                        <flux:icon name="exclamation-triangle" class="h-6 w-6" />
                    </div>
                    <h1 class="text-lg font-semibold text-zinc-900">Task not found</h1>
                    <p class="mt-1 text-sm text-zinc-600">Task dengan ID <span class="font-semibold">{{ $id }}</span> tidak ditemukan.</p>
                </div>
            @else
                @php
                    $status = $task['status'] ?? 0;
                    $statusColor = match ($status) {
                        1 => 'bg-blue-100 text-blue-700 ring-blue-200',
                        2 => 'bg-amber-50 text-amber-800 ring-amber-200',
                        3 => 'bg-rose-50 text-rose-700 ring-rose-200',
                        4 => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                        default => 'bg-blue-100 text-blue-700 ring-blue-200',
                    };
                    $statusDot = match ($status) {
                        1 => 'bg-blue-400',
                        2 => 'bg-amber-500',
                        3 => 'bg-rose-500',
                        4 => 'bg-emerald-500',
                        default => 'bg-blue-400',
                    };
                    $statusLabel = match ($status) {
                        1 => 'OPEN',
                        2 => 'PENDING',
                        3 => 'CANCELLED',
                        4 => 'CLOSED',
                        default => 'OPEN',
                    };

                    $duration = null;
                    if (! empty($task['start_date']) && ! empty($task['end_date'])) {
                        $start = Carbon::parse($task['start_date']);
                        $end = Carbon::parse($task['end_date']);
                        $duration = $start->diff($end)->format('%h jam %i menit');
                    }
                @endphp

                <div class="grid grid-cols-1 gap-4 sm:gap-5 lg:grid-cols-3">
                    {{-- ── LEFT: Task content ── --}}
                    <article class="lg:col-span-2 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200/70 sm:p-8">
                        @if ($editing)
                            {{-- ── Edit Mode ── --}}
                            <div class="space-y-6">
                                {{-- Section header --}}
                                <div class="flex items-start justify-between gap-3 border-b border-zinc-100 pb-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-zinc-100 text-zinc-600">
                                            <flux:icon name="pencil-square" class="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-zinc-900">Edit Task</p>
                                            <p class="text-xs text-zinc-500">Perbarui detail, jadwal, dan anggota tim.</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Title --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Title</label>
                                    <input wire:model="editActivity" type="text"
                                        class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-base font-semibold text-zinc-900 placeholder:text-zinc-400 focus:border-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-200"
                                        placeholder="Apa yang dikerjakan?" />
                                    @error('editActivity')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>

                                {{-- Status + Dates --}}
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Status</label>
                                        <select wire:model="editStatus"
                                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-800 focus:border-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-200">
                                            <option value="1">OPEN</option>
                                            <option value="2">PENDING</option>
                                            <option value="3">CANCELLED</option>
                                            <option value="4">CLOSED</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Start</label>
                                        <input wire:model="editStartDate" type="datetime-local"
                                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-800 focus:border-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-200" />
                                        @error('editStartDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">End</label>
                                        <input wire:model="editEndDate" type="datetime-local"
                                            class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2.5 text-sm text-zinc-800 focus:border-zinc-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-zinc-200" />
                                        @error('editEndDate')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                {{-- Project category --}}
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/60 p-4">
                                    <label class="flex cursor-pointer items-start gap-3">
                                        <input
                                            wire:model.live="editIsProject"
                                            type="checkbox"
                                            class="mt-0.5 h-4 w-4 cursor-pointer rounded border-zinc-300 text-zinc-900 focus:ring-zinc-400"
                                        />
                                        <span class="flex-1">
                                            <span class="block text-sm font-semibold text-zinc-900">Kegiatan Project</span>
                                            <span class="block text-xs text-zinc-500">Centang jika tugas ini terkait dengan project tertentu.</span>
                                        </span>
                                    </label>

                                    @if ($editIsProject)
                                        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Project</label>
                                                <select wire:model.live="editProjectId"
                                                    class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-800 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200">
                                                    <option value="">— Pilih project —</option>
                                                    @foreach ($projectData as $proj)
                                                        <option value="{{ $proj['id'] }}">{{ $proj['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                @error('editProjectId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                            <div>
                                                <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Timeline / Category</label>

                                                {{-- Loading selalu di DOM agar wire:loading bisa menampilkannya saat fetch timeline --}}
                                                <div wire:loading.flex wire:target="editProjectId,updatedEditProjectId" class="items-center gap-2 rounded-xl bg-zinc-100 px-3 py-2.5 text-xs text-zinc-500">
                                                    <flux:icon name="arrow-path" class="h-3.5 w-3.5 animate-spin" />
                                                    Memuat timeline...
                                                </div>

                                                <div wire:loading.remove wire:target="editProjectId,updatedEditProjectId">
                                                    @if ($editProjectId && empty($editTimelines))
                                                        <div class="rounded-xl bg-amber-50 px-3 py-2.5 text-xs text-amber-700 ring-1 ring-amber-200">
                                                            Tidak ada timeline untuk project ini.
                                                        </div>
                                                    @else
                                                        <select wire:model="editProjectCategoryId" @disabled(! $editProjectId)
                                                            class="w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-zinc-800 focus:border-zinc-400 focus:outline-none focus:ring-2 focus:ring-zinc-200 disabled:cursor-not-allowed disabled:opacity-50">
                                                            <option value="">— Pilih timeline —</option>
                                                            @foreach ($editTimelines as $tl)
                                                                <option value="{{ $tl['id'] }}">{{ $tl['title'] ?? $tl['name'] ?? '#' . $tl['id'] }}</option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                </div>
                                                @error('editProjectCategoryId')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Team picker --}}
                                @php
                                    $userMap = collect($availableUsers)->keyBy('id');
                                @endphp
                                <div
                                    x-data="teamPicker"
                                    @click.away="open = false"
                                    @keydown.escape.window="open = false"
                                    class="relative"
                                >
                                    <div class="mb-1.5 flex items-center justify-between">
                                        <label class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Team Members</label>
                                        <span class="text-[11px] text-zinc-400">{{ count($editTeamUser) }} dipilih</span>
                                    </div>

                                    <div
                                        @click="open = true; $nextTick(() => $refs.search.focus())"
                                        class="flex min-h-11.5 cursor-text flex-wrap items-center gap-1.5 rounded-xl border border-zinc-200 bg-zinc-50 px-2 py-1.5 transition focus-within:border-zinc-400 focus-within:bg-white focus-within:ring-2 focus-within:ring-zinc-200"
                                    >
                                        @foreach ($editTeamUser as $uid)
                                            @php $u = $userMap[$uid] ?? null; @endphp
                                            @if ($u)
                                                <span wire:key="team-chip-{{ $uid }}"
                                                    class="inline-flex items-center gap-1.5 rounded-full bg-white px-2 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200">
                                                    <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                                    {{ $u['name'] }}
                                                    <button
                                                        type="button"
                                                        @click.stop="$wire.toggleTeamUser({{ $uid }})"
                                                        class="grid h-4 w-4 place-items-center rounded-full text-zinc-400 hover:bg-zinc-100 hover:text-red-600"
                                                        aria-label="Remove"
                                                    >
                                                        <flux:icon name="x-mark" class="h-3 w-3" />
                                                    </button>
                                                </span>
                                            @endif
                                        @endforeach

                                        <input
                                            x-ref="search"
                                            x-model="query"
                                            @focus="open = true"
                                            @keydown.enter.prevent
                                            type="text"
                                            placeholder="@if (empty($editTeamUser)) Cari & pilih anggota tim... @else Tambah lagi... @endif"
                                            class="min-w-30 flex-1 border-0 bg-transparent px-2 py-1 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-0"
                                        />
                                    </div>

                                    {{-- Dropdown --}}
                                    <div
                                        x-show="open"
                                        x-cloak
                                        x-transition.origin.top
                                        class="absolute left-0 right-0 z-30 mt-1 max-h-64 overflow-y-auto rounded-xl bg-white p-1 shadow-lg ring-1 ring-zinc-200/70"
                                    >
                                        @forelse ($availableUsers as $u)
                                            @php $isSelected = in_array($u['id'], $editTeamUser, true); @endphp
                                            <button
                                                wire:key="team-opt-{{ $u['id'] }}"
                                                type="button"
                                                x-show="matches('{{ addslashes(strtolower($u['name'])) }}')"
                                                @click="$wire.toggleTeamUser({{ $u['id'] }}); query = ''"
                                                class="flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm hover:bg-zinc-50 {{ $isSelected ? 'bg-zinc-50' : '' }}"
                                            >
                                                <span class="inline-flex items-center gap-2 min-w-0">
                                                    <flux:avatar circle name="{{ $u['name'] }}" size="xs" />
                                                    <span class="truncate text-zinc-800">{{ $u['name'] }}</span>
                                                </span>
                                                @if ($isSelected)
                                                    <flux:icon name="check" class="h-4 w-4 shrink-0 text-emerald-600" />
                                                @endif
                                            </button>
                                        @empty
                                            <div class="px-3 py-2 text-xs text-zinc-500">Tidak ada user tersedia.</div>
                                        @endforelse
                                    </div>

                                    @error('editTeamUser')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>

                                {{-- Description --}}
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-zinc-500">Description</label>
                                    <div x-data="editorComponent(@entangle('editDescription'))" wire:ignore class="dar-editor overflow-hidden rounded-xl border border-zinc-200 bg-white">
                                        <div x-ref="editor"></div>
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-center justify-end gap-2 border-t border-zinc-100 pt-4">
                                    <button wire:click="cancelEditing" type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50">
                                        <flux:icon name="x-mark" class="h-4 w-4" />
                                        Cancel
                                    </button>
                                    <button wire:click="updateTask" wire:loading.attr="disabled" wire:target="updateTask" type="button"
                                        class="inline-flex items-center gap-1.5 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60">
                                        <flux:icon name="check" class="h-4 w-4" />
                                        <span wire:loading.remove wire:target="updateTask">Save Changes</span>
                                        <span wire:loading wire:target="updateTask" class="animate-pulse">Saving...</span>
                                    </button>
                                </div>
                            </div>
                        @else
                            {{-- View mode --}}
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusColor }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                                        {{ $statusLabel }}
                                    </span>
                                    <span class="text-xs text-zinc-400">·</span>
                                    <span class="text-xs font-medium text-zinc-500">Task #{{ $task['id'] ?? $id }}</span>
                                    @if (! empty($task['project_id']))
                                        @php
                                            $projName = collect($projectData)->firstWhere('id', $task['project_id'])['name'] ?? null;
                                        @endphp
                                        <span class="text-xs text-zinc-400">·</span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                            <flux:icon name="briefcase" class="h-3 w-3" />
                                            {{ $projName ?? 'Project #' . $task['project_id'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-400">·</span>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-zinc-50 px-2 py-0.5 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200">
                                            Non-project
                                        </span>
                                    @endif
                                </div>

                                <h1 class="mt-3 text-xl font-semibold tracking-tight text-zinc-900 sm:text-3xl">
                                    {{ $task['activity'] }}
                                </h1>

                                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-zinc-600">
                                    <span class="inline-flex items-center gap-2">
                                        <flux:avatar circle name="{{ $this->user->name ?? 'Unknown' }}" size="xs" />
                                        <span class="font-medium text-zinc-800">{{ $this->user->name ?? 'Unknown' }}</span>
                                    </span>
                                    @if (! empty($task['start_date']))
                                        <span class="inline-flex items-center gap-1.5 text-zinc-500">
                                            <flux:icon name="calendar" class="h-4 w-4" />
                                            {{ Carbon::parse($task['start_date'])->format('d M Y · H:i') }}
                                            @if (! empty($task['end_date']))
                                                <span class="text-zinc-300">→</span>
                                                {{ Carbon::parse($task['end_date'])->format('H:i') }}
                                            @endif
                                        </span>
                                    @endif
                                    @if ($duration)
                                        <span class="inline-flex items-center gap-1.5 text-zinc-500">
                                            <flux:icon name="clock" class="h-4 w-4" />
                                            {{ $duration }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 border-t border-zinc-100 pt-6 sm:pb-20">
                                <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Description</h2>
                                @if (! empty($task['description']))
                                    <div class="dar-prose">{!! $task['description'] !!}</div>
                                @else
                                    <p class="text-sm italic text-zinc-400">No description provided.</p>
                                @endif
                            </div>

                            @if ($this->teamUser->isNotEmpty())
                                <div class="mt-6 border-t border-zinc-100 pt-6">
                                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">Team</h2>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($this->teamUser as $user)
                                            <span class="inline-flex items-center gap-2 rounded-full bg-zinc-50 px-3 py-1 text-xs font-medium text-zinc-700 ring-1 ring-zinc-200">
                                                <flux:avatar circle name="{{ $user->name }}" size="xs" />
                                                {{ $user->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    </article>

                    {{-- ── RIGHT: Sidebar ── --}}
                    <aside class="space-y-4 sm:space-y-5">
                        {{-- Status card --}}
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200/70 sm:p-5">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Details</h3>
                            <dl class="mt-3 space-y-3 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-zinc-500">Status</dt>
                                    <dd>
                                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $statusColor }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                                            {{ $statusLabel }}
                                        </span>
                                    </dd>
                                </div>
                                @if (! empty($task['start_date']))
                                    <div class="flex items-center justify-between">
                                        <dt class="text-zinc-500">Start</dt>
                                        <dd class="font-medium text-zinc-800">{{ Carbon::parse($task['start_date'])->format('d M, H:i') }}</dd>
                                    </div>
                                @endif
                                @if (! empty($task['end_date']))
                                    <div class="flex items-center justify-between">
                                        <dt class="text-zinc-500">End</dt>
                                        <dd class="font-medium text-zinc-800">{{ Carbon::parse($task['end_date'])->format('d M, H:i') }}</dd>
                                    </div>
                                @endif
                                @if ($duration)
                                    <div class="flex items-center justify-between">
                                        <dt class="text-zinc-500">Duration</dt>
                                        <dd class="font-medium text-zinc-800">{{ $duration }}</dd>
                                    </div>
                                @endif
                                <div class="flex items-center justify-between">
                                    <dt class="text-zinc-500">Comments</dt>
                                    <dd class="font-medium text-zinc-800">{{ count($comments) }}</dd>
                                </div>
                            </dl>
                        </div>

                        {{-- Attachments overview --}}
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-zinc-200/70 sm:p-5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Attachments</h3>
                                <span class="text-xs font-semibold text-zinc-400">{{ $this->allAttachments->count() }}</span>
                            </div>

                            @if ($this->allAttachments->isNotEmpty())
                                <div class="mt-4 space-y-2 max-h-40 overflow-auto">
                                    @foreach ($this->allAttachments as $index => $item)
                                        <div wire:key="att-{{ $item['id'] ?? $item['url'] ?? $item['filename'] ?? $index }}">
                                            @include('livewire.dar.partials.attachment-card', ['file' => $item, 'variant' => 'card'])
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-3 text-sm text-zinc-500">No attachments yet.</p>
                            @endif
                        </div>
                    </aside>
                </div>

                {{-- ── Comments section ── --}}
                <section wire:init="loadLogs" class="mt-4 rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200/70 sm:mt-5">
                    <header class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-4 sm:gap-3 sm:px-5">
                        <div class="flex items-center gap-2">
                            <flux:icon name="chat-bubble-left-right" class="h-4 w-4 text-zinc-500" />
                            <h2 class="text-sm font-semibold text-zinc-900">Comments</h2>
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-semibold text-zinc-600">{{ count($comments) }}</span>
                        </div>

                        <flux:modal.trigger name="activity-log-flyout">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-zinc-600 ring-1 ring-zinc-200 shadow-sm hover:bg-zinc-50"
                            >
                                <flux:icon name="clock" class="h-3.5 w-3.5" />
                                <span class="hidden sm:inline">Riwayat Perubahan</span>
                                <span class="sm:hidden">Riwayat</span>
                                <span class="rounded-full bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold text-zinc-600">{{ count($logs) }}</span>
                            </button>
                        </flux:modal.trigger>
                    </header>

                    {{-- ── Compose (di atas list, ala Instagram) ── --}}
                    <div class="border-b border-zinc-100 px-4 py-4 sm:px-5">
                        <div
                            x-data="{ dragging: false }"
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                            :class="dragging ? 'ring-2 ring-zinc-400 bg-zinc-50' : 'ring-1 ring-zinc-200'"
                            class="rounded-2xl bg-white transition focus-within:ring-2 focus-within:ring-zinc-300"
                        >
                            <div class="flex items-start gap-3 px-4 pt-3">
                                <flux:avatar circle name="{{ Auth::user()->name }}" size="sm" class="shrink-0" />
                                <textarea
                                    wire:model="comment"
                                    rows="2"
                                    @keydown.meta.enter="$wire.addComment()"
                                    @keydown.ctrl.enter="$wire.addComment()"
                                    class="block w-full resize-none border-0 bg-transparent py-1.5 text-sm text-zinc-800 placeholder:text-zinc-400 focus:outline-none focus:ring-0"
                                    placeholder="Tulis komentar... (⌘+Enter to send)"
                                ></textarea>
                            </div>

                            {{-- File previews --}}
                            @if (! empty($newFiles))
                                <div class="mx-4 mt-2 flex flex-wrap gap-2 rounded-xl bg-zinc-50 p-2">
                                    @foreach ($newFiles as $i => $upload)
                                        @php
                                            $fname = $upload->getClientOriginalName();
                                            $isImg = isImageFile($fname, $upload->getMimeType());
                                            $meta = fileExtMeta($fname);
                                        @endphp
                                        <div wire:key="newfile-{{ $i }}" class="relative flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 pr-7 text-xs">
                                            @if ($isImg)
                                                <img src="{{ $upload->temporaryUrl() }}" alt="" class="h-7 w-7 rounded object-cover" />
                                            @else
                                                <span class="grid h-7 w-7 place-items-center rounded text-[9px] font-bold {{ $meta['bg'] }} {{ $meta['text'] }}">
                                                    {{ $meta['label'] }}
                                                </span>
                                            @endif
                                            <span class="max-w-35 truncate font-medium text-zinc-700">{{ $fname }}</span>
                                            <span class="text-zinc-400">{{ formatFileSize($upload->getSize()) }}</span>
                                            <button
                                                type="button"
                                                wire:click="removeNewFile({{ $i }})"
                                                class="absolute right-1 top-1 grid h-5 w-5 place-items-center rounded-full text-zinc-400 hover:bg-zinc-100 hover:text-red-600"
                                                aria-label="Remove"
                                            >
                                                <flux:icon name="x-mark" class="h-3 w-3" />
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @error('newFiles.*') <p class="mx-4 mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('comment') <p class="mx-4 mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                            {{-- Action bar --}}
                            <div class="flex items-center justify-between gap-2 border-t border-zinc-100 px-3 py-2">
                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-zinc-600 hover:bg-zinc-50">
                                    <flux:icon name="paper-clip" class="h-4 w-4" />
                                    <span>Attach</span>
                                    <input
                                        x-ref="fileInput"
                                        type="file"
                                        wire:model="newFiles"
                                        multiple
                                        class="hidden"
                                    />
                                </label>

                                <div class="flex items-center gap-2">
                                    <span wire:loading wire:target="newFiles" class="text-xs text-zinc-500">Uploading...</span>
                                    <button
                                        wire:click="addComment"
                                        wire:loading.attr="disabled"
                                        wire:target="addComment,newFiles"
                                        type="button"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-900 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-zinc-800 disabled:opacity-60"
                                    >
                                        <span wire:loading.remove wire:target="addComment">Send</span>
                                        <span wire:loading wire:target="addComment" class="animate-pulse">Sending...</span>
                                        <flux:icon name="paper-airplane" class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p class="mt-2 text-[11px] text-zinc-400">
                            Drag &amp; drop file ke area di atas, atau klik <span class="font-medium">Attach</span>. Max 10MB per file.
                        </p>
                    </div>

                    @php
                        $sortedComments = collect($comments)
                            ->sortByDesc(fn ($c) => ! empty($c['created_at']) ? Carbon::parse($c['created_at'])->timestamp : 0)
                            ->values();
                    @endphp

                    {{-- Comment list (terbaru paling atas) --}}
                    <div x-ref="commentList" class="divide-y divide-zinc-100">
                        {{-- Loading skeleton --}}
                        <div wire:loading.flex wire:target="addComment" class="hidden gap-3 px-4 py-4 sm:px-5">
                            <div class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-zinc-100"></div>
                            <div class="flex-1 space-y-2">
                                <div class="h-3 w-24 animate-pulse rounded bg-zinc-100"></div>
                                <div class="h-10 w-3/4 animate-pulse rounded-xl bg-zinc-100"></div>
                            </div>
                        </div>

                        @forelse ($sortedComments as $c)
                            @include('livewire.dar.partials.comment-item', [
                                'c' => $c,
                                'cu' => $commentUsers[$c['user_id']] ?? null,
                                'isOwn' => ($c['user_id'] ?? null) === Auth::id() || Auth::user()->level >= 90,
                                'isEditing' => $editingCommentId !== null && isset($c['id']) && $editingCommentId === $c['id'],
                            ])
                        @empty
                            <div class="px-4 py-10 text-center sm:px-5">
                                <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-2xl bg-zinc-100 text-zinc-400">
                                    <flux:icon name="chat-bubble-left-right" class="h-6 w-6" />
                                </div>
                                <p class="text-sm font-medium text-zinc-700">Belum ada komentar</p>
                                <p class="mt-1 text-xs text-zinc-500">Mulai diskusi dengan menulis komentar pertama.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                {{-- ── Activity log flyout ── --}}
                @php
                    $fieldLabels = [
                        'activity' => 'Judul',
                        'description' => 'Deskripsi',
                        'status' => 'Status',
                        'start_date' => 'Tanggal Mulai',
                        'end_date' => 'Tanggal Selesai',
                        'team_user' => 'Anggota Tim',
                        'team' => 'Anggota Tim',
                        'project_id' => 'Project',
                        'project_category_id' => 'Timeline',
                        'timelines_id' => 'Timeline',
                    ];

                    $statusMap = [1 => 'OPEN', 2 => 'PENDING', 3 => 'CANCELLED', 4 => 'CLOSED'];

                    $formatLogValue = function ($field, $val) use ($statusMap) {
                        if ($val === null || $val === '' || $val === []) {
                            return '—';
                        }
                        if ($field === 'status') {
                            return $statusMap[(int) $val] ?? (string) $val;
                        }
                        if (in_array($field, ['start_date', 'end_date'], true)) {
                            try {
                                return Carbon::parse($val)->format('d M Y · H:i');
                            } catch (\Throwable) {
                                return (string) $val;
                            }
                        }
                        if (is_array($val)) {
                            return implode(', ', array_map(fn ($v) => is_array($v) ? json_encode($v) : (string) $v, $val));
                        }
                        $str = strip_tags((string) $val);
                        return mb_strlen($str) > 80 ? mb_substr($str, 0, 80) . '…' : $str;
                    };

                    $sortedLogs = collect($logs)
                        ->sortByDesc(fn ($l) => ! empty($l['created_at']) ? Carbon::parse($l['created_at'])->timestamp : 0)
                        ->values();
                @endphp

                <flux:modal name="activity-log-flyout" class="w-md max-w-full" flyout>
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">Riwayat Perubahan</flux:heading>
                            <flux:text class="mt-1">Semua perubahan pada task ini, terbaru di atas.</flux:text>
                        </div>

                        @if ($sortedLogs->isEmpty())
                            <p class="py-4 text-center text-sm text-zinc-400">Belum ada riwayat perubahan.</p>
                        @else
                            <ol>
                                @foreach ($sortedLogs as $log)
                                    @php
                                        $logAt = ! empty($log['created_at']) ? Carbon::parse($log['created_at']) : null;
                                        $changes = $log['changes'] ?? [];
                                        $action = $log['action'] ?? 'updated';
                                        $label = $log['label'] ?? match ($action) {
                                            'created' => 'Task dibuat',
                                            'deleted' => 'Task dihapus',
                                            default => 'Data diubah',
                                        };
                                        $iconName = match ($action) {
                                            'created' => 'sparkles',
                                            'deleted' => 'trash',
                                            default => 'pencil-square',
                                        };
                                        $nodeColor = match ($action) {
                                            'created' => 'bg-emerald-50 text-emerald-600 ring-emerald-200',
                                            'deleted' => 'bg-red-50 text-red-600 ring-red-200',
                                            default => 'bg-blue-50 text-blue-600 ring-blue-200',
                                        };
                                    @endphp

                                    <li wire:key="flyout-log-{{ $log['id'] ?? 'i'.$loop->index }}" class="relative flex gap-3 pb-6 last:pb-0">
                                        {{-- Stepper connector --}}
                                        @unless ($loop->last)
                                            <span class="absolute start-3.5 top-8 bottom-1 w-px bg-zinc-200"></span>
                                        @endunless

                                        {{-- Stepper node --}}
                                        <span class="z-10 grid h-7 w-7 shrink-0 place-items-center rounded-full ring-1 {{ $nodeColor }}">
                                            <flux:icon name="{{ $iconName }}" class="h-3.5 w-3.5" />
                                        </span>

                                        <div class="min-w-0 flex-1 pt-1">
                                            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                                <p class="text-sm font-medium text-zinc-900">{{ $label }}</p>
                                                @if ($logAt)
                                                    <span class="text-xs text-zinc-500" title="{{ $logAt->format('d M Y, H:i') }}">{{ $logAt->diffForHumans() }}</span>
                                                @endif
                                            </div>

                                            @if (! empty($changes) && is_array($changes))
                                                <div class="mt-2 divide-y divide-zinc-100 overflow-hidden rounded-lg border border-zinc-200">
                                                    @foreach ($changes as $field => $diff)
                                                        @php
                                                            $fieldLabel = $fieldLabels[$field] ?? \Illuminate\Support\Str::headline($field);
                                                            $oldVal = $formatLogValue($field, $diff['old'] ?? null);
                                                            $newVal = $formatLogValue($field, $diff['new'] ?? null);
                                                        @endphp
                                                        <div class="flex flex-col gap-1.5 px-3 py-2.5 sm:flex-row sm:items-baseline sm:gap-3">
                                                            <span class="w-28 shrink-0 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">{{ $fieldLabel }}</span>
                                                            <div class="flex min-w-0 flex-wrap items-center gap-1.5 text-xs">
                                                                <span class="rounded-md bg-red-50 px-2 py-0.5 text-red-600 line-through decoration-red-300 ring-1 ring-red-100">{{ $oldVal }}</span>
                                                                <flux:icon name="arrow-right" class="h-3 w-3 shrink-0 text-zinc-400" />
                                                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 font-medium text-emerald-700 ring-1 ring-emerald-100">{{ $newVal }}</span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </flux:modal>
            @endif
        </div>

        {{-- ── Full-page loading overlay (saat startEditing) ── --}}
        <div
            wire:loading.flex
            wire:target="startEditing"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-zinc-900/40 backdrop-blur-sm"
        >
            <div class="flex items-center gap-3 rounded-2xl bg-white px-5 py-3 shadow-lg ring-1 ring-zinc-200">
                <svg class="h-5 w-5 animate-spin text-zinc-700" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v3a5 5 0 0 0-5 5H4z"></path>
                </svg>
                <span class="text-sm font-medium text-zinc-700">Memuat editor task...</span>
            </div>
        </div>

        {{-- ── Delete comment confirmation modal ── --}}
        <x-confirm-modal name="delete-comment-modal" confirm="deleteComment" title="Hapus komentar ini?"
            confirm-label="Hapus komentar"
            description="Komentar dan seluruh lampiran terkait akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan." />

        {{-- ── Image lightbox ── --}}
        <div
            x-data="{ open: false, url: '', name: '' }"
            x-on:open-lightbox.window="open = true; url = $event.detail.url; name = $event.detail.name"
            x-on:keydown.escape.window="open = false"
            x-show="open"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click.self="open = false"
        >
            <button
                @click="open = false"
                class="absolute right-4 top-4 grid h-10 w-10 place-items-center rounded-full bg-white/10 text-white backdrop-blur hover:bg-white/20"
                aria-label="Close"
            >
                <flux:icon name="x-mark" class="h-5 w-5" />
            </button>
            <div class="max-h-full max-w-5xl">
                <img :src="url" :alt="name" class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl" />
                <p class="mt-3 text-center text-sm text-white/80" x-text="name"></p>
            </div>
        </div>
    </div>

    @assets
    {{-- CKEditor di-bundle lewat resources/js/app.js (window.ClassicEditor), bukan CDN. --}}
    <style>
        .dar-editor .ck.ck-toolbar {
            border: none;
            border-bottom: 1px solid #e4e4e7;
            background: #fafafa;
        }

        .dar-editor .ck.ck-editor__editable_inline {
            border: none !important;
            box-shadow: none !important;
            font-size: 0.875rem;
            min-height: 150px;
            max-height: 360px;
            overflow-y: auto;
        }

        .dar-editor .ck .ck-placeholder::before {
            color: #a1a1aa;
        }
    </style>
    @endassets

    @script
    <script>
        Alpine.data('teamPicker', () => ({
            open: false,
            query: '',
            matches(name) {
                if (!this.query) return true;
                return name.includes(this.query.toLowerCase());
            },
        }));

        Alpine.data('darShow', () => ({
            scrollToLatestComment() {
                this.$nextTick(() => {
                    const list = this.$refs.commentList;
                    if (!list) return;
                    const items = list.querySelectorAll('article');
                    const latest = items[0];
                    if (latest) {
                        latest.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        latest.classList.add('ring-2', 'ring-zinc-300');
                        setTimeout(() => latest.classList.remove('ring-2', 'ring-zinc-300'), 1500);
                    }
                });
            },
        }));

        Alpine.data('editorComponent', (model) => ({
            editor: null,
            value: model,

            init() {
                this.whenEditorReady(() => this.mountEditor());
            },

            destroy() {
                this.editor?.destroy().catch(() => {});
                this.editor = null;
            },

            whenEditorReady(callback, tries = 0) {
                if (window.ClassicEditor) {
                    callback();
                    return;
                }

                if (tries > 200) {
                    console.error('Editor gagal dimuat.');
                    return;
                }

                setTimeout(() => this.whenEditorReady(callback, tries + 1), 50);
            },

            mountEditor() {
                if (this.editor) return;

                ClassicEditor
                    .create(this.$refs.editor, {
                        toolbar: [
                            'heading', '|',
                            'bold', 'italic', '|',
                            'bulletedList', 'numberedList', '|',
                            'link', 'blockQuote', '|',
                            'undo', 'redo',
                        ],
                        placeholder: 'Tulis deskripsi tugas...',
                    })
                    .then((editor) => {
                        this.editor = editor;
                        editor.setData(this.value || '');

                        editor.model.document.on('change:data', () => {
                            this.value = editor.getData();
                        });

                        this.$watch('value', (val) => {
                            if (editor.getData() !== (val || '')) {
                                editor.setData(val || '');
                            }
                        });
                    })
                    .catch((error) => console.error(error));
            },
        }));
    </script>
    @endscript
    @endif
</div>
