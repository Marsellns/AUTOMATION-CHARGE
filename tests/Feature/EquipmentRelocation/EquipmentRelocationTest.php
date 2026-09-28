<?php

namespace Tests\Feature\EquipmentRelocation;

use App\Exports\EquipmentRelocationExport;
use App\Models\EquipmentRelocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class EquipmentRelocationTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_equipment_inventory_can_be_monitored_updated_and_cleared(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');
        $snapshot = json_decode(file_get_contents(public_path('data/equipment_relocation_inventory.json')), true);
        $key = $snapshot['rows'][0][0];

        $this->actingAs($admin)->get(route('equipment-relocation.index'))
            ->assertOk()
            ->assertSee('Equipment Relocation')
            ->assertSee('Download Excel')
            ->assertSee('Upload Excel')
            ->assertSee('chart.umd.min.js');
        $this->actingAs($viewer)->postJson(route('equipment-relocation.relocation-data.store'), [
            'donor_uniq_key' => $key,
        ])->assertForbidden();

        $this->actingAs($admin)->postJson(route('equipment-relocation.relocation-data.store'), [
            'donor_uniq_key' => $key,
            'pic' => 'NOP BEKASI',
            'progress' => 'ON GOING',
        ])->assertOk()->assertJsonPath('data.progress', 'ON GOING');

        $this->assertDatabaseHas('equipment_relocations', [
            'donor_uniq_key' => $key,
            'pic' => 'NOP BEKASI',
            'progress' => 'ON GOING',
        ]);
        $this->actingAs($admin)->deleteJson(route('equipment-relocation.relocation-data.destroy'), [
            'donor_uniq_key' => $key,
        ])->assertOk()->assertJsonPath('deleted', true);
        $this->assertSame(0, EquipmentRelocation::count());
    }

    public function test_equipment_relocation_excel_export_and_import_preserve_audit_fields(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');
        $snapshot = json_decode(file_get_contents(public_path('data/equipment_relocation_inventory.json')), true);
        $key = $snapshot['rows'][0][0];

        $this->actingAs($viewer)
            ->get(route('equipment-relocation.export-excel'))
            ->assertOk()
            ->assertDownload('equipment-relocation.xlsx');

        $this->assertSame([
            'id', 'donor_uniq_key', 'donor_acceptor', 'site_target_source', 'pic', 'progress', 'remark',
            'created_by', 'updated_by', 'created_at', 'updated_at',
        ], (new EquipmentRelocationExport())->headings());

        $this->actingAs($viewer)
            ->post(route('equipment-relocation.import-excel'), [
                'relocation_file' => $this->excelUpload([['donor_uniq_key' => $key]]),
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('equipment-relocation.import-excel'), [
                'relocation_file' => $this->excelUpload([[
                    'id' => 99999,
                    'donor_uniq_key' => $key,
                    'donor_acceptor' => 'Donor',
                    'site_target_source' => 'Target',
                    'pic' => 'nop bekasi',
                    'progress' => 'on going',
                    'remark' => 'Diimpor dari Excel',
                    'created_by' => 99999,
                    'updated_by' => 99999,
                    'created_at' => '2000-01-01 00:00:00',
                    'updated_at' => '2000-01-01 00:00:00',
                ]]),
            ])
            ->assertRedirect(route('equipment-relocation.index'))
            ->assertSessionHas('success');

        $created = EquipmentRelocation::query()->where('donor_uniq_key', $key)->firstOrFail();
        $createdAt = $created->created_at?->toDateTimeString();
        $this->assertSame($admin->id, $created->created_by);
        $this->assertSame($admin->id, $created->updated_by);
        $this->assertNotSame(99999, $created->id);
        $this->assertSame('NOP BEKASI', $created->pic);
        $this->assertSame('ON GOING', $created->progress);

        $this->actingAs($admin)
            ->post(route('equipment-relocation.import-excel'), [
                'relocation_file' => $this->excelUpload([[
                    'id' => $created->id + 10,
                    'donor_uniq_key' => $key,
                    'remark' => 'Diperbarui dari Excel',
                    'created_by' => 12345,
                ]]),
            ])
            ->assertRedirect(route('equipment-relocation.index'));

        $updated = $created->fresh();
        $this->assertSame($created->id, $updated->id);
        $this->assertSame($created->created_by, $updated->created_by);
        $this->assertSame($createdAt, $updated->created_at?->toDateTimeString());
        $this->assertSame('Diperbarui dari Excel', $updated->remark);
        $this->assertNull($updated->pic);
        $this->assertNull($updated->progress);
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function excelUpload(array $rows): UploadedFile
    {
        $headings = (new EquipmentRelocationExport())->headings();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headings, null, 'A1');

        foreach ($rows as $offset => $row) {
            $sheet->fromArray(
                [array_map(fn (string $heading) => $row[$heading] ?? null, $headings)],
                null,
                'A'.($offset + 2)
            );
        }

        $path = tempnam(sys_get_temp_dir(), 'equipment-relocation-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile(
            $path,
            'equipment-relocation.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
