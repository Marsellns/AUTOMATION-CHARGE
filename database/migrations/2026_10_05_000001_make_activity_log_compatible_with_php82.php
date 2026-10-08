<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table): void {
                $table->uuid('batch_uuid')->nullable()->index();
            });
        }

        // Activitylog 4 stores changes in properties. Keep the original
        // Activitylog 5 column and timestamps as well as all custom metadata.
        DB::table('activity_log')->whereNotNull('attribute_changes')
            ->select(['id', 'properties', 'attribute_changes'])->chunkById(200, function ($records): void {
                foreach ($records as $record) {
                    $changes = json_decode($record->attribute_changes, true, flags: JSON_THROW_ON_ERROR);
                    $properties = json_decode($record->properties ?? '{}', true, flags: JSON_THROW_ON_ERROR) ?? [];
                    if (! is_array($changes) || ! is_array($properties)) {
                        throw new RuntimeException('Invalid audit log JSON on record '.$record->id);
                    }
                    $merged = $properties;
                    foreach (['attributes', 'old'] as $key) {
                        if (array_key_exists($key, $changes) && ! array_key_exists($key, $merged)) {
                            $merged[$key] = $changes[$key];
                        }
                    }
                    if ($merged !== $properties) {
                        DB::table('activity_log')->where('id', $record->id)->update([
                            'properties' => json_encode($merged, JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Retain audit history and batch identifiers when reverting code.
    }
};
