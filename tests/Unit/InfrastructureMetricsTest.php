<?php

namespace Tests\Unit;

use App\Models\CombatSite;
use App\Models\SewaLahanRenewal;
use App\Support\InfrastructureMetrics;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class InfrastructureMetricsTest extends TestCase
{
    public function test_bak_aging_is_calculated_from_tanggal_bak_instead_of_excel_formula(): void
    {
        $row = new CombatSite([
            'status_perpanjangan' => 'BAK',
            'source_details' => [
                'tanggal_bak' => '2026-07-17',
                'aging_days' => '=TODAY()-[@[Tanggal BAK]]',
            ],
        ]);

        $this->assertSame('2026-07-17', InfrastructureMetrics::processStartDate($row)?->toDateString());
        $this->assertSame(65, InfrastructureMetrics::processAgingDays($row, CarbonImmutable::parse('2026-09-20')));
    }

    public function test_missing_process_date_remains_missing_instead_of_zero(): void
    {
        $row = new CombatSite([
            'status_perpanjangan' => 'BAK',
            'source_details' => ['tanggal_bak' => 'NY'],
        ]);

        $this->assertNull(InfrastructureMetrics::processAgingDays($row, CarbonImmutable::parse('2026-09-20')));
        $this->assertNull(InfrastructureMetrics::priorityLabel($row, CarbonImmutable::parse('2026-09-20')));
    }

    public function test_region_r12_is_not_used_as_a_city_category(): void
    {
        $withKabupaten = new CombatSite(['source_details' => [
            'kabupaten' => 'BOGOR',
            'region' => 'R12 Eastern Jabodetabek',
        ]]);
        $regionOnly = new CombatSite(['source_details' => [
            'region' => 'R12 Eastern Jabodetabek',
        ]]);

        $this->assertSame('BOGOR', InfrastructureMetrics::geographyBucket($withKabupaten));
        $this->assertSame('Tidak Diisi', InfrastructureMetrics::geographyBucket($regionOnly));
    }

    public function test_nested_combat_workbook_details_are_available_to_process_and_geography_metrics(): void
    {
        $row = new CombatSite([
            'status_perpanjangan' => 'BAK',
            'source_details' => [
                'database' => [
                    'tanggal_bak' => 46220,
                    'kabupaten' => 'BEKASI',
                ],
                'database_revenue' => [
                    'tahun_justi_dirnet' => 2026,
                ],
            ],
        ]);

        $this->assertSame('2026-07-17', InfrastructureMetrics::processStartDate($row)?->toDateString());
        $this->assertSame('BEKASI', InfrastructureMetrics::geographyBucket($row));
    }

    public function test_formula_based_lease_duration_is_derived_from_normalized_dates(): void
    {
        $row = new SewaLahanRenewal([
            'start_date_baru' => '2025-09-01',
            'end_date_baru' => '2035-08-31',
            'source_details' => ['masa_sewath' => '=DATEDIF([@[NEW PERIOD (AWAL)]],[@[NEW PERIOD (AKHIR)]],"m")'],
        ]);

        $this->assertSame('10 tahun', InfrastructureMetrics::leaseDurationLabel($row));
    }

    public function test_operational_status_uses_status_site_not_document_workflow(): void
    {
        $row = new SewaLahanRenewal([
            'status_dokumen' => 'Finalisasi PKS --> Legal',
            'status_perpanjangan' => 'PKS',
            'source_details' => ['status' => 'Off Air'],
        ]);

        $this->assertSame(InfrastructureMetrics::OFF_AIR, InfrastructureMetrics::operationalBucket($row));
    }

    public function test_financial_values_handle_excel_thousands_and_derive_pnl(): void
    {
        $row = new SewaLahanRenewal([
            'source_details' => [
                'rev_jan_26' => 113011708,
                'cost_jan_26' => 57761439,
                'pnl_jan_26' => '55,250,269 ',
            ],
        ]);

        $this->assertSame(55250269.0, InfrastructureMetrics::moneyValue('55,250,269 '));
        $this->assertSame(23932204.0, InfrastructureMetrics::moneyValue('23.932.204'));
        $this->assertSame([
            'revenue' => 113011708.0,
            'cost' => 57761439.0,
            'pnl' => 55250269.0,
        ], InfrastructureMetrics::monthlyFinancials($row, 'jan'));
    }
}
