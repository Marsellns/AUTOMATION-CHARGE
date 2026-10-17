<?php

namespace Tests\Feature;

use App\Exports\ListrikAllExport;
use App\Imports\Electricity\CentralizedListrikAllImport;
use App\Imports\Electricity\CentralizedPaymentImport;
use App\Models\ListrikInbuilding;
use App\Models\ListrikPln;
use App\Services\ElectricityPaymentComparisonService;
use App\Services\SiteStatusSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

/** Regression coverage for manual/Excel input through the existing report endpoints. */
class OperationalFreshnessTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', DB::connection()->getDatabaseName());
        Cache::clear();
        $this->actingAs($this->userWithRole('admin'));
    }

    public function test_manual_ibc_bill_reaches_payment_matrix_and_dashboard_without_copying_imports(): void
    {
        ListrikInbuilding::create(['site_id' => 'AUDIT-IBC', 'site_name' => 'Audit', 'status' => 'Aktif']);
        $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), [
            'type' => 'X0,E3', 'site_id' => 'AUDIT-IBC', 'periode' => '2026-01',
            'dpp_ppn_b' => 1000, 'tgl_bayar_to_bm' => '2026-01-15',
        ])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('input_upload_tagihan_ibc', ['site_id' => 'AUDIT-IBC', 'total_tagihan_f' => 1000]);
        $this->assertDatabaseCount('payment_ibc', 0);
        $this->assertDatabaseCount('inbuilding_all', 0);
        $this->assertSame(1, DB::table('payment_ibc_effective')->count());
        $this->assertSame(1, DB::table('inbuilding_all_effective')->count());
        $this->assertDatabaseCount('anomali_tagihan_inbuilding', 0);
        $this->getJson(route('electricity.dashboard.data', ['tahun' => 2026, 'bulan' => 1]))
            ->assertOk()->assertJsonPath('kpi.total_sites', 1)->assertJsonPath('kpi.total_billed', 1000);
    }

    public function test_manual_pln_payment_reaches_site_chart_and_global_billing(): void
    {
        $site = ListrikPln::create(['id_pelanggan' => 'AUDIT-ACC', 'site_id' => 'AUDIT-PLN', 'status_aktif_site' => 'Aktif']);
        $this->post(route('electricity.centralized.listrik-pln.status-pembayaran.store', $site), [
            'bulan' => 1, 'tahun' => 2026, 'harga' => 2000, 'remark' => 'Lunas',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('status_pembayaran', 1);
        $this->assertDatabaseCount('payment_pln', 0);
        $this->getJson(route('electricity.centralized.listrik-pln.grafik-data', [$site, 'tahun' => 2026]))
            ->assertOk()->assertJsonPath('summary.raw_total', 2000);
        $this->getJson(route('electricity.dashboard.data', ['tahun' => 2026, 'bulan' => 1]))
            ->assertOk()->assertJsonPath('kpi.total_billed', 2000);
    }

    public function test_master_dashboard_cache_is_invalidated_by_manual_infrastructure_and_po_changes(): void
    {
        DB::table('payment_pln')->insert(['id_pelanggan' => 'PERIOD-ACC', 'site_id' => 'PERIOD', 'bulan' => 1, 'tahun' => 2026, 'harga' => 1, 'status' => 'Done', 'created_at' => now(), 'updated_at' => now()]);
        $url = route('dashboard.chart-data', ['tahun' => 2026, 'bulan' => 'all']);
        $this->getJson($url)->assertOk()->assertJsonPath('infra.0.y', 0)->assertJsonCount(0, 'po');
        $this->post(route('infrastruktur.manual.store', 'sewa-lahan'), ['site_code' => 'AUDIT-SEWA', 'tahun_renewal' => 2026])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('po-hq.store'), ['po_number' => 'AUDIT-PO', 'vendor_name' => 'Audit', 'expense_type' => 'Capex', 'status' => 'Draft'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->getJson(route('infrastruktur.dashboard.data'))->assertOk()->assertJsonPath('cards.total', 1);
        $this->get(route('po-monitoring.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['PO HQ'] === 1);
        $this->getJson($url)->assertOk()->assertJsonPath('infra.0.y', 1)->assertJsonPath('po.0.y', 1);
        $this->travel(301)->seconds();
        $this->getJson($url)->assertOk()->assertJsonPath('infra.0.y', 1)->assertJsonPath('po.0.y', 1);
        $this->travelBack();
    }

    public function test_new_listrik_all_upload_updates_the_matching_monthly_snapshot(): void
    {
        DB::table('payment_pln_master_monthly')->insert([
            'id_pelanggan' => 'AUDIT-ACC', 'site_id' => 'AUDIT-SITE', 'tahun' => 2026, 'bulan' => 1,
            'amount' => 100, 'is_paid' => true, 'status_aktif_site' => 'Aktif',
        ]);
        $file = $this->workbook([
            ['Listrik All - Template'],
            ['No', 'ID Pelanggan', 'Site ID', 'Site Name', 'Gol Tarif', 'Unit PLN', 'Flagging Jan 2026'],
            [1, 'AUDIT-ACC', 'AUDIT-SITE', 'Audit', 'B2', 'UID', 900],
        ]);
        $this->post(route('electricity.centralized.listrik-all.upload'), ['listrik_all_file' => $file])
            ->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('listrik_all', ['site_id' => 'AUDIT-SITE', 'jan' => 900]);
        $this->getJson(route('electricity.centralized.listrik-all.data', ['tahun' => 2026]))
            ->assertOk()->assertJsonPath('data.0.jan', 'Rp 900');
        $summary = app(ElectricityPaymentComparisonService::class)->summary(2026, 1);
        $this->assertSame(900.0, $summary['series'][0]['points'][0]['amount']);
    }

    public function test_bapss_upload_preserves_existing_manual_data(): void
    {
        $this->post(route('infrastruktur.manual.store', 'bapss'), ['site_code' => 'MANUAL-OLD'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('infrastruktur.upload.store', 'bapss'), ['dataset_file' => $this->workbook([
            ['BAPSS'], ['Site ID', 'Site Name'], ['EXCEL-NEW', 'Audit'],
        ])])->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseCount('bapss', 2);
        $this->assertDatabaseHas('bapss', ['site_code' => 'MANUAL-OLD']);
        $this->assertDatabaseHas('bapss', ['site_code' => 'EXCEL-NEW']);
    }

    public function test_ibc_manual_period_choices_follow_the_current_year(): void
    {
        $this->travelTo(now()->setDate(2027, 2, 1));
        $response = $this->get(route('electricity.inbuilding.input-tagihan-ibc.create'))->assertOk();
        $periods = $response->viewData('periods');
        $this->assertSame('2028-12', $periods[0]['code']);
        $this->assertTrue(collect($periods)->contains(fn ($period) => str_starts_with($period['code'], '2027-')));
        $this->travelBack();
    }

    public function test_pnl_excel_upload_updates_real_analytics_and_periods(): void
    {
        // Warm the period cache before the upload to test its invalidation.
        $this->assertSame([], app(SiteStatusSummaryService::class)->availablePeriods());
        $file = $this->workbook([
            ['PnL'], ['Site ID', 'Site Name', 'Rev Jul-27', 'Cost Jul-27'], ['AUDIT-PNL', 'Audit', 1500, 600],
        ]);
        $this->post(route('pnl.upload.store'), ['pnl_file' => $file])
            ->assertSessionHasNoErrors()->assertRedirect(route('pnl.index'));
        $this->assertDatabaseCount('sites', 1);
        $this->assertDatabaseHas('site_monthly_metrics', ['tahun' => 2027, 'bulan' => 7, 'profit_loss' => 900]);
        $this->assertSame([['bulan' => 7, 'tahun' => 2027]], app(SiteStatusSummaryService::class)->availablePeriods());
        $this->getJson(route('pnl.analytics', ['tahun' => 2027, 'bulan' => 'all']))
            ->assertOk()->assertJsonPath('active_count', 1)->assertJsonPath('monthly.0.revenue', 1500);
        $this->getJson(route('dashboard.chart-data', ['tahun' => 2027, 'bulan' => 'all']))
            ->assertOk()->assertJsonPath('financial_kpi.total_revenue', 1500)->assertJsonPath('financial_kpi.total_pnl', 900);
    }

    public function test_ibc_invoice_corrections_additions_and_deletions_keep_every_report_consistent(): void
    {
        $master = ListrikInbuilding::create(['site_id' => 'INVOICES', 'status' => 'Aktif']);
        foreach (['A' => 100, 'B' => 200] as $invoice => $amount) {
            DB::table('payment_ibc')->insert([
                'site_id' => 'INVOICES', 'invoice' => $invoice, 'jumlah_tagihan' => $amount, 'tahun' => 2027, 'bulan' => 1, 'status' => 'Done',
            ]);
        }
        $values = ['site_id' => 'INVOICES', 'periode' => '2027-01', 'type' => 'X0,E3', 'no_invoice_bm' => 'A', 'dpp_ppn_b' => 150];
        $id = $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), $values)->assertOk()->json('data.id');
        $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), $values)->assertUnprocessable();
        $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), array_replace($values, ['no_invoice_bm' => 'C', 'dpp_ppn_b' => 50]))->assertOk();
        $this->assertSame(400.0, (float) DB::table('payment_ibc_effective')->sum('jumlah_tagihan'));
        $this->getJson(route('electricity.inbuilding.listrik-inbuilding.chart-data', [$master, 'tahun' => 2027]))->assertOk()->assertJsonPath('summary.raw_total', 400);
        $this->assertSame(400.0, (float) DB::table('inbuilding_all_effective')->value('jan'));
        $this->putJson(route('electricity.inbuilding.input-tagihan-ibc.update', $id), array_replace($values, ['dpp_ppn_b' => 170]))->assertOk();
        $this->assertSame(420.0, (float) DB::table('payment_ibc_effective')->sum('jumlah_tagihan'));
        $this->deleteJson(route('electricity.inbuilding.input-tagihan-ibc.destroy', $id))->assertOk();
        $this->assertSame(350.0, (float) DB::table('payment_ibc_effective')->sum('jumlah_tagihan'));
        $total = $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), array_replace($values, ['no_invoice_bm' => null, 'dpp_ppn_b' => 600]))->assertOk()->json('data.id');
        $this->assertSame(600.0, (float) DB::table('payment_ibc_effective')->sum('jumlah_tagihan'));
        $this->deleteJson(route('electricity.inbuilding.input-tagihan-ibc.destroy', $total))->assertOk();
        $this->assertSame(350.0, (float) DB::table('payment_ibc_effective')->sum('jumlah_tagihan'));
        $this->assertDatabaseCount('payment_ibc', 2);
    }

    public function test_pln_correction_overrides_both_billing_and_snapshot_and_delete_restores_imports(): void
    {
        $site = ListrikPln::create(['site_id' => 'CORRECTION', 'id_pelanggan' => 'ACC-CORRECTION', 'status_aktif_site' => 'Aktif']);
        DB::table('payment_pln')->insert(['site_id' => $site->site_id, 'id_pelanggan' => $site->id_pelanggan, 'tahun' => 2027, 'bulan' => 1, 'harga' => 100, 'status' => 'Done']);
        DB::table('payment_pln_master_monthly')->insert(['site_id' => $site->site_id, 'id_pelanggan' => $site->id_pelanggan, 'tahun' => 2027, 'bulan' => 1, 'amount' => 100, 'is_paid' => true]);
        $this->post(route('electricity.centralized.listrik-pln.status-pembayaran.store', $site), ['tahun' => 2027, 'bulan' => 1, 'harga' => 250, 'remark' => 'Belum dibayar'])->assertSessionHasNoErrors();
        $id = DB::table('status_pembayaran')->value('id');
        $this->assertSame(250.0, (float) DB::table('payment_pln_effective')->sum('harga'));
        $this->assertSame('Pending', DB::table('payment_pln_effective')->value('status'));
        $this->assertSame(0, (int) DB::table('payment_pln_master_monthly_effective')->value('is_paid'));
        $this->put(route('electricity.centralized.status-pembayaran.update', $id), ['harga' => 300, 'remark' => 'Lunas'])->assertSessionHasNoErrors();
        $this->assertSame(300.0, (float) DB::table('payment_pln_master_monthly_effective')->sum('amount'));
        $row = (new ListrikAllExport(2027))->collection()->first();
        $this->assertSame(300.0, (float) $row->jan);
        $this->getJson(route('electricity.centralized.listrik-all.data', ['tahun' => 2027]))->assertOk()->assertJsonPath('data.0.jan', 'Rp 300');
        $this->delete(route('electricity.centralized.status-pembayaran.destroy', $id))->assertSessionHasNoErrors();
        $this->assertSame(100.0, (float) DB::table('payment_pln_effective')->sum('harga'));
        $this->assertSame(100.0, (float) DB::table('payment_pln_master_monthly_effective')->sum('amount'));
    }

    public function test_anomalies_recalculate_across_year_boundary_and_disappear_after_correction(): void
    {
        $pln = ListrikPln::create(['site_id' => 'ANOMALY', 'id_pelanggan' => 'ACC-ANOMALY']);
        ListrikInbuilding::create(['site_id' => 'ANOMALY', 'status' => 'Aktif']);
        DB::table('payment_pln')->insert(['site_id' => 'ANOMALY', 'id_pelanggan' => 'ACC-ANOMALY', 'tahun' => 2026, 'bulan' => 12, 'harga' => 100]);
        DB::table('payment_ibc')->insert(['site_id' => 'ANOMALY', 'tahun' => 2026, 'bulan' => 12, 'jumlah_tagihan' => 100]);
        $this->post(route('electricity.centralized.listrik-pln.status-pembayaran.store', $pln), ['tahun' => 2027, 'bulan' => 1, 'harga' => 180])->assertSessionHasNoErrors();
        $id = $this->postJson(route('electricity.inbuilding.input-tagihan-ibc.store'), ['site_id' => 'ANOMALY', 'periode' => '2027-01', 'type' => 'X0,E3', 'dpp_ppn_b' => 200])->assertOk()->json('data.id');
        $this->assertSame(80.0, (float) DB::table('anomali_tagihan_pln_effective')->value('kenaikan_persen'));
        $this->assertSame('2026-12', DB::table('anomali_tagihan_inbuilding_effective')->value('periode_sebelumnya'));
        $this->assertSame(100.0, (float) DB::table('anomali_tagihan_inbuilding_effective')->value('kenaikan_persen'));
        $this->post(route('electricity.centralized.listrik-pln.status-pembayaran.store', $pln), ['tahun' => 2027, 'bulan' => 1, 'harga' => 120])->assertSessionHasNoErrors();
        $this->putJson(route('electricity.inbuilding.input-tagihan-ibc.update', $id), ['site_id' => 'ANOMALY', 'periode' => '2027-01', 'type' => 'X0,E3', 'dpp_ppn_b' => 120])->assertOk();
        $this->assertSame(0, DB::table('anomali_tagihan_pln_effective')->where('kenaikan_persen', '>', 50)->count());
        $this->assertSame(0, DB::table('anomali_tagihan_inbuilding_effective')->where('kenaikan_persen', '>', 50)->count());
    }

    public function test_partial_listrik_all_upload_preserves_other_months_and_accounts(): void
    {
        DB::table('listrik_all')->insert(['id_pelanggan' => 'PARTIAL', 'site_id' => 'PARTIAL', 'tahun' => 2027, 'jan' => 10, 'feb' => 20]);
        DB::table('payment_pln_master_monthly')->insert(['id_pelanggan' => 'OTHER', 'site_id' => 'OTHER', 'tahun' => 2027, 'bulan' => 1, 'amount' => 99]);
        (new CentralizedListrikAllImport)->collection(collect([
            collect(['No', 'ID Pelanggan', 'Site ID', 'Site Name', 'Flagging Jan 2027']), collect([1, 'PARTIAL', 'PARTIAL', 'Site', 50]),
        ]));
        $rows = (new ListrikAllExport(2027))->collection()->keyBy('site_id');
        $this->assertSame(50.0, (float) $rows['PARTIAL']->jan);
        $this->assertSame(20.0, (float) $rows['PARTIAL']->feb);
        $this->assertSame(99.0, (float) $rows['OTHER']->jan);
    }

    public function test_report_revision_changes_only_after_successful_writes(): void
    {
        $url = route('reports.data-version');
        $version = $this->getJson($url)->assertOk()->json('version');
        $this->getJson($url)->assertJsonPath('version', $version);
        $this->postJson(route('operations.store', 'site-owner'), [])->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('version', $version);
        $this->post(route('operations.store', 'site-owner'), ['site_code' => 'REVISION'])->assertSessionHasNoErrors();
        $this->assertNotSame($version, $this->getJson($url)->assertOk()->json('version'));
    }

    public function test_bongkar_rampung_upload_merges_and_rejects_empty_files(): void
    {
        DB::table('bongkar_rampung_mandiri')->insert(['id_pelanggan' => 'KEEP', 'site_id' => 'KEEP']);
        $rows = [['Daftar_Site_Tidak_Aktif'], ['No', 'Aksi', 'ID Pelanggan', 'Site ID', 'Site Name'], [1, '', 'NEW', 'NEW', 'Site']];
        foreach ([1, 2] as $repeat) {
            $this->post(route('electricity.centralized.bongkar-rampung-mandiri.upload'), ['boram_file' => $this->workbook($rows)])->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('bongkar_rampung_mandiri', 2);
        $this->post(route('electricity.centralized.bongkar-rampung-mandiri.upload'), ['boram_file' => $this->workbook(array_slice($rows, 0, 2))])->assertSessionHasErrors('boram_file');
        $this->assertDatabaseHas('bongkar_rampung_mandiri', ['site_id' => 'KEEP']);
    }

    public function test_payment_upload_changes_status_of_the_same_bill_instead_of_adding_a_second_bill(): void
    {
        $rows = collect([collect(['Payment']), collect(['No', 'ID Pelanggan', 'Site ID']), collect([1, 'ACCOUNT', 'SITE', 'Site', 1000, '1', 'B2', 'PLN', 250])]);
        (new CentralizedPaymentImport('Pending', 1, 2027))->collection($rows);
        (new CentralizedPaymentImport('Done', 1, 2027))->collection($rows);
        $this->assertDatabaseCount('payment_pln', 1);
        $this->assertDatabaseHas('payment_pln', ['status' => 'Done', 'harga' => 250]);
    }

    public function test_pnl_explicit_zero_corrects_old_values_and_blank_months_preserve_data(): void
    {
        $this->post(route('pnl.upload.store'), ['pnl_file' => $this->workbook([['PnL'], ['Site ID', 'Rev Jan-27', 'Cost Jan-27', 'Rev Feb-27', 'Cost Feb-27'], ['ZERO', 100, 20, 200, 50]])])->assertSessionHasNoErrors();
        $this->post(route('pnl.upload.store'), ['pnl_file' => $this->workbook([['PnL'], ['Site ID', 'Rev Jan-27', 'Cost Jan-27', 'Rev Feb-27', 'Cost Feb-27'], ['ZERO', 0, 0, null, null]])])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_monthly_metrics', ['tahun' => 2027, 'bulan' => 1, 'revenue' => 0, 'cost' => 0, 'profit_loss' => 0]);
        $this->assertDatabaseHas('site_monthly_metrics', ['tahun' => 2027, 'bulan' => 2, 'revenue' => 200, 'cost' => 50, 'profit_loss' => 150]);
    }

    public function test_executive_chart_cache_also_works_with_the_database_cache_store(): void
    {
        Cache::setDefaultDriver('database');
        DB::table('payment_pln')->insert(['site_id' => 'CACHE', 'id_pelanggan' => 'CACHE', 'tahun' => 2027, 'bulan' => 1, 'harga' => 100]);
        $url = route('dashboard.chart-data', ['tahun' => 2027, 'bulan' => 'all']);
        $this->getJson($url)->assertOk()->assertJsonPath('pln_kpi.total_tagihan', 100);
        $this->getJson($url)->assertOk()->assertJsonPath('pln_kpi.total_tagihan', 100);
        $this->assertLessThanOrEqual(255, DB::table('cache')->max(DB::raw('LENGTH(`key`)')));
    }

    public function test_pln_site_chart_also_follows_excel_billing_with_historical_status_as_fallback(): void
    {
        $site = ListrikPln::create(['site_id' => 'EXCEL-CHART', 'id_pelanggan' => 'CHART-ACCOUNT']);
        DB::table('status_pembayaran')->insert(['listrik_pln_id' => $site->id, 'id_pelanggan' => $site->id_pelanggan, 'tahun' => 2028, 'bulan' => 1, 'harga' => 99]);
        $rows = collect([collect(['Payment']), collect(['No', 'ID Pelanggan', 'Site ID']), collect([1, $site->id_pelanggan, $site->site_id, 'Site', 1000, '1', 'B2', 'PLN', 250])]);
        (new CentralizedPaymentImport('Done', 1, 2028))->collection($rows);
        $this->getJson(route('electricity.centralized.listrik-pln.grafik-data', [$site, 'tahun' => 2028]))->assertOk()->assertJsonPath('summary.raw_total', 250);
        $this->get(route('electricity.centralized.listrik-pln.grafik', $site))->assertOk()->assertViewHas('years', fn ($years) => in_array(2028, $years));
        $this->assertDatabaseHas('status_pembayaran', ['harga' => 99]);
    }

    private function workbook(array $rows): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray($rows, null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'audit-freshness-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('audit.xlsx', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }
}
