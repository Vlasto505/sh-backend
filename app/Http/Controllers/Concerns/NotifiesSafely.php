<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

trait NotifiesSafely
{
    /**
     * Send a notification without letting a delivery failure (e.g. SMTP down)
     * break the surrounding action. The in-app (database) record is still written.
     */
    protected function notifySafely(object $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (\Throwable $e) {
            Log::warning('Notification delivery failed: '.$e->getMessage());
        }
    }
}
