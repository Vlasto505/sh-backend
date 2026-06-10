<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application) {}

    public function envelope(): Envelope
    {
        $statusLabel = match ($this->application->status->value) {
            'approved'             => 'schválená',
            'rejected'             => 'zamietnutá',
            'supplement_requested' => 'vyžaduje doplnenie',
            'under_review'         => 'v procese hodnotenia',
            default                => 'aktualizovaná',
        };

        return new Envelope(
            subject: "Vaša žiadosť bola {$statusLabel} – NTI Platforma",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-status',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
