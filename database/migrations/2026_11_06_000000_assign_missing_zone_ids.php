<?php

use App\Models\Zone;
use Illuminate\Database\Migrations\Migration;

/**
 * Give every zone without an EverQuest zone id a placeholder id from 1111 up.
 * The old updaters crash on a blank "id" in updater.json.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Zone::assignMissingIds() as $shortName => $id) {
            echo "  Zone {$shortName} given id {$id}".PHP_EOL;
        }
    }

    public function down(): void
    {
        // The placeholder ids are kept; nothing records which ids were blank.
    }
};
