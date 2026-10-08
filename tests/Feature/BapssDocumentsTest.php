<?php

namespace Tests\Feature;

use App\Imports\Datasets\BapssImport;
use App\Models\Bapss;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BapssDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_label_is_not_a_download_and_import_preserves_existing_attachment(): void
    {
        Storage::fake('private');
        $path = 'bapss/existing.pdf';
        Storage::disk('private')->put($path, '%PDF-1.7 original');
        $record = Bapss::factory()->create(['site_code' => 'BAP001', 'pdf_bapss' => $path, 'pdf_ba_dismantle' => null]);
        (new BapssImport)->incremental()->collection(collect([collect([
            'site_id' => 'BAP001', 'site_name' => 'Updated site', 'pdf_bapss' => 'Download', 'pdf_ba_dismantle' => 'Download',
        ])]));
        $record->refresh();
        $this->assertSame($path, $record->pdf_bapss);
        $this->assertTrue($record->pdfAvailable('pdf_bapss'));
        $this->assertNull($record->pdf_ba_dismantle);
        $this->assertSame('Download', $record->source_documents['pdf_ba_dismantle']);
        $this->assertSame('Berkas sumber belum tersedia', $record->missingPdfLabel('pdf_ba_dismantle'));
        $this->actingAs(User::factory()->create(['account_status' => 'approved']))
            ->get(route('infrastruktur.bapss.show', $record))->assertOk()->assertSee('Berkas sumber belum tersedia');
    }

    public function test_manifest_copies_one_verified_private_pdf_for_multiple_sites_and_is_repeatable(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        $records = collect(['BAP001', 'BAP002'])->map(fn ($code) => Bapss::factory()->create([
            'site_code' => $code, 'pdf_ba_dismantle' => null,
        ]));
        $sourceDirectory = Storage::disk('private')->path('source');
        mkdir($sourceDirectory, 0755, true);
        file_put_contents($sourceDirectory.'/document.pdf', '%PDF-1.7 source document');
        file_put_contents($sourceDirectory.'/manifest.json', json_encode([
            ['file' => 'document.pdf', 'type' => 'dismantle', 'site_ids' => ['BAP001', 'BAP002']],
        ]));
        $this->artisan('dataset:attach-bapss-pdfs', ['manifest' => $sourceDirectory.'/manifest.json'])->assertSuccessful();
        $path = $records->first()->fresh()->pdf_ba_dismantle;
        $this->assertSame($path, $records->last()->fresh()->pdf_ba_dismantle);
        $this->assertSame('%PDF-1.7 source document', Storage::disk('private')->get($path));
        Storage::disk('public')->assertMissing($path);
        $this->artisan('dataset:attach-bapss-pdfs', ['manifest' => $sourceDirectory.'/manifest.json'])->assertSuccessful();
        $this->assertSame($path, $records->first()->fresh()->pdf_ba_dismantle);
    }

    public function test_shared_reference_uses_the_canonical_document_and_cycles_do_not_recurse(): void
    {
        Storage::fake('private');
        Storage::disk('private')->put('bapss/shared.pdf', '%PDF-1.7 shared');
        Bapss::factory()->create(['site_code' => 'SOURCE1', 'pdf_bapss' => 'bapss/shared.pdf']);
        $shared = Bapss::factory()->create([
            'site_code' => 'SHARED1', 'pdf_bapss' => null, 'pdf_ba_dismantle' => null,
            'remark' => 'Dokumen BAPSS dan BA Dismantle Sama dengan SOURCE1',
        ]);
        $this->assertSame('bapss/shared.pdf', $shared->pdfPath('pdf_bapss'));
        $this->actingAs(User::factory()->create(['account_status' => 'approved']))
            ->get(route('infrastruktur.bapss.file', [$shared, 'bapss']))->assertOk();
        Bapss::factory()->create(['site_code' => 'CYCLE1', 'pdf_bapss' => null, 'remark' => 'Dokumen BAPSS dan BA Dismantle Sama dengan CYCLE2']);
        $cycle = Bapss::factory()->create(['site_code' => 'CYCLE2', 'pdf_bapss' => null, 'remark' => 'Dokumen BAPSS dan BA Dismantle Sama dengan CYCLE1']);
        $this->assertNull($cycle->pdfPath('pdf_bapss'));
    }
}
