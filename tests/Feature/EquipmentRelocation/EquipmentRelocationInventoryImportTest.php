<?php

namespace Tests\Feature\EquipmentRelocation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentRelocationInventoryImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_import_requires_explicit_replace_for_existing_mysql_data(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'equipment-inventory-');

        try {
            file_put_contents($path, json_encode($this->snapshot([
                $this->row('UNIT-001'),
                $this->row('UNIT-002'),
            ])));

            $this->artisan('equipment:import-inventory', ['file' => $path])->assertExitCode(0);
            $this->assertDatabaseCount('equipment_relocation_inventory', 2);
            $this->assertDatabaseHas('equipment_relocation_inventory', [
                'uniq_key' => 'UNIT-001',
                'site_id' => 'BKS001',
            ]);

            $this->artisan('equipment:import-inventory', ['file' => $path])->assertExitCode(1);
            $this->assertDatabaseCount('equipment_relocation_inventory', 2);

            file_put_contents($path, json_encode($this->snapshot([$this->row('UNIT-003')])));
            $this->artisan('equipment:import-inventory', [
                'file' => $path,
                '--replace' => true,
            ])->assertExitCode(0);

            $this->assertDatabaseCount('equipment_relocation_inventory', 1);
            $this->assertDatabaseHas('equipment_relocation_inventory', ['uniq_key' => 'UNIT-003']);
        } finally {
            unlink($path);
        }
    }

    private function snapshot(array $rows): array
    {
        return [
            'schema' => 1,
            'columns' => [
                'uniq_key', 'site_id', 'nop', 'region', 'to_name', 'ne_name',
                'equipment_group', 'equipment_type', 'category', 'board_name',
                'board_type', 'serial_number', 'utilization_status', 'safe_to_reloc',
            ],
            'rows' => $rows,
        ];
    }

    private function row(string $key): array
    {
        return [
            $key, 'BKS001', 'NOP BEKASI', 'JABOTABEK', 'TO', 'NE',
            'RU', 'Radio Unit', 'RU', 'Board', 'Type', 'SN-001', 'Utilized', 'Safe',
        ];
    }
}
