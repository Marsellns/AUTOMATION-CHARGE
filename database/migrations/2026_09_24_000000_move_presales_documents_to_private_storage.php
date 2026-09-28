<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Move existing confidential documents out of the web-accessible disk. */
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('private');

        DB::table('document_circulations')
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->pluck('file_path')
            ->each(function (string $path) use ($public, $private): void {
                if (!str_starts_with($path, 'document-circulation/') || !$public->exists($path)) {
                    return;
                }

                if (!$private->exists($path)) {
                    $private->put($path, $public->get($path));
                }

                // Delete only after the copy is confirmed to avoid data loss.
                if ($private->exists($path)) {
                    $public->delete($path);
                }
            });
    }

    /** Re-exposing confidential documents during rollback would be unsafe. */
    public function down(): void
    {
        // Intentionally left blank.
    }
};
