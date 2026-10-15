<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PnlHighlightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_highlights_follow_period_and_nop_filters_and_remain_available_for_table_navigation(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        $now = now();
        $sites = [];

        foreach ([
            'BKS001' => ['Site Alpha', 'NOP BEKASI'],
            'BKS002' => ['Site Beta', 'NOP BEKASI'],
            'BKS003' => ['Site Gamma', 'NOP BEKASI'],
            'BGR001' => ['Site Bogor', 'NOP BOGOR'],
        ] as $siteCode => [$siteName, $nop]) {
            $sites[$siteCode] = DB::table('sites')->insertGetId([
                'site_id' => $siteCode,
                'site_name' => $siteName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('site_owners')->insert(['site_code' => $siteCode, 'nop' => $nop]);
        }

        foreach ([
            ['BKS001', 1, 100, false],
            ['BKS001', 2, -60, false],
            ['BKS002', 1, -80, false],
            ['BKS002', 2, -40, false],
            ['BKS003', 1, 60, false],
            ['BKS003', 2, 1000, true],
            ['BGR001', 1, 500, false],
            ['BGR001', 2, -900, false],
        ] as [$siteCode, $month, $netPnl, $isAnomaly]) {
            DB::table('site_monthly_metrics')->insert([
                'site_id' => $sites[$siteCode],
                'bulan' => $month,
                'tahun' => 2026,
                'revenue' => max($netPnl, 0),
                'cost' => max(-$netPnl, 0),
                'profit_loss' => $netPnl,
                'is_anomaly' => $isAnomaly,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->actingAs($user)
            ->get(route('pnl.index'))
            ->assertOk()
            ->assertViewHas('selectedAllMonths', true)
            ->assertViewHas('selectedPeriod', '0-2026')
            ->assertSee('Top 3 Site Profit')
            ->assertSee('Top 3 Site Loss')
            ->assertSee('aria-controls="pnl-table"', false)
            ->assertDontSee('Klik untuk tampilkan di tabel');

        $this->actingAs($user)
            ->get(route('pnl.index', ['tahun' => 2026, 'bulan' => 1]))
            ->assertOk()
            ->assertViewHas('selectedAllMonths', false)
            ->assertViewHas('selectedPeriod', '1-2026');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1, 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BKS001')
            ->assertJsonPath('profit.site_name', 'Site Alpha')
            ->assertJsonPath('profit.profit_loss', 100)
            ->assertJsonPath('loss.site_id', 'BKS002')
            ->assertJsonPath('loss.profit_loss', -80);

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 'all', 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BKS003')
            ->assertJsonPath('profit.profit_loss', 60)
            ->assertJsonPath('loss.site_id', 'BKS002')
            ->assertJsonPath('loss.profit_loss', -120);

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 2, 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BKS003')
            ->assertJsonPath('profit.profit_loss', 1000)
            ->assertJsonPath('loss.site_id', 'BKS001');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1, 'nop' => 'NOP BOGOR']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BGR001')
            ->assertJsonPath('loss', null);

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1]))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BGR001')
            ->assertJsonPath('loss.site_id', 'BKS002');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1, 'nop' => 'NOP BEKASI', 'status' => 'Profit']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BKS001')
            ->assertJsonPath('loss.site_id', 'BKS002');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1, 'nop' => 'NOP BEKASI', 'status' => 'Loss']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BKS001')
            ->assertJsonPath('loss.site_id', 'BKS002');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1, 'status' => 'TidakAktif']))
            ->assertOk()
            ->assertJsonPath('profit.site_id', 'BGR001')
            ->assertJsonPath('loss.site_id', 'BKS002');

        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2025, 'bulan' => 'all', 'nop' => 'NOP BEKASI']))
            ->assertOk()
            ->assertJsonPath('profit', null)
            ->assertJsonPath('loss', null);

        $columns = collect([
            'DT_RowIndex', 'site_id', 'site_name', 'nop',
            'revenue', 'cost', 'profit_loss', 'status_badge',
        ])->map(fn (string $column) => [
            'data' => $column,
            'name' => $column,
            'searchable' => 'true',
            'orderable' => 'true',
            'search' => ['value' => $column === 'site_id' ? 'BKS003' : '', 'regex' => 'false'],
        ])->all();

        $this->actingAs($user)
            ->getJson(route('pnl.data', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'tahun' => 2026,
                'bulan' => 'all',
                'nop' => 'NOP BEKASI',
                'columns' => $columns,
                'search' => ['value' => '', 'regex' => 'false'],
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.site_id', 'BKS003');
    }

    public function test_highlights_require_authentication_and_valid_month(): void
    {
        $this->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 1]))->assertUnauthorized();

        $user = User::factory()->create(['account_status' => 'approved']);
        $this->actingAs($user)
            ->getJson(route('pnl.highlights', ['tahun' => 2026, 'bulan' => 13]))
            ->assertUnprocessable();
    }

    public function test_top_three_rankings_include_financial_details_and_exclude_break_even_from_losses(): void
    {
        $user = User::factory()->create(['account_status' => 'approved']);
        foreach ([60, 50, 50, -10, -30, -20, 0, 900] as $index => $pnl) {
            $code = 'RANK'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $site = DB::table('sites')->insertGetId(['site_id' => $code, 'site_name' => 'Ranking '.$index]);
            DB::table('site_owners')->insert(['site_code' => $code, 'nop' => $index === 7 ? 'NOP BOGOR' : 'NOP BEKASI']);
            DB::table('site_monthly_metrics')->insert([
                'site_id' => $site, 'bulan' => 1, 'tahun' => 2026,
                'revenue' => 1000, 'cost' => 1000 - $pnl, 'profit_loss' => $pnl, 'is_anomaly' => false,
            ]);
        }

        $response = $this->actingAs($user)->getJson(route('pnl.highlights', [
            'tahun' => 2026, 'bulan' => 'all', 'nop' => 'NOP BEKASI',
        ]))->assertOk()->assertJsonCount(3, 'top_profit')->assertJsonCount(3, 'top_loss')
            ->assertJsonPath('top_profit.0.site_id', 'RANK000')
            ->assertJsonPath('top_profit.1.site_id', 'RANK001')
            ->assertJsonPath('top_profit.2.site_id', 'RANK002')
            ->assertJsonPath('top_profit.0.revenue', 1000)
            ->assertJsonPath('top_profit.0.cost', 940)
            ->assertJsonPath('top_profit.0.margin', 6)
            ->assertJsonPath('top_profit.0.months_count', 1)
            ->assertJsonPath('top_loss.0.site_id', 'RANK004')
            ->assertJsonPath('top_loss.1.site_id', 'RANK005')
            ->assertJsonPath('top_loss.2.site_id', 'RANK003')
            ->assertJsonPath('summary.sites_count', 7)
            ->assertJsonPath('summary.revenue', 7000)
            ->assertJsonPath('summary.cost', 6900)
            ->assertJsonPath('summary.profit_loss', 100)
            ->assertJsonPath('summary.margin', 1.43);
        $this->assertNotContains('RANK006', array_column($response->json('top_loss'), 'site_id'));

        $this->getJson(route('pnl.highlights', ['tahun' => 2025, 'bulan' => 1]))
            ->assertOk()->assertJsonCount(0, 'top_profit')->assertJsonCount(0, 'top_loss')
            ->assertJsonPath('summary.sites_count', 0)->assertJsonPath('summary.margin', null);
    }
}
