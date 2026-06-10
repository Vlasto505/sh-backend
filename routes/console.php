<?php

use App\Console\Commands\SendApplicationReminderEmails;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-application-reminders')->dailyAt('08:00');

// GDPR retention: purge soft-deleted accounts past the retention window.
Schedule::command('gdpr:purge')->weekly();
