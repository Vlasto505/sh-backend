<?php

namespace App\Notifications;

use App\Models\Application;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusNotification extends Notification
{
    public function __construct(
        public Application $application,
        public string $event,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line] = $this->content();

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Dobrý deň {$notifiable->name},")
            ->line($line)
            ->action('Zobraziť prihlášku', url("/applications/{$this->application->id}"))
            ->line('Ďakujeme, že používate platformu NTI.');
    }

    public function toArray(object $notifiable): array
    {
        [$subject, $line] = $this->content();

        return [
            'type'           => 'application_status',
            'event'          => $this->event,
            'application_id' => $this->application->id,
            'title'          => $this->application->title,
            'message'        => $line,
            'url'            => "/applications/{$this->application->id}",
        ];
    }

    /** @return array{0:string,1:string} */
    private function content(): array
    {
        $t = $this->application->title;

        return match ($this->event) {
            'submitted' => ["Prihláška podaná: {$t}", "Vaša prihláška „{$t}“ bola úspešne podaná a čaká na spracovanie komisiou."],
            'supplement_requested' => ["Vyžiadané doplnenie: {$t}", "Komisia žiada doplnenie vašej prihlášky „{$t}“. Otvorte ju a upravte podľa pokynov."],
            'approved' => ["Prihláška schválená: {$t}", "Gratulujeme! Vaša prihláška „{$t}“ bola schválená."],
            'rejected' => ["Prihláška zamietnutá: {$t}", "Vaša prihláška „{$t}“ bola zamietnutá. Detaily nájdete v prihláške."],
            default => ["Zmena stavu prihlášky: {$t}", "Stav vašej prihlášky „{$t}“ sa zmenil."],
        };
    }
}
