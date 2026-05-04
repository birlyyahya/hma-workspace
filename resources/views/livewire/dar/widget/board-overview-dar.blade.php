<?php

use App\Notifications\DarCommentReceived;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Masmerise\Toaster\Toaster;

new class extends Component {
    public $messages;
    public $todos;
    public $schedules;

    #[On('updatedTimeline')]
    #[On('darCommentAdded')]
    public function mount(): void
    {
          if(Auth::user()->role_id < 3){
              $response = Http::timeout(120)->retry(3, 200)->get(env('API_IZIN').'/global/dar/list?limit=1000000')->json();
          }else {
              $response = Http::timeout(120)->retry(3, 200)->get(env('API_IZIN').'/global/dar/list?limit=1000000&team_user='.Auth::id())->json();
          }

        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $collection = collect($response['data'])
            ->whereIn('status', [1, 2, 3, 4])
            ->map(function ($item) use ($today, $todayEnd) {
                $endDate = ! empty($item['end_date']) ? \Carbon\Carbon::parse($item['end_date']) : null;
                $endPassed = $endDate ? $endDate->lt($today) : false;
                $endIsToday = $endDate ? $endDate->between($today, $todayEnd) : false;

                return [
                    'id' => $item['id'],
                    'activity' => $item['activity'],
                    'status' => $item['status'],
                    'project_id' => $item['project_id'],
                    'end_passed' => $endPassed,
                    'end_is_today' => $endIsToday,
                ];
            })
            ->filter(function ($item) {
                $isDone = (int) $item['status'] === 4;

                if (! $isDone) {
                    return true;
                }

                return ! $item['end_passed'];
            })
            ->values();

        $user = Auth::user();

        $this->messages = $this->loadMessages();

        [$project, $nonproject] = $collection->partition(function ($item) {
            return !is_null($item['project_id']);
        });

        $this->todos = [
            'project' => $project->values()->all(),
            'nonproject' => $nonproject->values()->all(),
        ];

        $scheduleFrom = $today->copy()->subDays(2);

        $this->schedules = collect($response['data'])
            ->map(function ($item) {
                $reference = $item['date'] ?? $item['start_date'] ?? null;

                return (object) [
                    'title' => $item['activity'],
                    'description' => $item['description'],
                    'date' => $reference ? \Carbon\Carbon::parse($reference) : null,
                    'start_date' => ! empty($item['start_date']) ? \Carbon\Carbon::parse($item['start_date']) : null,
                    'end_date' => ! empty($item['end_date']) ? \Carbon\Carbon::parse($item['end_date']) : null,
                    'status' => $item['status'],
                    'project_id' => $item['project_id'],
                ];
            })
            ->filter(fn ($s) => $s->date && $s->date->copy()->startOfDay()->gte($scheduleFrom))
            ->sortBy(fn ($s) => $s->date->timestamp)
            ->values();
    }

    private function loadMessages()
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        if ($user->role_id < 3) {
            $query = \Illuminate\Notifications\DatabaseNotification::query()
                ->where('type', DarCommentReceived::class);
        } else {
            $query = $user->notifications()
                ->where('type', DarCommentReceived::class);
        }

        return $query->latest()
            ->limit(50)
            ->get()
            ->groupBy(fn ($n) => $n->data['activity_id'] ?? null)
            ->map(function ($group) {
                $latest = $group->first();
                $data = $latest->data;
                $unread = $group->whereNull('read_at')->count();

                return (object) [
                    'id' => $latest->id,
                    'title' => $data['commenter_name'] ?? 'Unknown',
                    'activity' => $data['activity_title'] ?? '',
                    'description' => $data['body'] ?? '',
                    'replies' => $group->count(),
                    'unread' => $unread,
                    'created_at' => $latest->created_at,
                    'read_at' => $latest->read_at,
                    'activity_id' => $data['activity_id'] ?? null,
                ];
            })
            ->values();
    }

    public function refreshMessages(): void
    {
        $this->messages = $this->loadMessages();
    }

    public function openMessage(int $activityId): void
    {
        $user = Auth::user();

        if ($user) {
            $user->unreadNotifications()
                ->where('type', DarCommentReceived::class)
                ->get()
                ->filter(fn ($n) => (int) ($n->data['activity_id'] ?? 0) === $activityId)
                ->each->markAsRead();
        }

        $this->redirectRoute('dar.dar-show', ['id' => $activityId], navigate: true);
    }

    public function toggleTodo($id)
    {
        $todo = collect($this->todos['project'])
            ->merge($this->todos['nonproject'])
            ->firstWhere('id', $id);

        if (! $todo) {
            return;
        }

        $newStatus = $todo['status'] == 1 ? 4 : 1;

        Http::put(env('API_IZIN').'global/dar/activity/'.$id.'/status', [
            'status' => $newStatus
        ]);

        Toaster::success('Todo status updated');

        $this->mount();

        $this->dispatch('updatedTimeline');
        $this->dispatch('updatedCardTaskDar');
    }
}; ?>

<div>
    <div class="py-6">
        <div class="">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- MESSAGE BOARD --}}
                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    <header class="flex items-center justify-between border-b border-slate-200/70 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v7A2.5 2.5 0 0 1 17.5 16H10l-4.2 3.15A.9.9 0 0 1 4 18.45V6.5Z" />
                                    <path d="M7.5 8.5h9" />
                                    <path d="M7.5 11.5h6.5" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold tracking-tight text-slate-900">Message Board</h2>
                                <p class="text-xs text-slate-500">Message board terbaru</p>
                            </div>
                        </div>
                        <button type="button" class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900" aria-label="Message actions">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.6" />
                                <circle cx="12" cy="12" r="1.6" />
                                <circle cx="19" cy="12" r="1.6" />
                            </svg>
                        </button>
                    </header>

                    <div class="p-5" wire:poll.5s="refreshMessages">
                        <div class="space-y-3 overflow-scroll max-h-65">
                            @forelse($messages as $msg)
                            <button
                                type="button"
                                wire:click="openMessage({{ (int) $msg->activity_id }})"
                                wire:key="msg-{{ $msg->id }}"
                                class="group flex w-full items-start gap-3 rounded-xl border bg-white p-3 text-left transition hover:bg-slate-50 {{ $msg->unread > 0 ? 'border-blue-200 bg-blue-50/40' : 'border-slate-200/70' }}"
                            >
                                <div class="relative grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                                    <flux:tooltip content="{{ $msg->title }}">
                                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($msg->title, 0, 2)) }}
                                    </flux:tooltip>
                                    @if($msg->unread > 0)
                                        <span class="absolute -top-0.5 -right-0.5 h-2.5 w-2.5 rounded-full bg-blue-500 ring-2 ring-white"></span>
                                    @endif
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-center gap-2">
                                        <p class="truncate text-sm font-semibold text-slate-900 group-hover:text-slate-950">
                                            <span class="text-slate-700">{{ $msg->activity }}</span>
                                        </p>
                                        <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $msg->unread > 0 ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' }}">
                                            {{ $msg->replies }}
                                            <span class="font-medium {{ $msg->unread > 0 ? 'text-blue-500' : 'text-slate-500' }}">replies</span>
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs leading-relaxed text-slate-600 line-clamp-1">
                                        {!! $msg->description !!}
                                    </p>
                                    @if(!empty($msg->created_at))
                                        <p class="mt-1 text-[11px] text-slate-400">
                                            {{ \Carbon\Carbon::parse($msg->created_at)->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>
                            </button>
                            @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                                Belum ada komentar dari anggota tim.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </section>


                {{-- TO-DO --}}
                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    <header class="flex items-center justify-between border-b border-slate-200/70 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M9 6h11" />
                                    <path d="M9 12h11" />
                                    <path d="M9 18h11" />
                                    <path d="M4.5 6.5l1.2 1.2 2.3-2.7" />
                                    <path d="M4.5 12.5l1.2 1.2 2.3-2.7" />
                                    <path d="M4.5 18.5l1.2 1.2 2.3-2.7" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold tracking-tight text-slate-900">To-dos</h2>
                                <p class="text-xs text-slate-500">Checklist pekerjaan</p>
                            </div>
                        </div>
                        <button type="button" class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900" aria-label="Todo actions">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.6" />
                                <circle cx="12" cy="12" r="1.6" />
                                <circle cx="19" cy="12" r="1.6" />
                            </svg>
                        </button>
                    </header>

                    <div class="p-5">
                        <div class="space-y-5 overflow-scroll max-h-65">

                            {{-- Section --}}
                            <div class="space-y-1">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Non Project</p>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ count($todos['nonproject'] ?? []) }}
                                    </span>
                                </div>
                                @foreach($todos['nonproject'] as $todo)
                                @php $isDone = (int) $todo['status'] === 4; @endphp
                                <label wire:key="todo-{{ $todo['id'] }}" class="group flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/70 bg-white px-3 py-2 hover:bg-slate-50 {{ $isDone ? 'opacity-60' : '' }}">
                                    <input
                                        type="checkbox"
                                        @checked($isDone)
                                        wire:change="toggleTodo({{ $todo['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleTodo({{ $todo['id'] }})"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-900"
                                    >
                                    <span class="flex-1 text-sm text-slate-800 group-hover:text-slate-950 {{ $isDone ? 'line-through text-slate-400' : '' }}">
                                        {{ $todo['activity'] }}
                                    </span>
                                </label>
                                @endforeach
                            </div>

                            <div class="space-y-1">
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Project</p>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ count($todos['project'] ?? []) }}
                                    </span>
                                </div>
                                @foreach($todos['project'] as $todo)
                                @php $isDone = (int) $todo['status'] === 4; @endphp
                                <label wire:key="todo-{{ $todo['id'] }}" class="group flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200/70 bg-white px-3 py-2 hover:bg-slate-50 {{ $isDone ? 'opacity-60' : '' }}">
                                    <input
                                        type="checkbox"
                                        @checked($isDone)
                                        wire:change="toggleTodo({{ $todo['id'] }})"
                                        wire:loading.attr="disabled"
                                        wire:target="toggleTodo({{ $todo['id'] }})"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-900/20"
                                    >
                                    <span class="flex-1 text-sm text-slate-800 group-hover:text-slate-950 {{ $isDone ? 'line-through text-slate-400' : '' }}">
                                        {{ $todo['activity'] }}
                                    </span>
                                </label>
                                @endforeach
                            </div>

                        </div>
                    </div>
                </section>


                {{-- SCHEDULE (pengganti Docs & Files) --}}
                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">
                    <header class="flex items-center justify-between border-b border-slate-200/70 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-700">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M7 3v3" />
                                    <path d="M17 3v3" />
                                    <path d="M3.5 9h17" />
                                    <path d="M5.5 6h13A2 2 0 0 1 20.5 8v12A2 2 0 0 1 18.5 22h-13A2 2 0 0 1 3.5 20V8A2 2 0 0 1 5.5 6Z" />
                                    <path d="M7.5 12.5h4.5" />
                                    <path d="M7.5 16.5h8.5" />
                                </svg>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold tracking-tight text-slate-900">Schedule</h2>
                                <p class="text-xs text-slate-500">Agenda terdekat</p>
                            </div>
                        </div>
                        <button type="button" class="inline-flex items-center rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-900" aria-label="Schedule actions">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor" aria-hidden="true">
                                <circle cx="5" cy="12" r="1.6" />
                                <circle cx="12" cy="12" r="1.6" />
                                <circle cx="19" cy="12" r="1.6" />
                            </svg>
                        </button>
                    </header>

                    <div class="p-5">
                        @php
                        $scheduleGroups = collect($schedules ?? [])->groupBy(function ($item) {
                        return \Carbon\Carbon::parse($item->date)->toDateString();
                        });
                        @endphp

                        <div class="space-y-5 overflow-scroll max-h-65">
                            @forelse($scheduleGroups as $date => $items)
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        {{ \Carbon\Carbon::parse($date)->format('D, d M') }}
                                    </p>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                        {{ $items->count() }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    @foreach($items as $schedule)
                                    <div class="group rounded-xl border border-slate-200/70 bg-white p-3 hover:bg-slate-50">
                                        <div class="flex items-start gap-3">
                                            <div class="w-14 shrink-0 pt-0.5 text-right">
                                                <div class="text-xs font-semibold text-slate-700">
                                                    {{ \Carbon\Carbon::parse($schedule->date)->format('H:i') }}
                                                </div>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-semibold text-slate-900 group-hover:text-slate-950">
                                                    {{ $schedule->title }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            @empty
                            <div class="rounded-xl border border-dashed border-slate-300 p-4 text-sm text-slate-600">
                                Belum ada agenda.
                            </div>
                            @endforelse
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </div>
</div>
