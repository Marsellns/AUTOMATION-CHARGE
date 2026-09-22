<?php

namespace Tests\Unit;

use App\Support\CombatSourceDetails;
use Tests\TestCase;

class CombatSourceDetailsTest extends TestCase
{
    public function test_it_reads_master_sheet_dimensions_from_nested_combat_snapshot(): void
    {
        $details = CombatSourceDetails::flattened([
            'database' => [
                'status' => 'On Air',
                'nop' => 'NOP BEKASI',
                'tp' => 'TELKOMSEL',
            ],
            'database_revenue' => [
                'nop' => 'NOP LAMA',
                'revenue_jan_2026' => 100000,
            ],
        ]);

        $this->assertSame('On Air', $details['status']);
        $this->assertSame('NOP BEKASI', $details['nop']);
        $this->assertSame('TELKOMSEL', $details['tp']);
        $this->assertSame(100000, $details['revenue_jan_2026']);
    }
}
