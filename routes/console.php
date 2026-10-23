<?php

use Illuminate\Support\Facades\Schedule;

// Pick up new and changed meshes from mqmesh-depot. The old site's cron also
// pulls the depot every 12 hours; this runs on its own clone, read only.
Schedule::command('mqmesh:sync-depot')
    ->twiceDaily(4, 16)
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/depot-sync.log'));
