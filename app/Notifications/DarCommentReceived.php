<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DarCommentReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $activityId,
        public string $activityTitle,
        public int $commentId,
        public int $commenterId,
        public string $commenterName,
        public string $body,
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
            'comment_id' => $this->commentId,
            'commenter_id' => $this->commenterId,
            'commenter_name' => $this->commenterName,
            'body' => $this->body,
        ];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Komentar baru: '.($this->activityTitle !== '' ? $this->activityTitle : 'DAR'))
            ->body($this->commenterName.': '.Str::limit($this->body, 120))
            ->icon(asset('img/logo/logo-hma2.png'))
            ->tag('dar-comment-'.$this->commentId)
            ->data(['url' => route('dar.dar-show', ['id' => $this->activityId])]);
    }
}
