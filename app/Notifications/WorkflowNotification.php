<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification
{
    use Queueable;

    /**
     * @param  Model  $subject  The content model the transition happened on
     */
    public function __construct(
        public Model $subject,
        public string $title,
        public string $message,
        public string $tone = 'info',
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
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone,
            'subject_type' => $this->subject->getMorphClass(),
            'subject_id' => $this->subject->getKey(),
        ];
    }
}
