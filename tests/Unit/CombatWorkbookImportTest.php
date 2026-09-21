<?php

namespace Tests\Unit;

use App\Imports\Datasets\CombatWorkbookImport;
use Tests\TestCase;

class CombatWorkbookImportTest extends TestCase
{
    public function test_it_consolidates_master_and_latest_revenue_by_site_id(): void
    {
        $import = new CombatWorkbookImport();
        $records = $import->consolidate(
            [
                ['site_id' => 'COC001', 'site_name' => 'Master Site', 'status_dokumen' => 'PKS'],
                ['site_id' => 'COC002', 'site_name' => 'Master Only'],
            ],
            [
                ['site_id' => 'COC001', 'tahun_justi_dirnet' => 2025, 'revenue_jan_2026' => 100000, 'cost_jan_2026' => 80000, 'pnl_jan_2026' => 20000],
                ['site_id' => 'COC001', 'tahun_justi_dirnet' => 2026, 'revenue_jan_2026' => 302.699, 'cost_jan_2026' => 200000, 'pnl_jan_2026' => 102699],
                ['site_id' => 'COC003', 'site_name' => 'Revenue Only', 'tahun_justi_dirnet' => 2026, 'revenue_jan_2026' => 500000],
            ]
        );

        $bySite = collect($records)->keyBy('site_code');

        $this->assertCount(3, $records);
        $this->assertSame('Master Site', $bySite['COC001']['site_name']);
        $this->assertSame('PKS', $bySite['COC001']['status_dokumen']);
        $this->assertSame(2026, $bySite['COC001']['tahun_justi_dirnet']);
        $this->assertSame(302699.0, $bySite['COC001']['revenue_jan_2026']);
        $this->assertSame('Master Only', $bySite['COC002']['site_name']);
        $this->assertSame('Revenue Only', $bySite['COC003']['site_name']);
    }
}
