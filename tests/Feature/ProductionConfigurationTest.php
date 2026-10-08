<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProductionConfigurationTest extends TestCase
{
    public function test_production_preserves_secure_cookie_and_generates_https_urls_even_for_cli_or_http_bootstrap(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config()->set(['app.force_https' => true, 'session.secure' => true]);
        $this->app->instance('request', Request::create('http://simaster.example.test/login'));
        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('session.secure'));
        $this->assertStringStartsWith('https://', URL::to('/login'));
    }

    public function test_local_http_remains_usable_without_secure_only_cookies(): void
    {
        $this->app->detectEnvironment(fn (): string => 'local');
        config()->set('session.secure', true);
        $this->app->instance('request', Request::create('http://localhost/login'));
        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('session.secure'));
    }
}
