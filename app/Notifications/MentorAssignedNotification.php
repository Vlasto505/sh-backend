<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MentorAssignedNotification extends Notification
{
    public function __construct(
        public Application $application,
        public User $mentor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isMentor = $notifiable->id === $this->mentor->id;
        $t = $this->application->title;

        $message = $isMentor
            ? (new MailMessage)
                ->subject("Pridelený projekt: {$t}")
                ->greeting("Dobrý deň {$notifiable->name},")
                ->line("Boli ste pridelení ako mentor k projektu „{$t}“.")
            : (new MailMessage)
                ->subject("Pridelený mentor: {$this->mentor->name}")
                ->greeting("Dobrý deň {$notifiable->name},")
                ->line("K vášmu projektu „{$t}“ bol pridelený mentor {$this->mentor->name}.");

        return $message
            ->action('Zobraziť projekt', url("/projects/{$this->application->id}"))
            ->line('Platforma NTI');
    }

    public function toArray(object $notifiable): array
    {
        $isMentor = $notifiable->id === $this->mentor->id;
        $t = $this->application->title;

        return [
            'type'           => 'mentor_assigned',
            'application_id' => $this->application->id,
            'title'          => $t,
            'message'        => $isMentor
                ? "Boli ste pridelení ako mentor k projektu „{$t}“."
                : "K projektu „{$t}“ bol pridelený mentor {$this->mentor->name}.",
            'url'            => "/projects/{$this->application->id}",
        ];
    }
}
