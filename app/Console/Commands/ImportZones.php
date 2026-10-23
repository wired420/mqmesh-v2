<?php

namespace App\Console\Commands;

use App\Models\Expansion;
use App\Models\Zone;
use Illuminate\Console\Command;

class ImportZones extends Command
{
    protected $signature = 'mqmesh:import-zones {csv : Path to zoneinfo.csv (short|id|name|expansion per line)}';

    protected $description = 'Create or update expansions and zones from zoneinfo.csv';

    public function handle(): int
    {
        $csv = $this->argument('csv');

        if (! is_readable($csv)) {
            $this->error("Cannot read {$csv}");

            return self::FAILURE;
        }

        Expansion::syncFromConfig();
        $expansions = Expansion::pluck('id', 'code');
        $aliases = config('mqmesh.expansion_aliases');
        $count = 0;

        foreach (file($csv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $fields = array_map('trim', explode('|', $line));

            if (count($fields) < 4) {
                $this->warn("Skipping malformed line: {$line}");

                continue;
            }

            [$shortName, $eqId, $name, $code] = $fields;
            $code = strtolower($aliases[strtolower($code)] ?? $code);

            if (! isset($expansions[$code])) {
                $this->warn("Unknown expansion [{$code}] for {$shortName}, filed under Other");
                $code = 'other';
            }

            Zone::updateOrCreate(
                ['short_name' => strtolower($shortName)],
                ['eq_zone_id' => is_numeric($eqId) ? (int) $eqId : null, 'name' => $name, 'expansion_id' => $expansions[$code]],
            );
            $count++;
        }

        $this->info("Imported {$count} zones into ".count($expansions).' expansions.');

        return self::SUCCESS;
    }
}
