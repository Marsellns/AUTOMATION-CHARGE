<?php

namespace Tests\Feature;

use App\Imports\Electricity\CentralizedListrikAllImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CentralizedListrikAllImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_reads_flagging_columns_without_changing_payment_master(): void
    {
        DB::table('payment_pln_master_monthly')->insert([
            'id_pelanggan' => 'OLD', 'site_id' => 'OLD', 'tahun' => 2026, 'bulan' => 1,
            'amount' => 123, 'is_paid' => true,
        ]);

        (new CentralizedListrikAllImport())->collection($this->sampleRows());

        $this->assertDatabaseCount('listrik_all', 4);
        $this->assertDatabaseHas('listrik_all', [
            'id_pelanggan' => 'ACC-1', 'tahun' => 2025, 'mei' => 100, 'sep' => 110,
        ]);
        $this->assertDatabaseHas('listrik_all', [
            'id_pelanggan' => 'ACC-1', 'tahun' => 2026, 'ags' => 1000, 'sep' => 0,
        ]);
        $this->assertDatabaseCount('payment_pln_master_monthly', 1);
        $this->assertDatabaseHas('payment_pln_master_monthly', ['site_id' => 'OLD', 'amount' => 123]);
    }

    public function test_listrik_all_table_joins_payment_by_account_without_repeating_site_total(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        (new CentralizedListrikAllImport())->collection($this->sampleRows());
        DB::table('payment_pln_master_monthly')->insert([
            [
                'id_pelanggan' => 'ACC-1', 'site_id' => 'SITE-A', 'site_name' => 'Site A',
                'tahun' => 2026, 'bulan' => 8, 'amount' => 1100, 'is_paid' => true,
            ],
            [
                'id_pelanggan' => 'ACC-2', 'site_id' => 'SITE-A', 'site_name' => 'Site A',
                'tahun' => 2026, 'bulan' => 8, 'amount' => 2200, 'is_paid' => true,
            ],
        ]);

        $response = $this->actingAs($user)->getJson(route('electricity.centralized.listrik-all.data', [
            'tahun' => 2026,
        ]))->assertOk()->assertJsonPath('recordsTotal', 2);
        $rows = collect($response->json('data'))->keyBy('id_pelanggan');
        $this->assertSame('Rp 1.100', $rows['ACC-1']['ags']);
        $this->assertSame('Rp 2.200', $rows['ACC-2']['ags']);

    }

    public function test_empty_upload_preserves_existing_listrik_all_rows(): void
    {
        DB::table('listrik_all')->insert(['id_pelanggan' => 'OLD', 'site_id' => 'OLD', 'tahun' => 2026]);

        try {
            (new CentralizedListrikAllImport())->collection(collect([collect(['Flagging Jan 2026'])]));
            $this->fail('Empty upload must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertDatabaseHas('listrik_all', ['site_id' => 'OLD']);
        }
    }

    private function sampleRows(): \Illuminate\Support\Collection
    {
        return collect([
            collect(['DATA MASTER']),
            collect([]),
            collect(['NO', 'ID Pelanggan', 'Site ID', 'Site Name', '', '', 'Gol Tarif', 'PLN UID',
                'Flagging May 2025', 'Flagging Sept 2025', 'Inquiry Agustus 2026',
                'Flagging Agustus 2026', 'Inquiry September 20262']),
            collect([1, 'ACC-1', 'SITE-A', 'Site A', '', '', 'B2', 'UID', 100, 110, 900, 1000, 2000]),
            collect([2, 'ACC-2', 'SITE-A', 'Site A', '', '', 'B2', 'UID', 200, 220, 1800, 2000, 4000]),
        ]);
    }
}
