<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecurringTagihanMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_existing_rows_are_preserved_and_rollback_restores_the_original_table(): void
    {
        $migration = require database_path('migrations/2026_10_17_010000_normalize_recurring_tagihan_ipas_sites.php');
        $migration->down();
        $rows = [
            ['id' => 101, 'site_code' => ' bks648 ', 'termin' => '1', 'termin_start' => '2014-10-21', 'termin_end' => '2015-10-20', 'amount' => '100.25', 'source_details' => '{"po_number":"PO-1"}'],
            ['id' => 102, 'site_code' => 'BKS648', 'termin' => '2', 'termin_start' => '2015-10-21', 'termin_end' => '2016-10-20', 'amount' => '200.50', 'source_details' => '{"po_number":"PO-2"}'],
        ];
        DB::table('recurring_tagihan_ipas')->insert($rows);
        $before = DB::table('recurring_tagihan_ipas')->orderBy('id')->get()->toArray();
        $migration->up();
        $this->assertDatabaseCount('recurring_tagihan_ipas', 1);
        $this->assertDatabaseHas('recurring_tagihan_ipas', ['site_code' => 'BKS648', 'term_count' => 2, 'termin' => '1–2', 'amount' => '300.75']);
        $this->assertDatabaseCount('recurring_tagihan_ipas_terms', 2);
        $this->assertDatabaseHas('recurring_tagihan_ipas_terms', ['id' => 101, 'site_code' => ' bks648 ']);
        $migration->down();
        $this->assertEquals($before, DB::table('recurring_tagihan_ipas')->orderBy('id')->get()->toArray());
        $migration->up();
    }
}
