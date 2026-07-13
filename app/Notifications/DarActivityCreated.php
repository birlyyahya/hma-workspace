<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DarActivityCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $activityId,
        public string $activityTitle,
        public int $actorId,
        public string $actorName,
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
            'activity_id' => $this->activityId,
            'activity_title' => $this->activityTitle,
            'actor_id' => $this->actorId,
            'actor_name' => $this->actorName,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('DAR baru: '.Str::limit($this->activityTitle, 60))
            ->body($this->actorName.' menambahkanmu ke aktivitas DAR baru.')
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('dar-created-'.$this->activityId)
            ->data(['url' => route('dar.dar-show', ['id' => $this->activityId])]);
    }
}
