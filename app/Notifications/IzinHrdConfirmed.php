<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class IzinHrdConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $izinId,
        public string $confirmedBy,
        public string $reason,
        public ?string $startDate,
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
            'izin_id' => $this->izinId,
            'confirmed_by' => $this->confirmedBy,
            'reason' => $this->reason,
            'start_date' => $this->startDate,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Izin disetujui HRD')
            ->body(Str::limit($this->reason, 80).' — izin kamu sudah dikonfirmasi HRD.')
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('izin-hrd-confirmed-'.$this->izinId)
            ->data(['url' => route('izin.show', ['id' => $this->izinId])]);
    }
}
