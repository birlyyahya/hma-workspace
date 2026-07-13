<?php

use App\Notifications\DarCommentReceived;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component {
    public string $lastCheckedAt = '';

    public function mount(): void
    {
        $this->lastCheckedAt = now()->toDateTimeString();
    }

    public function checkNewNotifications(): void
    {
        $notifications = Auth::user()
            ->unreadNotifications()
            ->where('type', DarCommentReceived::class)
            ->where('created_at', '>', $this->lastCheckedAt)
            ->latest()
            ->limit(10)
            ->get();

        $this->lastCheckedAt = now()->toDateTimeString();

        if ($notifications->isEmpty()) {
            return;
        }

        $payload = $notifications->map(fn ($notification) => [
            'id' => (string) $notification->id,
            'title' => 'Komentar baru: '.($notification->data['activity_title'] ?? 'DAR'),
            'body' => ($notification->data['commenter_name'] ?? 'Seseorang').': '.Str::limit($notification->data['body'] ?? '', 120),
            'url' => route('dar.dar-show', ['id' => $notification->data['activity_id'] ?? 0]),
        ])->values()->all();

        $this->dispatch('browser-push-notifications', notifications: $payload);
        $this->dispatch('play-notification-sound');
    }
}; ?>

<div wire:poll.30s.keep-alive="checkNewNotifications"></div>
