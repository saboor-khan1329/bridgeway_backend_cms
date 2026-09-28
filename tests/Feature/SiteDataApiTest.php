<?php

namespace Tests\Feature;

use Database\Seeders\NavigationSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDataApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_endpoint_matches_the_nextjs_layout_contract(): void
    {
        config(['frontend.cache.enabled' => false]);
        $this->seed([SiteSettingSeeder::class, NavigationSeeder::class]);

        $response = $this->getJson('/api/frontend/site')->assertOk();

        $response->assertJsonPath('data.contact.email', 'sales@bridgewaydigital.com')
            ->assertJsonPath('data.contact.phone.href', 'tel:+18322660227')
            ->assertJsonPath('data.navigation.services.title', 'Services')
            ->assertJsonPath('data.navigation.links.0.href', '/portfolio')
            ->assertJsonPath('data.footer.logo.src', '/images/white-logo.svg');

        $this->assertNotEmpty($response->json('data.navigation.services.links'));
        $this->assertNotEmpty($response->json('data.footer.linkGroups'));
        $this->assertContains('United States', $response->json('data.countries'));
    }
}
