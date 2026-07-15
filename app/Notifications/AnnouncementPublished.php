<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $announcementId,
        public string $title,
        public string $priority,
        public int $authorId,
        public string $authorName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcementId,
            'title' => $this->title,
            'priority' => $this->priority,
            'author_id' => $this->authorId,
            'author_name' => $this->authorName,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $prefix = $this->priority === 'important' ? '📢 Pengumuman penting: ' : 'Pengumuman baru: ';

        return (new WebPushMessage)
            ->title($prefix.Str::limit($this->title, 60))
            ->body('Diterbitkan oleh '.$this->authorName.'. Ketuk untuk membaca.')
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('announcement-'.$this->announcementId)
            ->data(['url' => route('knowledge.announcements')]);
    }
}
