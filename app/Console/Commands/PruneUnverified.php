<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PruneUnverified extends Command
{
    protected $signature = 'mqmesh:prune-unverified {--days=7 : Age an account must reach first} {--dry-run : List the accounts without deleting them}';

    protected $description = 'Delete accounts that never confirmed their email';

    public function handle(): int
    {
        $users = User::query()
            ->whereNull('email_verified_at')
            ->where('role', User::ROLE_USER)
            ->where('is_trusted', false)
            ->where('created_at', '<', now()->subDays((int) $this->option('days')))
            ->whereDoesntHave('uploads')
            ->get();

        foreach ($users as $user) {
            $this->line("{$user->email} ({$user->created_at->toDateString()})");

            if (! $this->option('dry-run')) {
                $user->delete();
            }
        }

        $this->info(($this->option('dry-run') ? 'Would delete' : 'Deleted')." {$users->count()} unverified accounts.");

        return self::SUCCESS;
    }
}
