<?php

namespace Tests\Feature;

use App\Support\PrivateDocumentStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateDocumentStorageTest extends TestCase
{
    public function test_copy_is_verified_before_public_document_is_removed(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        Storage::disk('public')->put('document-circulation/example.pdf', 'original document');
        PrivateDocumentStorage::move(['document-circulation/example.pdf'], 'document-circulation/');
        $this->assertSame('original document', Storage::disk('private')->get('document-circulation/example.pdf'));
        Storage::disk('public')->assertMissing('document-circulation/example.pdf');
        // Re-running after a completed migration is safe.
        PrivateDocumentStorage::move(['document-circulation/example.pdf'], 'document-circulation/');
        Storage::disk('private')->assertExists('document-circulation/example.pdf');
    }

    public function test_conflicting_private_copy_preserves_both_documents_and_blocks_migration(): void
    {
        Storage::fake('private');
        Storage::fake('public');
        Storage::disk('public')->put('bapss/example.pdf', 'original document');
        Storage::disk('private')->put('bapss/example.pdf', 'different document');
        try {
            PrivateDocumentStorage::move(['bapss/example.pdf'], 'bapss/');
            $this->fail('A conflicting private copy must block migration.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('berbeda', $exception->getMessage());
        }
        $this->assertSame('original document', Storage::disk('public')->get('bapss/example.pdf'));
        $this->assertSame('different document', Storage::disk('private')->get('bapss/example.pdf'));
    }
}
