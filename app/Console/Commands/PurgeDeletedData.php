<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PurgeDeletedData extends Command
{
    protected $signature = 'gdpr:purge {--days=365 : Retention period in days}';

    protected $description = 'Permanently delete soft-deleted accounts (and their data) past the GDPR retention period.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $users = User::onlyTrashed()->where('deleted_at', '<', $cutoff)->get();

        foreach ($users as $user) {
            $user->forceDelete(); // cascades to applications, teams membership, etc.
        }

        $this->info("Trvalo odstránených {$users->count()} účtov zmazaných pred {$cutoff->toDateString()} (retencia {$days} dní).");

        return self::SUCCESS;
    }
}
