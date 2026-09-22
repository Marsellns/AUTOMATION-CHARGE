<?php

namespace Tests\Feature;

use App\Models\DocumentCirculation;
use App\Models\EquipmentRelocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EquipmentRelocationPresalesTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_equipment_inventory_can_be_monitored_updated_and_cleared(): void
    {
        $admin = $this->userWithRole('admin');
        $viewer = $this->userWithRole('viewer');
        $snapshot = json_decode(file_get_contents(public_path('data/equipment_relocation_inventory.json')), true);
        $key = $snapshot['rows'][0][0];

        $this->actingAs($admin)->get(route('equipment-relocation.index'))
            ->assertOk()->assertSee('Equipment Relocation')->assertSee('chart.umd.min.js');
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

    public function test_presales_upload_and_manager_approval_flow(): void
    {
        Storage::fake('public');
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->get('/po-monitoring/document-circulation')
            ->assertRedirect(route('presales.index'));

        $this->actingAs($admin)->post(route('presales.store'), [
            'document_title' => 'Dokumen Presales Uji',
            'document_number' => 'PRE-001',
            'document_file' => UploadedFile::fake()->create('presales.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $document = DocumentCirculation::firstOrFail();
        $this->assertSame('Pending', $document->status);
        $this->assertSame(2, $document->current_step);
        Storage::disk('public')->assertExists($document->file_path);
        $this->assertDatabaseHas('document_approvals', [
            'document_id' => $document->id,
            'step' => 1,
            'approver_id' => $admin->id,
            'approver_name' => $admin->name,
            'action' => 'uploaded',
        ]);
        $this->actingAs($admin)->get(route('presales.index'))
            ->assertOk()->assertSee('Dokumen Presales Uji');
        $this->actingAs($admin)->get(route('presales.show', $document))
            ->assertOk()->assertSee('Riwayat waktu')->assertSee('WIB');

        foreach (['manager_nop', 'manager_sq', 'manager_nos', 'manager_nbae'] as $role) {
            $manager = $this->userWithRole($role);
            $this->actingAs($manager)->post(route('presales.status', $document), [
                'action' => 'approve',
            ])->assertRedirect();
        }

        $document->refresh();
        $this->assertSame('Completed', $document->status);
        $this->assertSame(5, $document->approvals()->count());
        $this->actingAs($admin)->get(route('presales.file', $document))->assertOk();
    }

    public function test_presales_rejection_requires_reason_and_correct_manager(): void
    {
        $admin = $this->userWithRole('admin');
        $nop = $this->userWithRole('manager_nop');
        $sq = $this->userWithRole('manager_sq');
        $document = DocumentCirculation::create([
            'document_title' => 'Dokumen untuk penolakan',
            'document_number' => 'PRE-002',
            'file_name' => 'presales.pdf',
            'file_path' => 'document-circulation/presales.pdf',
            'status' => 'Pending',
            'current_step' => 2,
            'uploaded_by' => $admin->id,
            'uploaded_by_name' => $admin->name,
        ]);

        $this->actingAs($sq)->post(route('presales.status', $document), [
            'action' => 'approve',
        ])->assertForbidden();
        $this->actingAs($nop)->post(route('presales.status', $document), [
            'action' => 'reject',
        ])->assertSessionHasErrors('comments');
        $this->actingAs($nop)->post(route('presales.status', $document), [
            'action' => 'reject',
            'comments' => 'Nomor dokumen perlu diperbaiki.',
        ])->assertRedirect();

        $document->refresh();
        $this->assertSame('Rejected', $document->status);
        $this->assertSame('Nomor dokumen perlu diperbaiki.', $document->rejected_reason);
        $this->actingAs($nop)->post(route('presales.status', $document), [
            'action' => 'approve',
        ])->assertForbidden();
        $this->assertSame(1, $document->approvals()->count());
    }
}
