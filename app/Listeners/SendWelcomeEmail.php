<?php

namespace App\Listeners;

use App\Mail\WelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmail
{
    public function __construct() {}

    public function handle(Registered $event): void
    {
        Mail::to($event->user->email)->send(new WelcomeMail($event->user));
    }
}
