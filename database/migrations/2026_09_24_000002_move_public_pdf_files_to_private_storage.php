<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Move existing BAPSS and general upload PDFs out of public storage. */
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('private');

        $this->moveFiles(DB::table('bapss')->whereNotNull('pdf_bapss')->pluck('pdf_bapss'), $public, $private, 'bapss/');
        $this->moveFiles(DB::table('bapss')->whereNotNull('pdf_ba_dismantle')->pluck('pdf_ba_dismantle'), $public, $private, 'bapss/');
        $this->moveFiles(DB::table('upload_files')->whereNotNull('file_path')->pluck('file_path'), $public, $private, 'upload-files/');
    }

    /** Do not expose internal PDFs again during a rollback. */
    public function down(): void
    {
        // Intentionally left blank.
    }

    private function moveFiles(iterable $paths, $public, $private, string $prefix): void
    {
        foreach ($paths as $path) {
            if (!is_string($path) || !str_starts_with($path, $prefix) || str_contains($path, '..') || !$public->exists($path)) {
                continue;
            }

            if (!$private->exists($path)) {
                $private->put($path, $public->get($path));
            }

            if ($private->exists($path)) {
                $public->delete($path);
            }
        }
    }
};
