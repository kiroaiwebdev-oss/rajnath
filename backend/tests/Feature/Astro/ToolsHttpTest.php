<?php

namespace Tests\Feature\Astro;

use App\Domain\Astro\Engine;
use App\Domain\Astro\Tools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ToolsHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app(Engine::class)->available()) {
            $this->markTestSkipped('engine not built');
        }
        DB::table('places')->insert(['id' => 1260086, 'name' => 'Patna', 'ascii_name' => 'Patna', 'admin1' => 'Bihar', 'country_code' => 'IN', 'country' => 'India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'population' => 1]);
    }

    private function input(string $type): array
    {
        $person = ['name' => 'Ravi', 'birth_date' => '1990-05-14', 'birth_time' => '06:30', 'place_id' => 1260086];

        return match ($type) {
            'birth' => $person,
            'couple' => ['a_name' => 'Ravi', 'a_birth_date' => '1990-05-14', 'a_birth_time' => '06:30', 'a_place_id' => 1260086, 'b_name' => 'Sita', 'b_birth_date' => '1992-08-20', 'b_birth_time' => '14:10', 'b_place_id' => 1260086],
            'name' => ['name' => 'Rajnath Jha'],
            'dob' => ['birth_date' => '1990-05-14', 'year' => 2026],
            'dob_month' => ['birth_date' => '1990-05-14', 'month' => '2026-11'],
            'name_dob' => ['name' => 'Rajnath Jha', 'birth_date' => '1990-05-14'],
            'vehicle' => ['birth_date' => '1990-05-14', 'plate' => 'BR01AB1234'],
            'two_names' => ['name' => 'Rama', 'partner' => 'Sita'],
        };
    }

    public function test_every_tool_renders_a_result_on_web_and_api(): void
    {
        $this->get('/tools')->assertOk()->assertSee('Free Kundli')->assertSee('FLAMES');
        foreach (Tools::TOOLS as $slug => [$title, $type]) {
            $this->get("/tools/$slug")->assertOk()->assertSee($title);
            $res = $this->post("/tools/$slug", $this->input($type));
            $res->assertOk();
            $this->assertNull($res->exception, $slug);
            $this->assertStringNotContainsString('Enter the details and press Calculate', $res->getContent(), $slug);
            $this->postJson("/api/v1/tools/$slug", $this->input($type))->assertOk()->assertJsonPath('data.tool', $slug);
            $this->app['cache']->store()->flush(); // keep within the per-minute limiter
        }
    }

    public function test_kundli_page_shows_chart_and_dasha(): void
    {
        $this->post('/tools/kundli', $this->input('birth'))->assertOk()
            ->assertSee('Rashi chart (D1)')->assertSee('Navamsa chart (D9)')->assertSee('Vimshottari dasha')->assertSee('Lahiri');
    }

    public function test_matching_shows_score_out_of_36(): void
    {
        $this->post('/tools/kundli-matching', $this->input('couple'))->assertOk()->assertSee('/ 36 gunas')->assertSee('Nadi');
    }

    public function test_validation_and_out_of_range_dates(): void
    {
        $this->post('/tools/kundli', ['birth_date' => '1700-01-01', 'birth_time' => '10:00', 'place_id' => 1260086])->assertSessionHasErrors('birth_date');
        $this->post('/tools/kundli', ['birth_date' => '1990-01-01', 'birth_time' => '10:00', 'place_id' => 99])->assertSessionHasErrors('place_id');
        $this->postJson('/api/v1/tools/nope', [])->assertNotFound();
        $this->get('/tools/nope')->assertNotFound();
    }

    public function test_panchang_web_and_api(): void
    {
        $this->get('/panchang?date=2026-10-28&place_id=1260086')->assertOk()->assertSee('Budhavara')->assertSee('Rahu Kaal')->assertSee('Tithi');
        $this->getJson('/api/v1/panchang?date=2026-10-28&place_id=1260086')->assertOk()->assertJsonPath('data.vara', 'Budhavara (Wednesday)');
        $this->get('/panchang')->assertOk();
    }

    public function test_engine_failure_shows_friendly_message(): void
    {
        config(['astro.binary' => '/nonexistent']);
        $this->app->forgetInstance(Engine::class);
        $this->post('/tools/kundli', $this->input('birth'))->assertOk()->assertSee('calculation service is unavailable');
        $this->postJson('/api/v1/tools/kundli', $this->input('birth'))->assertStatus(503)->assertJsonPath('error.code', 'engine_unavailable');
    }
}
