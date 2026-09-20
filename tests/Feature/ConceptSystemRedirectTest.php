<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ConceptSystemRedirectTest extends TestCase
{
    public function test_config_system_urls_redirect_to_the_database_backed_modules(): void
    {
        $user = new User(['id' => 1]);

        $this->actingAs($user)->get('/infrastructure-management')
            ->assertRedirect('/infrastruktur');
        $this->actingAs($user)->get('/sewa-lahan')
            ->assertRedirect('/infrastruktur/sewa-lahan');
        $this->actingAs($user)->get('/site-telkomsel')
            ->assertRedirect('/infrastruktur/sewa-lahan?filter_field=ownership&filter_value=Telkomsel');
        $this->actingAs($user)->get('/site-tp')
            ->assertRedirect('/infrastruktur/sewa-lahan?filter_field=ownership&filter_value=TP');
        $this->actingAs($user)->get('/combat')
            ->assertRedirect('/infrastruktur/combat');
        $this->actingAs($user)->get('/rru')
            ->assertRedirect('/equipment-relocation');
        $this->actingAs($user)->get('/simawar')
            ->assertRedirect('/dashboard');
    }
}
