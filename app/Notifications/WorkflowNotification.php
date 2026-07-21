<?php

namespace App\Notifications;

use App\Support\ContentTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;

class WorkflowNotification extends Notification implements ShouldQueue
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
    ) {
        $this->afterCommit();
    }

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
        $type = ContentTypes::slugFor($this->subject);
        $baseRoute = $type !== null ? ContentTypes::META[$type]['route'] ?? null : null;

        return [
            'title' => $this->title,
            'message' => $this->message,
            'tone' => $this->tone,
            'subject_type' => $this->subject->getMorphClass(),
            'subject_id' => $this->subject->getKey(),
            'url' => $baseRoute !== null ? $baseRoute.'/'.$this->subject->getKey().'/edit' : null,
        ];
    }
}
