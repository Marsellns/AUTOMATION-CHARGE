<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\PrivateDocumentStorage;

return new class extends Migration
{
    /** Move existing BAPSS and general upload PDFs out of public storage. */
    public function up(): void
    {
        PrivateDocumentStorage::move(DB::table('bapss')->whereNotNull('pdf_bapss')->pluck('pdf_bapss'), 'bapss/');
        PrivateDocumentStorage::move(DB::table('bapss')->whereNotNull('pdf_ba_dismantle')->pluck('pdf_ba_dismantle'), 'bapss/');
        PrivateDocumentStorage::move(DB::table('upload_files')->whereNotNull('file_path')->pluck('file_path'), 'upload-files/');
    }

    /** Do not expose internal PDFs again during a rollback. */
    public function down(): void
    {
        // Intentionally left blank.
    }

};
