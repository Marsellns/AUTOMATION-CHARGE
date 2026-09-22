<?php

namespace Tests\Unit;

use App\Http\Controllers\InfrastructureDashboardController;
use App\Models\SiteOwner;
use App\Models\SewaLahanRenewal;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class ConceptSystemMigrationTest extends TestCase
{
    public function test_config_system_urls_resolve_to_the_active_application_routes(): void
    {
        $this->assertSame('/infrastructure-management', route('concept.infrastructure-management', [], false));
        $this->assertSame('/site-telkomsel', route('concept.site-telkomsel', [], false));
        $this->assertSame('/site-tp', route('concept.site-tp', [], false));
        $this->assertSame('/equipment-relocation', route('equipment-relocation.index', [], false));
    }

    public function test_ownership_scope_is_whitelisted_and_uses_the_existing_owner_classification(): void
    {
        $controller = app(InfrastructureDashboardController::class);
        $owners = new Collection([
            new SiteOwner(['site_code' => 'FALLBACK001', 'site_owner' => 'Tower Provider']),
        ]);

        $this->assertSame('Telkomsel', $this->invoke($controller, 'ownershipScope', ['TELKOMSEL']));
        $this->assertSame('TP', $this->invoke($controller, 'ownershipScope', ['tp']));
        $this->assertNull($this->invoke($controller, 'ownershipScope', ['other']));
        $this->assertSame('Telkomsel', $this->invoke($controller, 'ownerBucket', [
            new SewaLahanRenewal(['source_details' => ['ownership' => 'Telkomsel']]), $owners,
        ]));
        $this->assertSame('TP', $this->invoke($controller, 'ownerBucket', [
            new SewaLahanRenewal(['site_code' => 'FALLBACK001']), $owners->keyBy('site_code'),
        ]));
        $this->assertSame('TP', $this->invoke($controller, 'ownerBucket', [
            new SewaLahanRenewal(['site_code' => 'FALLBACK001', 'source_details' => ['ownership' => 'TP']]),
            new Collection([new SiteOwner(['site_code' => 'FALLBACK001', 'site_owner' => 'Mitratel'])])->keyBy('site_code'),
        ]));
        $this->assertSame('Telkomsel', $this->invoke($controller, 'ownerBucket', [
            new SewaLahanRenewal(['site_code' => 'FALLBACK001', 'source_details' => ['ownership' => 'null']]),
            new Collection([new SiteOwner(['site_code' => 'FALLBACK001', 'site_owner' => 'Telkomsel'])])->keyBy('site_code'),
        ]));
    }

    private function invoke(object $object, string $method, array $arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invokeArgs($object, $arguments);
    }
}
