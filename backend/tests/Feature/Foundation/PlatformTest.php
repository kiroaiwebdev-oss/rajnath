<?php

namespace Tests\Feature\Foundation;

use Database\Seeders\ShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_with_brand_and_confirmed_biography(): void
    {
        $this->seed(ShowcaseSeeder::class);
        $this->get('/')->assertOk()
            ->assertSee('Astro Rajnath Jha')
            ->assertSee('Jyotirveda Vigyan Sansthan')
            ->assertSee('participation in the Sri Lanka Sacred Astrological Symposium')
            ->assertSee('Source code (AGPL-3.0)');
    }

    public function test_security_headers_are_set(): void
    {
        $r = $this->get('/');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $csp);
    }

    public function test_staff_pages_are_not_cached(): void
    {
        $this->get('/staff/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_health_endpoints(): void
    {
        $this->get('/up')->assertOk();
        $this->getJson('/ready')->assertOk()->assertJson(['status' => 'ready', 'checks' => ['database' => true, 'cache' => true, 'storage' => true, 'migrations' => true]]);
    }

    public function test_api_meta(): void
    {
        $this->getJson('/api/v1/meta')->assertOk()->assertJson(['api_version' => 'v1', 'brand' => 'Astro Rajnath Jha']);
    }

    public function test_design_system_page_renders(): void
    {
        $this->get('/design-system')->assertOk()->assertSee('Design system');
    }
}
