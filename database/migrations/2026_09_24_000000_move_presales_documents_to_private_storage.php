<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\PrivateDocumentStorage;

return new class extends Migration
{
    /** Move existing confidential documents out of the web-accessible disk. */
    public function up(): void
    {
        PrivateDocumentStorage::move(DB::table('document_circulations')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->pluck('file_path'), 'document-circulation/');
    }

    /** Re-exposing confidential documents during rollback would be unsafe. */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
