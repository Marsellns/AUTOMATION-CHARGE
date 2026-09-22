<?php

namespace Tests\Unit;

use App\Http\Controllers\InfrastructureDashboardController;
use App\Models\CombatSite;
use ReflectionMethod;
use Tests\TestCase;

class InfrastructureDashboardLogicTest extends TestCase
{
    public function test_nop_variants_match_the_same_chart_dimension(): void
    {
        $controller = app(InfrastructureDashboardController::class);

        $this->assertTrue($this->invoke($controller, 'sameDimension', ['nop', 'BOGOR', 'NOP BOGOR']));
        $this->assertTrue($this->invoke($controller, 'sameDimension', ['nop', 'NOP-BOGOR', 'NOP BOGOR']));
        $this->assertFalse($this->invoke($controller, 'sameDimension', ['nop', 'BOGOR', 'NOP BANDUNG']));
    }

    public function test_pks_status_is_derived_from_pks_numbers_not_excel_formula(): void
    {
        $controller = app(InfrastructureDashboardController::class);
        $hasPks = new CombatSite(['no_pks_baru' => 'PKS/001/2026']);
        $noPks = new CombatSite(['no_pks_baru' => null, 'no_pks_lama' => null]);

        $this->assertSame('Ada PKS', $this->invoke($controller, 'pksStatusValue', [$hasPks]));
        $this->assertSame('Tanpa PKS', $this->invoke($controller, 'pksStatusValue', [$noPks]));
    }

    public function test_nested_combat_snapshot_is_used_for_nop_and_vendor_charts(): void
    {
        $controller = app(InfrastructureDashboardController::class);
        $row = new CombatSite(['source_details' => [
            'database' => ['nop' => 'NOP BEKASI', 'tp' => 'TELKOMSEL'],
            'database_revenue' => ['nop' => 'NOP LAMA', 'tp' => 'TP LAMA'],
        ]]);

        $this->assertSame('NOP BEKASI', $this->invoke($controller, 'nopValue', [$row]));
        $this->assertSame('TELKOMSEL', $this->invoke($controller, 'vendorValue', [$row]));
    }

    public function test_combat_table_filter_resolves_flat_and_nested_json_paths(): void
    {
        $controller = app(\App\Http\Controllers\CombatSiteController::class);
        $expression = $this->invoke($controller, 'sourceValueExpression', ['status']);
        $nopExpression = $this->invoke($controller, 'normalizedNopExpression', []);

        $this->assertStringContainsString('$.status', $expression);
        $this->assertStringContainsString('$.database.status', $expression);
        $this->assertStringContainsString('$.database_revenue.status', $expression);
        $this->assertStringContainsString('REGEXP_REPLACE', $nopExpression);
    }

    public function test_popup_suppresses_excel_formulas_and_raw_metric_artifacts(): void
    {
        $controller = app(InfrastructureDashboardController::class);

        $details = $this->invoke($controller, 'displaySourceDetails', [[
            'pks_status' => '=IF(LEN(TRIM(tbl_combat[no_pks_baru]))>0,"Ada PKS","Tanpa PKS")',
            'revenue_jan_2026' => 1250000,
            'periode_awal_baru' => 45870,
            'alamat_site' => 'Bogor',
        ]]);

        $this->assertSame(['alamat_site' => 'Bogor'], $details);
    }

    private function invoke(object $object, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($object, $method);

        return $reflection->invokeArgs($object, $arguments);
    }
}
