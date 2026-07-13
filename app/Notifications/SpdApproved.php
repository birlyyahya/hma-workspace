<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class SpdApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $spdId,
        public string $task,
        public string $destination,
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
            'spd_id' => $this->spdId,
            'task' => $this->task,
            'destination' => $this->destination,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('SPD disetujui')
            ->body(Str::limit(strip_tags($this->task), 80).' — SPD kamu sudah disetujui, silakan cek email.')
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('spd-approved-'.$this->spdId)
            ->data(['url' => route('izin.spd-preview', ['id' => $this->spdId])]);
    }
}
