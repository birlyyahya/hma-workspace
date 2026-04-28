<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DarCommentReceived extends Notification
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
        return ['database'];
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
}
