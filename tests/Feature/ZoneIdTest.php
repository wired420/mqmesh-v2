<?php

namespace Tests\Feature;

use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_ids_get_placeholders_from_1111_that_skip_ids_in_use(): void
    {
        Zone::factory()->create(['short_name' => 'guildhallng_errand', 'eq_zone_id' => null]);
        Zone::factory()->create(['short_name' => 'alpha', 'eq_zone_id' => 0]);
        Zone::factory()->create(['short_name' => 'beta', 'eq_zone_id' => null]);
        Zone::factory()->create(['short_name' => 'taken', 'eq_zone_id' => 1112]);
        Zone::factory()->create(['short_name' => 'poknowledge', 'eq_zone_id' => 202]);

        $this->assertSame(
            ['alpha' => 1111, 'beta' => 1113, 'guildhallng_errand' => 1114],
            Zone::assignMissingIds(),
        );

        $this->assertSame(202, Zone::where('short_name', 'poknowledge')->value('eq_zone_id'));
        $this->assertSame(1112, Zone::where('short_name', 'taken')->value('eq_zone_id'));
        $this->assertSame([], Zone::assignMissingIds());
    }

    public function test_reimporting_a_csv_with_a_blank_id_keeps_the_placeholder(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'zones');
        file_put_contents($csv, "poknowledge|202|The Plane of Knowledge|pop\nguildhallng_errand||Guild Hall Errand|other\n");

        $this->artisan('mqmesh:import-zones', ['csv' => $csv])
            ->expectsOutputToContain('guildhallng_errand has no id in the CSV, given placeholder id 1111')
            ->assertSuccessful();

        $this->artisan('mqmesh:import-zones', ['csv' => $csv])->assertSuccessful();

        $this->assertSame(1111, Zone::where('short_name', 'guildhallng_errand')->value('eq_zone_id'));
        $this->assertSame(202, Zone::where('short_name', 'poknowledge')->value('eq_zone_id'));

        unlink($csv);
    }
}
