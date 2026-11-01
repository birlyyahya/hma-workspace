<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class IzinSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $izinId,
        public string $applicantName,
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
            'applicant_name' => $this->applicantName,
            'reason' => $this->reason,
            'start_date' => $this->startDate,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Izin baru perlu konfirmasi HRD')
            ->body($this->applicantName.' mengajukan izin: '.Str::limit($this->reason, 80))
            ->icon(asset('img/logo/pwa-192.png'))
            ->tag('izin-submitted-'.($this->izinId ?? Str::slug($this->applicantName)))
            ->data(['url' => $this->izinId
                ? route('izin.show', ['id' => $this->izinId])
                : route('izin.laporan-pengajuan')]);
    }
}
