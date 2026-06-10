<?php

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Mail\ApplicationStatusMail;
use App\Models\Application;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendApplicationReminderEmails extends Command
{
    protected $signature   = 'app:send-application-reminders';
    protected $description = 'Send reminder emails for draft applications older than 7 days';

    public function handle(): int
    {
        $applications = Application::with('user')
            ->where('status', ApplicationStatus::Draft)
            ->where('created_at', '<=', now()->subDays(7))
            ->whereNull('submitted_at')
            ->get();

        foreach ($applications as $application) {
            Mail::to($application->user->email)
                ->queue(new ApplicationStatusMail($application));
        }

        $this->info("Queued reminders: {$applications->count()}");

        return self::SUCCESS;
    }
}
