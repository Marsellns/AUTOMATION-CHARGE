<?php

namespace Tests\Feature;

use App\Models\PoVarcost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class PoMonitoringDrilldownTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_each_chart_category_opens_the_same_database_rows_and_count(): void
    {
        foreach (['Overdue', ' Overdue ', 'Need Check'] as $index => $status) {
            DB::table('po_hq')->insert(['po_number' => 'HQ-'.$index, 'vendor_name' => 'Vendor', 'expense_type' => 'Capex', 'status' => $status]);
        }
        foreach (['Complete', ' Complete ', null, ''] as $status) {
            PoVarcost::factory()->create(['gr_status' => $status]);
        }
        foreach (['Pending', 'Completed', 'Rejected'] as $index => $status) {
            DB::table('document_circulations')->insert([
                'document_title' => 'Dokumen '.$index, 'file_name' => $index.'.pdf',
                'file_path' => 'private/'.$index.'.pdf', 'status' => $status,
            ]);
        }

        $this->actingAs($this->userWithRole('viewer'));
        $dashboard = $this->get(route('po-monitoring.dashboard'))->assertOk()
            ->assertSee('data-po-open="hq"', false)->assertSee('data-po-open="varcost"', false)
            ->assertSee('data-po-open="presales"', false)->assertSee('po-detail-search');
        foreach (array_slice($dashboard->viewData('charts'), 1) as $chart) {
            foreach ($chart['data'] as $point) {
                $response = $this->getJson(route('po-monitoring.details', ['dataset' => $chart['dataset'], 'category' => $point['name']]))
                    ->assertOk()->assertJsonPath('recordsFiltered', $point['y'])->assertJsonCount($point['y'], 'data');
                $column = $chart['dataset'] === 'varcost' ? 'gr_status' : 'status';
                foreach ($response->json('data') as $row) {
                    $this->assertSame($point['name'], trim($row[$column] ?? '') ?: 'Belum diisi');
                    $this->assertArrayNotHasKey('file_path', $row);
                }
            }
        }
        foreach ($dashboard->viewData('charts')[0]['data'] as $point) {
            $this->getJson(route('po-monitoring.details', ['dataset' => $point['dataset']]))
                ->assertOk()->assertJsonPath('recordsTotal', $point['y'])->assertJsonPath('recordsFiltered', $point['y']);
        }
    }

    public function test_search_and_pagination_preserve_the_selected_dataset_and_status(): void
    {
        foreach (range(1, 13) as $index) {
            DB::table('po_hq')->insert(['po_number' => 'HQ-'.$index, 'vendor_name' => 'Vendor Alpha', 'expense_type' => 'Capex', 'status' => 'Overdue']);
        }
        DB::table('po_hq')->insert(['po_number' => 'HQ-OTHER', 'vendor_name' => 'Vendor Beta', 'expense_type' => 'Opex', 'status' => 'Need Check']);
        PoVarcost::factory()->create(['po_number' => 'HQ-1', 'description' => 'Vendor Alpha']);
        $this->actingAs($this->userWithRole('report'));
        $filters = ['dataset' => 'hq', 'category' => 'Overdue', 'search' => ['value' => 'Alpha'], 'length' => 10, 'draw' => 7];
        $first = $this->getJson(route('po-monitoring.details', $filters))->assertOk()
            ->assertJsonPath('draw', 7)->assertJsonPath('recordsTotal', 14)->assertJsonPath('recordsFiltered', 13)->assertJsonCount(10, 'data');
        $second = $this->getJson(route('po-monitoring.details', $filters + ['start' => 10]))->assertOk()
            ->assertJsonPath('recordsFiltered', 13)->assertJsonCount(3, 'data');
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
        $this->getJson(route('po-monitoring.details', ['dataset' => 'hq', 'category' => 'Need Check', 'search' => ['value' => 'Alpha']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 0)->assertJsonCount(0, 'data');
        $this->getJson(route('po-monitoring.details', ['dataset' => 'hq', 'category' => "Overdue' OR 1=1 --"]))
            ->assertOk()->assertJsonPath('recordsFiltered', 0);
    }

    public function test_varcost_and_presales_support_search_and_keep_private_storage_paths_private(): void
    {
        PoVarcost::factory()->create(['po_number' => 'VC-MATCH', 'gr_status' => ' Complete ', 'description' => 'Pembayaran jaringan']);
        PoVarcost::factory()->create(['po_number' => 'VC-OTHER', 'gr_status' => 'Pending']);
        foreach (['Target', 'Other'] as $name) {
            DB::table('document_circulations')->insert([
                'document_title' => $name, 'document_number' => 'DOC-'.$name, 'file_name' => $name.'.pdf',
                'file_path' => 'private/'.$name.'.pdf', 'status' => 'Pending', 'uploaded_by_name' => 'Manager '.$name,
            ]);
        }
        $this->actingAs($this->userWithRole('viewer'));
        $this->getJson(route('po-monitoring.details', ['dataset' => 'varcost', 'category' => 'Complete', 'search' => ['value' => 'jaringan']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.po_number', 'VC-MATCH');
        $response = $this->getJson(route('po-monitoring.details', ['dataset' => 'presales', 'category' => 'Pending', 'search' => ['value' => 'Manager Target']]))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.document_number', 'DOC-Target');
        $this->assertArrayNotHasKey('file_path', $response->json('data.0'));
    }

    public function test_details_require_authentication_and_reject_invalid_filters_and_unbounded_pages(): void
    {
        $this->getJson(route('po-monitoring.details', ['dataset' => 'hq']))->assertUnauthorized();
        $this->actingAs($this->userWithRole('viewer'));
        foreach ([[], ['dataset' => 'users'], ['dataset' => 'hq', 'category' => ['Overdue']], ['dataset' => 'hq', 'search' => 'text'], ['dataset' => 'hq', 'length' => -1], ['dataset' => 'hq', 'length' => 101], ['dataset' => 'hq', 'start' => -1]] as $filters) {
            $this->getJson(route('po-monitoring.details', $filters))->assertUnprocessable();
        }
        $this->getJson(route('po-monitoring.details', ['dataset' => 'varcost']))->assertOk()
            ->assertJsonPath('recordsTotal', 0)->assertJsonPath('recordsFiltered', 0)->assertJsonCount(0, 'data');
    }
}
