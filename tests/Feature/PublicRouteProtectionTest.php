<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicRouteProtectionTest extends TestCase
{
    public function test_email_test_endpoint_is_not_publicly_accessible(): void
    {
        $this->get('/mail-test')->assertNotFound();
    }
}
