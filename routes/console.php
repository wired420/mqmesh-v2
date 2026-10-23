<?php

use Illuminate\Support\Facades\Schedule;

// The GitLab webhook (/hooks/gitlab) syncs the depot on every push. This daily
// run is only a safety net in case a webhook call is missed. It works on its
// own clone, read only.
Schedule::command('mqmesh:sync-depot')
    ->dailyAt('04:00')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/depot-sync.log'));
