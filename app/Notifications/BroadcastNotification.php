<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Bulk announcement from an administrator (spec 6.4). In-app only so it can be
 * delivered to large groups instantly; e-mail delivery would require a queue.
 */
class BroadcastNotification extends Notification
{
    public function __construct(
        public string $subject,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'broadcast',
            'title'   => $this->subject,
            'message' => $this->subject.' — '.$this->body,
            'url'     => '/notifications',
        ];
    }
}
