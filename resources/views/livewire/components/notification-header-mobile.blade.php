<?php

use App\Notifications\AnnouncementPublished;
use App\Notifications\DarActivityClosed;
use App\Notifications\DarActivityCreated;
use App\Notifications\DarCommentReceived;
use App\Notifications\SpdApproved;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public string $tab = 'all';

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['all', 'comment', 'announcement', 'history'], true) ? $tab : 'all';
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function allItems(): Collection
    {
        return auth()->user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->present($notification));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function items(): Collection
    {
        $items = match ($this->tab) {
            'history' => $this->allItems->where('unread', false),
            'comment' => $this->allItems->where('unread', true)->where('kind', 'comment'),
            'announcement' => $this->allItems->where('unread', true)->where('kind', 'announcement'),
            default => $this->allItems->where('unread', true),
        };

        return $items->values();
    }

    #[Computed]
    public function unreadCount(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $this->present($notification)['url'];

        if ($url !== null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllAsRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
        unset($this->allItems, $this->items, $this->unreadCount);
    }

    /**
     * @return array{id: string, kind: string, unread: bool, actor: string, headline: string, subject: string, context: string, time: string, icon: string, color: string, preview: array{type: string, title: string, meta: string}|null, url: string|null}
     */
    protected function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        $base = [
            'id' => $notification->id,
            'unread' => $notification->read_at === null,
            'time' => $notification->created_at->locale('id')->diffForHumans(short: true),
        ];

        $details = match ($notification->type) {
            DarCommentReceived::class => [
                'kind' => 'comment',
                'actor' => $data['commenter_name'] ?? 'Seseorang',
                'headline' => 'berkomentar di',
                'subject' => $data['activity_title'] ?? 'DAR',
                'context' => 'DAR',
                'icon' => 'chat-bubble-left-ellipsis',
                'color' => 'bg-emerald-500',
                'preview' => ['type' => 'quote', 'title' => $data['body'] ?? '', 'meta' => ''],
                'url' => isset($data['activity_id']) ? route('dar.dar-show', ['id' => $data['activity_id']]) : null,
            ],
            DarActivityCreated::class => [
                'kind' => 'dar',
                'actor' => $data['actor_name'] ?? 'Seseorang',
                'headline' => 'menambahkanmu ke aktivitas DAR',
                'subject' => '',
                'context' => 'DAR',
                'icon' => 'clipboard-document-list',
                'color' => 'bg-blue-500',
                'preview' => ['type' => 'link', 'title' => $data['activity_title'] ?? 'DAR', 'meta' => 'Buka aktivitas'],
                'url' => isset($data['activity_id']) ? route('dar.dar-show', ['id' => $data['activity_id']]) : null,
            ],
            DarActivityClosed::class => [
                'kind' => 'dar',
                'actor' => $data['actor_name'] ?? 'Seseorang',
                'headline' => 'menutup aktivitas DAR',
                'subject' => '',
                'context' => 'DAR',
                'icon' => 'check-circle',
                'color' => 'bg-zinc-500',
                'preview' => ['type' => 'link', 'title' => $data['activity_title'] ?? 'DAR', 'meta' => 'Selesai'],
                'url' => isset($data['activity_id']) ? route('dar.dar-show', ['id' => $data['activity_id']]) : null,
            ],
            SpdApproved::class => [
                'kind' => 'spd',
                'actor' => 'SPD',
                'headline' => 'kamu telah disetujui',
                'subject' => '',
                'context' => 'Izin',
                'icon' => 'paper-airplane',
                'color' => 'bg-amber-500',
                'preview' => ['type' => 'link', 'title' => strip_tags($data['task'] ?? 'SPD'), 'meta' => $data['destination'] ?? ''],
                'url' => isset($data['spd_id']) ? route('izin.spd-preview', ['id' => $data['spd_id']]) : null,
            ],
            AnnouncementPublished::class => [
                'kind' => 'announcement',
                'actor' => $data['author_name'] ?? 'Admin',
                'headline' => ($data['priority'] ?? '') === 'important' ? 'menerbitkan pengumuman penting' : 'menerbitkan pengumuman',
                'subject' => '',
                'context' => 'Pengumuman',
                'icon' => 'megaphone',
                'color' => ($data['priority'] ?? '') === 'important' ? 'bg-red-500' : 'bg-violet-500',
                'preview' => ['type' => 'link', 'title' => $data['title'] ?? 'Pengumuman', 'meta' => ($data['priority'] ?? '') === 'important' ? 'Penting' : ''],
                'url' => route('knowledge.announcements'),
            ],
            TaskAssignedNotification::class => [
                'kind' => 'task',
                'actor' => $data['assigned_by'] ?? 'Seseorang',
                'headline' => 'memberimu task baru',
                'subject' => '',
                'context' => 'Task',
                'icon' => 'check-badge',
                'color' => 'bg-rose-500',
                'preview' => ['type' => 'link', 'title' => $data['task_name'] ?? 'Task', 'meta' => ''],
                'url' => route('dashboard'),
            ],
            default => [
                'kind' => 'other',
                'actor' => 'Sistem',
                'headline' => $data['message'] ?? 'Notifikasi baru',
                'subject' => '',
                'context' => 'Info',
                'icon' => 'bell',
                'color' => 'bg-zinc-500',
                'preview' => null,
                'url' => null,
            ],
        };

        return $base + $details;
    }
}

?>

<div wire:poll.visible.60s>
    <flux:modal.trigger name="notification-mobile">
        <div class="relative">
            <flux:button size="sm" variant="ghost" icon="bell" iconVariant="outline" aria-label="Notifikasi">
            </flux:button>
            @if ($this->unreadCount > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white tabular-nums">
                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                </span>
            @endif
        </div>
    </flux:modal.trigger>

    <flux:modal name="notification-mobile" flyout class="w-full max-w-md overflow-hidden! p-0!">
        <div class="flex h-dvh flex-col">
            <div class="shrink-0 px-5 pt-5">
                <flux:heading size="lg" class="font-semibold">Notifikasi</flux:heading>
            </div>

            @php
                $tabs = [
                    'all' => ['label' => 'Semua', 'count' => $this->unreadCount],
                    'comment' => ['label' => 'Komentar', 'count' => null],
                    'announcement' => ['label' => 'Pengumuman', 'count' => null],
                    'history' => ['label' => 'Riwayat', 'count' => null],
                ];
            @endphp
            <div class="mt-3 flex shrink-0 gap-1 overflow-x-auto border-b border-zinc-200 px-3">
                @foreach ($tabs as $key => $item)
                    <button
                        type="button"
                        wire:key="notif-tab-{{ $key }}"
                        wire:click="setTab('{{ $key }}')"
                        @class([
                            'relative flex shrink-0 cursor-pointer items-center gap-1.5 px-2.5 pb-2.5 pt-1 text-sm transition',
                            'font-semibold text-blue-600' => $tab === $key,
                            'text-zinc-500 hover:text-zinc-800' => $tab !== $key,
                        ])
                    >
                        {{ $item['label'] }}
                        @if ($item['count'])
                            <span @class([
                                'rounded-full px-1.5 text-[11px] font-semibold tabular-nums',
                                'bg-blue-600 text-white' => $tab === $key,
                                'bg-zinc-100 text-zinc-500' => $tab !== $key,
                            ])>{{ $item['count'] }}</span>
                        @endif
                        @if ($tab === $key)
                            <span class="absolute inset-x-1 -bottom-px h-0.5 rounded-full bg-blue-600"></span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="min-h-0 flex-1 divide-y divide-zinc-200 overflow-y-auto overscroll-contain">
                @forelse ($this->items as $notification)
                    <button
                        type="button"
                        wire:key="notif-{{ $notification['id'] }}"
                        wire:click="open('{{ $notification['id'] }}')"
                        @class([
                            'block w-full cursor-pointer px-5 py-4 text-left transition hover:bg-zinc-50',
                            'bg-zinc-50' => $notification['unread'],
                        ])
                    >
                        <div class="flex items-start gap-3">
                            <div class="relative shrink-0">
                                @if ($notification['unread'])
                                    <span class="absolute -left-3 top-3 size-2 rounded-full bg-blue-500"></span>
                                @endif
                                <flux:avatar size="sm" name="{{ $notification['actor'] }}" />
                                <span class="{{ $notification['color'] }} absolute -bottom-1 -right-1 flex size-4.5 items-center justify-center rounded-full ring-2 ring-white">
                                    <flux:icon :name="$notification['icon']" variant="micro" class="size-3 text-white" />
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-sm leading-snug text-zinc-500">
                                        <span class="font-semibold text-zinc-900">{{ $notification['actor'] }}</span>
                                        {{ $notification['headline'] }}
                                        @if ($notification['subject'] !== '')
                                            <span class="font-semibold text-zinc-900">{{ $notification['subject'] }}</span>
                                        @endif
                                    </p>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs font-semibold text-zinc-800">{{ $notification['context'] }}</p>
                                        <p class="text-xs text-zinc-400">{{ $notification['time'] }}</p>
                                    </div>
                                </div>

                                @if ($notification['preview'])
                                    @if ($notification['preview']['type'] === 'quote')
                                        <div class="mt-2.5 flex gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5">
                                            <flux:icon name="arrow-turn-down-right" variant="micro" class="mt-0.5 size-4 shrink-0 text-zinc-300" />
                                            <p class="line-clamp-3 text-sm text-zinc-700">{{ $notification['preview']['title'] }}</p>
                                        </div>
                                    @else
                                        <div class="mt-2.5 flex items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3 py-2.5">
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-zinc-800">{{ $notification['preview']['title'] }}</p>
                                                @if ($notification['preview']['meta'] !== '')
                                                    <p class="truncate text-xs text-zinc-400">{{ $notification['preview']['meta'] }}</p>
                                                @endif
                                            </div>
                                            <flux:icon name="chevron-right" variant="micro" class="size-4 shrink-0 text-zinc-400" />
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="flex flex-col items-center gap-2 px-5 py-16 text-zinc-400">
                        <flux:icon name="bell-slash" class="size-8" />
                        <p class="text-sm">{{ $tab === 'history' ? 'Belum ada riwayat notifikasi' : 'Tidak ada notifikasi baru' }}</p>
                    </div>
                @endforelse
            </div>

            <div class="flex shrink-0 items-center justify-between border-t border-zinc-200 bg-white px-5 py-3">
                <flux:button
                    size="sm"
                    variant="ghost"
                    icon="check"
                    wire:click="markAllAsRead"
                    :disabled="$this->unreadCount === 0"
                    class="-ml-2 cursor-pointer"
                >
                    Tandai semua dibaca
                </flux:button>
                <span class="text-xs text-zinc-400 tabular-nums">{{ $this->allItems->count() }} terbaru</span>
            </div>
        </div>
    </flux:modal>
</div>
