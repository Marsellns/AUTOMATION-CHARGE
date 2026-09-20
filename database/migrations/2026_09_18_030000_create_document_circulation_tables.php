<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('document_circulations', function (Blueprint $table) {
            $table->id();
            $table->string('document_title');
            $table->string('document_number')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('status', 30)->default('Pending')->index();
            // Step 1 is the upload event; Manager NOP is the first approval
            // owner and therefore starts at step 2.
            $table->unsignedTinyInteger('current_step')->default(2)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('uploaded_by_name')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('document_circulations')->cascadeOnDelete();
            $table->unsignedTinyInteger('step');
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_name');
            $table->string('action', 20);
            $table->text('comments')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
            $table->index(['document_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('document_circulations');
    }
};
