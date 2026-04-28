<?php

use App\Models\User;
use App\Notifications\DarCommentReceived;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new #[Layout('components.layouts.app', ['title' => 'DAR - Task Detail'])]
class extends Component {
    public $id;

    public array $task = [];
    public array $comments = [];
    public array $commentUsers = [];

    public string $comment = '';

    // Edit task
    public bool $editing = false;
    public string $editActivity = '';
    public string $editDescription = '';
    public int|string $editStatus = 1;
    public string $editStartDate = '';
    public string $editEndDate = '';

    // Edit comment
    public ?int $editingCommentId = null;
    public string $editingCommentBody = '';

    public function mount(): void
    {
        $response = Http::get(config('services.api_izin') . "/global/dar/activity?id={$this->id}")->json();

        $this->task = $response['data'] ?? [];
        $this->comments = $this->task['comments'] ?? [];

        $userIds = collect($this->comments)->pluck('user_id')->unique()->filter()->values();

        $this->commentUsers = User::with('role')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id')
            ->map(fn($u) => ['name' => $u->name, 'role_name' => $u->role?->name])
            ->toArray();
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

    public function startEditing(): void
    {
        $this->editActivity = $this->task['activity'] ?? '';
        $this->editDescription = $this->task['description'] ?? '';
        $this->editStatus = $this->task['status'] ?? 1;
        $this->editStartDate = $this->task['start_date']
            ? Carbon::parse($this->task['start_date'])->format('Y-m-d\TH:i')
            : '';
        $this->editEndDate = $this->task['end_date']
            ? Carbon::parse($this->task['end_date'])->format('Y-m-d\TH:i')
            : '';
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
    }

    public function updateTask(): void
    {
        $this->validate([
            'editActivity' => ['required', 'min:3'],
            'editStatus'   => ['required', 'integer'],
            'editStartDate' => ['required'],
            'editEndDate'   => ['required'],
        ]);

        $response = null;

        try {
            $response = Http::post(config('services.api_izin') . "/global/dar/activity/{$this->id}", [
                '_method'     => 'PUT',
                'activity'    => $this->editActivity,
                'description' => $this->editDescription,
                'status'      => $this->editStatus,
                'start_date'  => $this->editStartDate,
                'end_date'    => $this->editEndDate,
            ]);

            if ($response['success']) {
                $this->task = [
                    ...$this->task,
                    'activity'    => $this->editActivity,
                    'description' => $this->editDescription,
                    'status'      => (int) $this->editStatus,
                    'start_date'  => $this->editStartDate,
                    'end_date'    => $this->editEndDate,
                ];
                $this->editing = false;
                Toaster::success('Task updated successfully!');
                return;
            }

            Toaster::error($response['message'] ?? 'Failed to update task.');
        } catch (Exception $e) {
            Toaster::error('An error occurred while updating the task.');
            Log::error('Failed to update task', [
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
                'system' => $e->getMessage(),
            ]);
        }
    }

    public function markAsDone(): void
    {
        $response = null;

        try {
            $response = Http::post(config('services.api_izin') . "/global/dar/activity/{$this->id}/status", [
                '_method' => 'PUT',
                'status'  => 4,
            ]);

            if ($response['success']) {
                $this->task['status'] = 4;
                Toaster::success('Task marked as done!');
                return;
            }

            Toaster::error($response['message'] ?? 'Failed to update status.');
        } catch (Exception $e) {
            Toaster::error('An error occurred while updating status.');
            Log::error('Failed to mark task as done', [
                'body'   => $response['message'] ?? 'No message',
                'system' => $e->getMessage(),
            ]);
        }
    }

    public function addComment(): void
    {
        $response = null;

        try {
            $response = Http::post(config('services.api_izin') . "/global/dar/activity/create-comment", [
                'activity_id' => $this->id,
                'user_id'     => Auth::id(),
                'body'        => $this->comment,
            ]);

            if ($response['success']) {
                $userId = Auth::id();

                if (!isset($this->commentUsers[$userId])) {
                    $user = Auth::user();
                    $this->commentUsers[$userId] = [
                        'name'      => $user->name,
                        'role_name' => $user->role?->name,
                    ];
                }

                $this->comments[] = [
                    'id'          => $response['data']['id'] ?? null,
                    'activity_id' => $this->id,
                    'user_id'     => $userId,
                    'body'        => $this->comment,
                    'created_at'  => now(),
                ];

                $taskOwnerId = (int) ($this->task['user_id'] ?? 0);
                $teamUserIds = collect($this->task['team_user'] ?? [])
                    ->pluck('user_id')
                    ->filter()
                    ->map(fn ($id) => (int) $id);

                $recipientIds = $teamUserIds
                    ->push($taskOwnerId)
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
                        commentId: (int) ($response['data']['id'] ?? 0),
                        commenterId: (int) $userId,
                        commenterName: (string) ($commenter->name ?? 'Unknown'),
                        body: (string) $this->comment,
                    ));

                    $this->dispatch('darCommentAdded');
                }

                $this->comment = '';
                Toaster::success('Comment added successfully!');
                return;
            }

            Toaster::error(getErrorMessages($response['errors']));
        } catch (Exception $e) {
            Toaster::error('An error occurred while creating the comment.');
            Log::error('Failed to add comment', [
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
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

        $response = null;

        try {
            $response = Http::post(config('services.api_izin') . "/global/dar/activity/update-comment/{$this->editingCommentId}", [
                '_method' => 'PUT',
                'body'    => $this->editingCommentBody,
            ]);

            if ($response['success']) {
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

            Toaster::error($response['message'] ?? 'Failed to update comment.');
        } catch (Exception $e) {
            Toaster::error('An error occurred while updating the comment.');
            Log::error('Failed to update comment', [
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
                'system' => $e->getMessage(),
            ]);
        }
    }

    public function deleteComment(int $commentId): void
    {
        $response = null;

        try {
            $response = Http::delete(config('services.api_izin') . "/global/dar/activity/delete-comment/{$commentId}");

            if ($response['success']) {
                $this->comments = array_values(array_filter($this->comments, fn($c) => $c['id'] !== $commentId));
                Toaster::success('Comment deleted successfully!');
                return;
            }

            Toaster::error(getErrorMessages($response['errors']));
        } catch (Exception $e) {
            Toaster::error('An error occurred while deleting the comment.');
            Log::error('Failed to delete comment', [
                'body'   => $response['message'] ?? 'No message',
                'error'  => $response['errors'] ?? 'No error',
                'system' => $e->getMessage(),
            ]);
        }
    }
};


?>

<div>
    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    <div class="min-h-screen bg-linear-to-b from-slate-50 to-slate-100/60 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto">

            {{-- Header --}}
            <div class="mb-6 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <a href="{{ route('dar') }}" class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" />
                        </svg>
                        Back to DAR
                    </a>
                    <div class="text-sm text-slate-400">/</div>
                    <div class="text-sm font-semibold text-slate-700">Task</div>
                </div>

                {{-- Ellipsis menu --}}
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="grid h-10 w-10 place-items-center rounded-full bg-white text-slate-700 ring-1 ring-slate-200 shadow-sm hover:bg-slate-50" aria-label="Task actions">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                            <circle cx="5" cy="12" r="1.6" />
                            <circle cx="12" cy="12" r="1.6" />
                            <circle cx="19" cy="12" r="1.6" />
                        </svg>
                    </button>

                    <div x-cloak x-show="open" @click.away="open = false" x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-44 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200/70">
                        @if($editing)
                            <button wire:click="cancelEditing" @click="open = false" type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                Cancel Edit
                            </button>
                        @else
                            <button wire:click="startEditing" @click="open = false" type="button" class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                Edit
                            </button>
                            <button
                                wire:click="markAsDone"
                                @click="open = false"
                                wire:loading.attr="disabled"
                                wire:target="markAsDone"
                                type="button"
                                class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                            >
                                <span wire:loading.remove wire:target="markAsDone">Mark as done</span>
                                <span wire:loading wire:target="markAsDone" class="animate-pulse">Updating...</span>
                            </button>
                        @endif
                        <div class="h-px bg-slate-200/70"></div>
                        <button type="button" class="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Delete</button>
                    </div>
                </div>
            </div>

            @if(empty($task))
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
                    <h1 class="text-xl font-semibold text-slate-900">Task not found</h1>
                    <p class="mt-2 text-sm text-slate-600">Task dengan ID <span class="font-semibold">{{ $id }}</span> tidak ditemukan.</p>
                </div>
            @else
                @php
                    $status = $task['status'] ?? 0;
                    $statusColor = match ($status) {
                        1 => 'bg-slate-50 text-slate-700 ring-slate-200',
                        2 => 'bg-amber-50 text-amber-800 ring-amber-200',
                        3 => 'bg-blue-50 text-blue-700 ring-blue-200',
                        4 => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                        default => 'bg-slate-50 text-slate-700 ring-slate-200',
                    };
                    $statusLabel = match ($status) {
                        1 => 'Pending',
                        2 => 'Hold',
                        3 => 'In Progress',
                        4 => 'Completed',
                        default => 'Draft',
                    };
                @endphp

                <article class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200/70 sm:p-10">

                    @if($editing)
                        {{-- ── Edit Mode ── --}}
                        <div class="space-y-6">
                            <div class="flex items-center gap-3 pb-4 border-b border-slate-100">
                                <div class="grid h-10 w-10 place-items-center rounded-xl bg-slate-100 text-slate-500 shrink-0">
                                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                    </svg>
                                </div>
                                <p class="text-sm font-semibold text-slate-500 uppercase tracking-widest">Editing Task</p>
                            </div>

                            {{-- Title --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Task Title</label>
                                <input
                                    wire:model="editActivity"
                                    type="text"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-xl font-semibold text-slate-900 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    placeholder="Task title..."
                                />
                                @error('editActivity')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Status + Dates --}}
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Status</label>
                                    <select
                                        wire:model="editStatus"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    >
                                        <option value="1">Pending</option>
                                        <option value="2">Hold</option>
                                        <option value="3">In Progress</option>
                                        <option value="4">Completed</option>
                                    </select>
                                    @error('editStatus')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Start Date</label>
                                    <input
                                        wire:model="editStartDate"
                                        type="datetime-local"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    />
                                    @error('editStartDate')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">End Date</label>
                                    <input
                                        wire:model="editEndDate"
                                        type="datetime-local"
                                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-800 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    />
                                    @error('editEndDate')
                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            {{-- Description --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Description</label>
                                <textarea
                                    wire:model="editDescription"
                                    rows="5"
                                    class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                    placeholder="Task description..."
                                ></textarea>
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center justify-end gap-3 pt-2">
                                <button
                                    wire:click="cancelEditing"
                                    type="button"
                                    class="rounded-xl px-5 py-2.5 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    wire:click="updateTask"
                                    wire:loading.attr="disabled"
                                    wire:target="updateTask"
                                    type="button"
                                    class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="updateTask">Save Changes</span>
                                    <span wire:loading wire:target="updateTask" class="animate-pulse">Saving...</span>
                                </button>
                            </div>
                        </div>

                    @else
                        {{-- ── View Mode ── --}}
                        <div class="text-center">
                            <div class="mx-auto mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-slate-100 text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4.5 6.5l1.2 1.2 2.3-2.7" />
                                    <path d="M4.5 12.5l1.2 1.2 2.3-2.7" />
                                    <path d="M4.5 18.5l1.2 1.2 2.3-2.7" />
                                    <path d="M9 6h11" />
                                    <path d="M9 12h11" />
                                    <path d="M9 18h11" />
                                </svg>
                            </div>

                            <p class="text-xs font-semibold uppercase tracking-widest text-slate-500">Task</p>
                            <h1
                                wire:loading.class="opacity-50"
                                wire:target="markAsDone"
                                class="mt-2 text-3xl font-semibold leading-tight tracking-tight text-slate-900 sm:text-4xl"
                            >
                                {{ $task['activity'] }}
                            </h1>

                            <div class="mt-4 flex flex-wrap items-center justify-center gap-2 text-sm text-slate-500">
                                <span class="inline-flex items-center gap-2">
                                    <flux:avatar circle name="{{ $this->user->name ?? 'Unknown' }}" size="xs" />
                                    <span class="font-semibold text-slate-700">{{ $this->user->name ?? 'Unknown' }}</span>
                                </span>
                                <span>•</span>
                                <span>{{ Carbon::parse($task['start_date'] ?? now())->format('M d') }}</span>
                                <span>•</span>
                                <span>Notified {{ count($task['team_user'] ?? []) }} people</span>
                            </div>

                            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold ring-1 {{ $statusColor }}">
                                    {{ $statusLabel }}
                                </span>
                                @if(!empty($task['end_date']))
                                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M7 3v3" />
                                            <path d="M17 3v3" />
                                            <path d="M3.5 9h17" />
                                            <path d="M5.5 6h13A2 2 0 0 1 20.5 8v12A2 2 0 0 1 18.5 22h-13A2 2 0 0 1 3.5 20V8A2 2 0 0 1 5.5 6Z" />
                                        </svg>
                                        Due {{ Carbon::parse($task['end_date'])->format('d M') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="mt-10 space-y-5 text-lg leading-relaxed text-slate-800">
                            <p>{{ $task['description'] ?? 'No description provided.' }}</p>
                        </div>

                        <div class="mt-10 flex flex-wrap items-center justify-center gap-2">
                            @foreach($this->teamUser as $user)
                                <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 ring-1 ring-slate-200">
                                    <flux:avatar circle name="{{ $user->name }}" size="xs" />
                                    {{ $user->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                </article>

                {{-- Comments Section --}}
                <section class="mt-8 rounded-3xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    <header class="flex items-center justify-between border-b border-slate-200/70 px-6 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">Comments</h2>
                        <span class="text-xs font-semibold text-slate-500">{{ count($comments) }}</span>
                    </header>

                    <div class="divide-y divide-slate-200/70">
                        @forelse($comments as $c)
                            @php $cu = $commentUsers[$c['user_id']] ?? null; @endphp

                            <article class="px-6 py-5" wire:key="comment-{{ $c['id'] }}">
                                <div class="flex items-start gap-4">
                                    <flux:avatar circle name="{{ $cu['name'] ?? 'Unknown' }}" size="sm" class="shrink-0" />

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <span class="font-semibold text-slate-900">{{ $cu['name'] ?? 'Unknown' }}</span>
                                            @if(!empty($cu['role_name']))
                                                <span class="text-slate-500">{{ $cu['role_name'] }}</span>
                                            @endif
                                            <span class="text-slate-400">•</span>
                                            <span class="text-slate-500">{{ Carbon::parse($c['created_at'] ?? now())->format('M d') }}</span>
                                        </div>

                                        @if($editingCommentId === $c['id'])
                                            {{-- Comment edit form --}}
                                            <div class="mt-3 space-y-2">
                                                <textarea
                                                    wire:model="editingCommentBody"
                                                    rows="3"
                                                    class="w-full resize-none rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800 placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-200"
                                                ></textarea>
                                                @error('editingCommentBody')
                                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                                @enderror
                                                <div class="flex items-center gap-2">
                                                    <button
                                                        wire:click="updateComment"
                                                        wire:loading.attr="disabled"
                                                        wire:target="updateComment"
                                                        type="button"
                                                        class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                                                    >
                                                        <span wire:loading.remove wire:target="updateComment">Save</span>
                                                        <span wire:loading wire:target="updateComment" class="animate-pulse">Saving...</span>
                                                    </button>
                                                    <button
                                                        wire:click="cancelEditingComment"
                                                        type="button"
                                                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <p class="mt-3 text-sm leading-relaxed text-slate-700">
                                                {{ $c['body'] ?? '' }}
                                            </p>
                                        @endif
                                    </div>

                                    {{-- Comment actions dropdown --}}
                                    @if($editingCommentId !== $c['id'])
                                        <div x-data="{ open: false }" class="relative shrink-0">
                                            <button type="button" @click="open = !open" class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100 hover:text-slate-700" aria-label="Comment actions">
                                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true">
                                                    <circle cx="5" cy="12" r="1.6" />
                                                    <circle cx="12" cy="12" r="1.6" />
                                                    <circle cx="19" cy="12" r="1.6" />
                                                </svg>
                                            </button>
                                            <div x-cloak x-show="open" @click.away="open = false" x-transition.origin.top.right class="absolute right-0 z-20 mt-2 w-36 overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-slate-200/70">
                                                @if($c['user_id'] === Auth::id())
                                                    <button
                                                        wire:click="startEditingComment({{ $c['id'] }}, '{{ addslashes($c['body'] ?? '') }}')"
                                                        @click="open = false"
                                                        type="button"
                                                        class="w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50"
                                                    >
                                                        Edit
                                                    </button>
                                                    <button
                                                        wire:click="deleteComment({{ $c['id'] }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="deleteComment({{ $c['id'] }})"
                                                        @click="open = false"
                                                        type="button"
                                                        class="w-full px-3 py-2 text-left text-sm text-red-700 hover:bg-red-50 disabled:opacity-50"
                                                    >
                                                        <span wire:loading.remove wire:target="deleteComment({{ $c['id'] }})">Delete</span>
                                                        <span wire:loading wire:target="deleteComment({{ $c['id'] }})" class="animate-pulse">Deleting...</span>
                                                    </button>
                                                @else
                                                    <div class="px-3 py-2 text-xs text-slate-400">No actions</div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <div class="px-6 py-6 text-sm text-slate-600">Belum ada komentar.</div>
                        @endforelse
                    </div>

                    {{-- Add comment --}}
                    <div class="border-t border-slate-200/70 px-6 py-5">
                        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500">Add a comment</label>
                        <div class="mt-2 rounded-2xl ring-1 ring-slate-200/70 focus-within:ring-slate-300 bg-white">
                            <textarea
                                wire:model="comment"
                                rows="3"
                                class="w-full resize-none rounded-2xl border-0 bg-transparent px-4 py-3 text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none"
                                placeholder="Tulis komentar..."
                            ></textarea>
                            <div class="flex items-center justify-end gap-2 px-4 pb-3">
                                <button
                                    wire:click="addComment"
                                    wire:loading.attr="disabled"
                                    wire:target="addComment"
                                    type="button"
                                    class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="addComment">Post</span>
                                    <span wire:loading wire:target="addComment" class="animate-pulse">Posting...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

        </div>
    </div>
</div>
