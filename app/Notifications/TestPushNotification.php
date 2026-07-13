<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TestPushNotification extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Notifikasi Tes — HMA Workspace')
            ->body('Web push berfungsi di perangkat ini.')
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('test-push')
            ->data(['url' => route('dashboard')]);
    }
}
