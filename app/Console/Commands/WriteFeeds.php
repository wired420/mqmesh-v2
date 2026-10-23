<?php

namespace App\Console\Commands;

use App\Services\LegacyFeeds;
use Illuminate\Console\Command;

class WriteFeeds extends Command
{
    protected $signature = 'mqmesh:write-feeds';

    protected $description = 'Rebuild zones.json, updater.json and meshdb.json for the old updaters';

    public function handle(LegacyFeeds $feeds): int
    {
        $feeds->write();
        $this->info('Wrote '.implode(', ', LegacyFeeds::FILES).' to '.public_path());

        return self::SUCCESS;
    }
}
