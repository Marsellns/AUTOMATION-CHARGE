<?php

namespace Tests\Feature;

use App\Models\ListrikInbuilding;
use App\Support\OperationalDatasets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class OperationalDataEntryTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_each_new_entry_point_supports_manual_and_idempotent_excel_without_erasing_other_rows(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        ListrikInbuilding::create(['site_id' => 'IBC-ENTRY', 'status' => 'Aktif']);
        $cases = [
            'site-owner' => ['site_code' => 'OWNER-ENTRY', 'site_name' => 'Owner', 'nop' => 'BEKASI'],
            'asset-tower' => ['site_id' => 'OWNER-ENTRY', 'part_name' => 'Tower', 'brand' => 'Brand', 'owner' => 'Telkomsel'],
            'po-hq' => ['po_number' => 'HQ-ENTRY', 'vendor_name' => 'Vendor', 'status' => 'Draft', 'expense_type' => 'Capex'],
            'po-varcost' => ['po_number' => 'VC-ENTRY', 'expense_type' => 'Opex', 'description' => 'Operational cost', 'gr_status' => 'Complete', 'po_year' => 2027],
            'inventory' => ['uniq_key' => 'EQ-ENTRY', 'site_id' => 'OWNER-ENTRY', 'safe_to_reloc' => 'SAFE'],
            'pnl' => ['site_code' => 'PNL-ENTRY', 'site_name' => 'Financial', 'bulan' => 1, 'tahun' => 2027, 'revenue' => 1000, 'cost' => 100],
            'input-ibc' => ['site_id' => 'IBC-ENTRY', 'periode' => '2027-01', 'type' => 'X0,E3', 'no_invoice_bm' => 'INV-ENTRY', 'dpp_ppn_b' => 500],
            'listrik-pln' => ['site_id' => 'PLN-ENTRY', 'id_pelanggan' => 'ACC-ENTRY', 'status_aktif_site' => 'Aktif'],
        ];
        foreach ($cases as $dataset => $values) {
            $this->get(route('operations.create', $dataset))->assertOk()->assertSee('Tambah Manual')->assertSee('Template Excel');
            $this->get(route('operations.template', $dataset))->assertOk();
            $model = OperationalDatasets::DEFINITIONS[$dataset][0];
            $before = $model::count();
            $this->post(route('operations.store', $dataset), $values)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($before + 1, $model::count(), $dataset.' manual');
            $this->postJson(route('operations.store', $dataset), $values)->assertUnprocessable();
            $headers = array_keys(OperationalDatasets::fields($dataset));
            foreach ([1, 2] as $repeat) {
                $this->post(route('operations.upload', $dataset), ['dataset_file' => $this->workbook($headers, [$values])])
                    ->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('success');
                $this->assertSame($before + 1, $model::count(), $dataset.' Excel '.$repeat);
            }
        }
        $this->assertDatabaseHas('site_monthly_metrics', ['tahun' => 2027, 'bulan' => 1, 'profit_loss' => 900]);
        $this->getJson(route('electricity.dashboard.data', ['tahun' => 2027, 'bulan' => 1]))->assertOk()->assertJsonPath('kpi.total_billed', 500);
        $this->get(route('po-monitoring.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['PO HQ'] === 1 && $stats['PO Varcost'] === 1);
        $this->get(route('data-potensi.dashboard'))->assertOk()->assertViewHas('stats', fn ($stats) => $stats['Total Site'] === 2);
    }

    public function test_failed_upload_is_atomic_and_blank_optional_cells_preserve_existing_metadata(): void
    {
        $this->actingAs($this->userWithRole('admin'));
        DB::table('site_owners')->insert(['site_code' => 'KEEP', 'site_name' => 'Keep Name', 'nop' => 'BOGOR']);
        $headers = ['site_code', 'site_name', 'nop'];
        $this->post(route('operations.upload', 'site-owner'), ['dataset_file' => $this->workbook($headers, [['site_code' => 'KEEP', 'nop' => 'BEKASI']])])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('site_owners', ['site_code' => 'KEEP', 'site_name' => 'Keep Name', 'nop' => 'BEKASI']);
        $this->post(route('operations.upload', 'site-owner'), ['dataset_file' => $this->workbook($headers, [['site_code' => 'VALID'], ['site_name' => 'Missing Site ID']])])->assertSessionHasErrors('dataset_file');
        $this->assertDatabaseMissing('site_owners', ['site_code' => 'VALID']);
    }

    public function test_new_entry_routes_preserve_admin_authorization_and_dataset_allowlist(): void
    {
        $this->get(route('operations.create', 'site-owner'))->assertRedirect(route('login'));
        $this->actingAs($this->userWithRole('viewer'));
        foreach (array_keys(OperationalDatasets::DEFINITIONS) as $dataset) {
            $this->get(route('operations.create', $dataset))->assertForbidden();
            $this->get(route('operations.template', $dataset))->assertForbidden();
            $this->postJson(route('operations.store', $dataset), [])->assertForbidden();
            $this->postJson(route('operations.upload', $dataset), [])->assertForbidden();
        }
        $this->get('/operations/users/create')->assertNotFound();
    }

    private function workbook(array $headers, array $records): UploadedFile
    {
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([$headers, ...array_map(fn ($row) => array_map(fn ($header) => $row[$header] ?? null, $headers), $records)], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'operational-entry-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('entry.xlsx', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }
}
