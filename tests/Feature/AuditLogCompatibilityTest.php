<?php

namespace Tests\Feature;

use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_update_and_delete_are_logged_with_original_and_new_values(): void
    {
        $site = Site::create(['site_id' => 'AUDIT-82', 'site_name' => 'Original name']);
        $site->update(['site_name' => 'Updated name']);
        $site->delete();

        $activities = Activity::query()->where('subject_type', Site::class)->where('subject_id', $site->id)->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $activities->pluck('event')->all());
        $this->assertSame('Original name', $activities[0]->changes->get('attributes')['site_name']);
        $this->assertSame('Original name', $activities[1]->changes->get('old')['site_name']);
        $this->assertSame('Updated name', $activities[1]->changes->get('attributes')['site_name']);
        $this->assertSame('Updated name', $activities[2]->changes->get('old')['site_name']);
    }

    public function test_legacy_changes_and_metadata_survive_an_idempotent_compatibility_migration(): void
    {
        $changes = json_encode(['old' => ['site_name' => 'Before'], 'attributes' => ['site_name' => 'After']], JSON_THROW_ON_ERROR);
        $id = DB::table('activity_log')->insertGetId([
            'description' => 'updated', 'event' => 'updated', 'attribute_changes' => $changes,
            'properties' => json_encode(['origin' => 'legacy', 'old' => ['site_name' => 'Existing metadata']], JSON_THROW_ON_ERROR),
            'created_at' => '2026-08-19 00:00:00', 'updated_at' => '2026-08-19 00:00:00',
        ]);
        $migration = require database_path('migrations/2026_10_05_000001_make_activity_log_compatible_with_php82.php');
        $migration->up();
        $first = DB::table('activity_log')->find($id);
        $migration->up();
        $second = DB::table('activity_log')->find($id);

        $this->assertSame($changes, json_encode(json_decode($second->attribute_changes, true), JSON_THROW_ON_ERROR));
        $this->assertSame($first->properties, $second->properties);
        $this->assertSame('2026-08-19 00:00:00', $second->updated_at);
        $activity = Activity::findOrFail($id);
        $this->assertSame('legacy', $activity->properties->get('origin'));
        $this->assertSame('After', $activity->changes->get('attributes')['site_name']);
        $this->assertSame('Existing metadata', $activity->changes->get('old')['site_name']);
    }
}
