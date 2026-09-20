<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE document_circulations MODIFY current_step TINYINT UNSIGNED NOT NULL DEFAULT 2');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE document_circulations MODIFY current_step TINYINT UNSIGNED NOT NULL DEFAULT 1');
    }
};
