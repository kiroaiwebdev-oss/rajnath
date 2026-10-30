<?php

namespace Tests\Feature\Phase13;

use App\Domain\Astro\LunarCalendar;
use App\Domain\Puja\PujaBookings;
use App\Http\Controllers\FileLinkController;
use App\Mail\OtpMail;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** App parity API (support, contact change, privacy, signed files, content), purnimanta months, and SEO/AEO/GEO output. */
class ParityAndSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        DB::table('places')->insert(['id' => 1260086, 'name' => 'Patna', 'ascii_name' => 'Patna', 'admin1' => 'Bihar', 'country_code' => 'IN', 'country' => 'India',
            'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'population' => 1]);
    }

    private function customer(string $email = 'asha@example.com'): User
    {
        $u = new User(['name' => 'Asha', 'email' => $email]);
        $u->save();

        return $u;
    }

    private function token(User $u, string $name = 'Phone'): string
    {
        return $u->createToken($name, ['customer'])->plainTextToken;
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(OtpMail::class, function (OtpMail $m) use (&$code) {
            $code = $m->code;

            return true;
        });

        return $code;
    }

    public function test_support_tickets_through_the_api(): void
    {
        $u = $this->customer();
        $t = $this->token($u);
        $this->withToken($t)->getJson('/api/v1/support')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.categories.0.key', 'booking');
        $this->withToken($t)->postJson('/api/v1/support', ['category' => 'nope', 'subject' => 'x', 'body' => 'y'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $ref = $this->withToken($t)->postJson('/api/v1/support', ['category' => 'report', 'subject' => 'Report delayed', 'body' => 'Where is my report?'])
            ->assertCreated()->assertJsonPath('data.subject', 'Report delayed')->json('data.ref');
        $this->withToken($t)->postJson("/api/v1/support/$ref/reply", ['body' => 'Any update?'])->assertOk()
            ->assertJsonPath('data.messages.0.from', 'you')->assertJsonCount(2, 'data.messages');
        $this->withToken($t)->postJson("/api/v1/support/$ref/close")->assertOk()->assertJsonPath('data.status', 'closed');

        // another customer cannot see it
        $other = $this->token($this->customer('other@example.com'));
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson("/api/v1/support/$ref")->assertNotFound();
        $this->withToken($other)->postJson("/api/v1/support/$ref/reply", ['body' => 'hi'])->assertNotFound();
    }

    public function test_contact_change_needs_the_code_sent_to_the_new_address(): void
    {
        Mail::fake();
        $u = $this->customer();
        $this->customer('taken@example.com');
        $t = $this->token($u);
        $this->withToken($t)->postJson('/api/v1/me/contact', ['contact' => 'taken@example.com'])->assertUnprocessable()->assertJsonValidationErrors('contact');
        $challenge = $this->withToken($t)->postJson('/api/v1/me/contact', ['contact' => 'New@Example.com'])->assertOk()->json('data.challenge');
        $this->withToken($t)->postJson('/api/v1/me/contact/confirm', ['challenge' => $challenge, 'code' => '000000'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame('asha@example.com', $u->fresh()->email);
        $this->withToken($t)->postJson('/api/v1/me/contact/confirm', ['challenge' => $challenge, 'code' => $this->lastCode()])->assertOk();
        $this->assertSame('new@example.com', $u->fresh()->email);
    }

    public function test_sign_out_other_devices_keeps_this_one(): void
    {
        $u = $this->customer();
        $mine = $this->token($u, 'A');
        $this->token($u, 'B');
        $this->withToken($mine)->postJson('/api/v1/me/sign-out-others')->assertOk();
        $this->assertSame(['A'], $u->tokens()->pluck('name')->all());
    }

    public function test_privacy_export_and_delete_need_a_fresh_code(): void
    {
        Mail::fake();
        $u = $this->customer();
        $t = $this->token($u);
        $this->withToken($t)->getJson('/api/v1/privacy')->assertOk()->assertJsonPath('data.blockers', []);
        $this->withToken($t)->postJson('/api/v1/privacy/export', [])->assertUnprocessable()->assertJsonValidationErrors(['challenge', 'code']);

        $challenge = $this->withToken($t)->postJson('/api/v1/privacy/code')->assertOk()->json('data.challenge');
        $code = $this->lastCode();
        $this->withToken($t)->postJson('/api/v1/privacy/export', ['challenge' => $challenge, 'code' => $code])->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        // the code is single use
        $this->withToken($t)->postJson('/api/v1/privacy/export', ['challenge' => $challenge, 'code' => $code])->assertUnprocessable();

        Mail::fake();
        $challenge = $this->withToken($t)->postJson('/api/v1/privacy/code')->json('data.challenge');
        $this->withToken($t)->postJson('/api/v1/privacy/delete', ['challenge' => $challenge, 'code' => $this->lastCode(), 'confirm' => 'delete'])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        Mail::fake();
        $challenge = $this->withToken($t)->postJson('/api/v1/privacy/code')->json('data.challenge');
        $this->withToken($t)->postJson('/api/v1/privacy/delete', ['challenge' => $challenge, 'code' => $this->lastCode(), 'confirm' => 'DELETE'])->assertOk()->assertJsonPath('data.status', 'deleted');
        $this->assertNull($u->fresh()->email);
    }

    public function test_signed_file_links_check_signature_expiry_and_owner(): void
    {
        Storage::fake(PujaBookings::DISK);
        Storage::disk(PujaBookings::DISK)->put('puja/p1.jpg', 'jpeg-bytes');
        $u = $this->customer();
        $ref = (string) Str::ulid();
        Schema::withoutForeignKeyConstraints(fn () => DB::table('puja_bookings')->insert(['ref' => $ref, 'user_id' => $u->id, 'order_id' => 999, 'offering_id' => 1,
            'status' => 'completed', 'evidence' => json_encode(['photos' => ['puja/p1.jpg']]), 'created_at' => now(), 'updated_at' => now()]));

        $url = FileLinkController::url('puja-photo', $ref, $u->id);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        // tampered owner, missing signature, other photo index
        $this->get(str_replace('u='.$u->id, 'u='.($u->id + 1), $url))->assertForbidden();
        $this->get(strtok($url, '?').'?i=0&u='.$u->id)->assertForbidden();
        $this->get(FileLinkController::url('puja-photo', $ref, $u->id, 3))->assertNotFound();
        $this->get(FileLinkController::url('puja-photo', $ref, $u->id + 1))->assertNotFound();
        $this->get(FileLinkController::url('invoice', (string) Str::ulid(), $u->id))->assertNotFound();
        // links last 15 minutes
        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_public_content_api_for_the_app(): void
    {
        app(SettingsRepository::class)->set('contact.phone', '+91 90000 11111');
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonPath('locales.0', 'en');
        $this->getJson('/api/v1/site')->assertOk()->assertJsonPath('data.phone', '+91 90000 11111')
            ->assertJsonStructure(['data' => ['name', 'address' => ['line'], 'hours', 'about', 'web' => ['terms', 'privacy']]]);
        $this->getJson('/api/v1/faq')->assertOk()->assertJsonStructure(['data' => [['question', 'answer']]]);
        $this->getJson('/api/v1/calendar?month=2026-11&place_id=1260086')->assertOk()->assertJsonCount(30, 'data');
        $this->getJson('/api/v1/calendar?month=1800-01')->assertUnprocessable();
        $this->getJson('/api/v1/rashi/mesha')->assertOk()->assertJsonPath('data.name', 'Mesha (Aries)');
        $this->getJson('/api/v1/rashi/nope')->assertNotFound();
        $this->getJson('/api/v1/navagraha')->assertOk()->assertJsonCount(9, 'data');

        $modes = $this->getJson('/api/v1/catalogue/families')->assertOk()->json('meta.modes');
        $this->assertNotEmpty($modes);
        $video = $this->getJson('/api/v1/catalogue/offerings?mode=video')->assertOk()->json('data');
        $this->assertNotEmpty($video);
        $this->assertLessThan(count($this->getJson('/api/v1/catalogue/offerings')->json('data')), count($video));
    }

    public function test_tool_results_carry_a_display_block(): void
    {
        $this->postJson('/api/v1/tools/flames', ['name' => 'Ravi', 'partner' => 'Sita'])->assertOk()->assertJsonStructure(['data' => ['display' => ['sections']]]);
        $r = $this->postJson('/api/v1/tools/kundli', ['birth_date' => '1990-05-14', 'birth_time' => '06:30', 'place_id' => 1260086])->assertOk();
        $this->assertNotEmpty($r->json('data.display.sections'));
    }

    public function test_purnimanta_month_names(): void
    {
        $ashwin = ['masa' => 6, 'adhik' => false];
        $this->assertSame(LunarCalendar::name($ashwin), LunarCalendar::purnimantaName($ashwin, 10));
        $this->assertSame(LunarCalendar::name(['masa' => 7, 'adhik' => false]), LunarCalendar::purnimantaName($ashwin, 19));
        $this->assertSame(LunarCalendar::name(['masa' => 0, 'adhik' => false]), LunarCalendar::purnimantaName(['masa' => 11, 'adhik' => false], 20));

        // 29 Oct 2026 is Krishna Chaturthi: Ashwin (amanta), Kartik (purnimanta)
        $masa = $this->getJson('/api/v1/panchang?date=2026-10-29&place_id=1260086')->assertOk()->json('data.masa');
        $this->assertSame('Ashwin', $masa['amanta']);
        $this->assertStringStartsWith('Kart', $masa['purnimanta']);
    }

    public function test_crawl_files(): void
    {
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /account')->assertSee('Sitemap: '.url('/sitemap.xml'));
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringContainsString(url('/services/janma-kundali-reading'), $xml);
        $this->assertStringContainsString(url('/tools/kundli'), $xml);
        $this->assertStringNotContainsString('/account', $xml);
        $this->get('/llms.txt')->assertOk()->assertSee('# ')->assertSee(url('/services/janma-kundali-reading'));
        $this->get('/llms-full.txt')->assertOk()->assertSee('Questions and answers')->assertSee('Q: ');
    }

    public function test_pages_have_structured_data_and_private_pages_are_noindex(): void
    {
        $pages = ['/' => 'ItemList', '/about' => 'ProfilePage', '/faq' => 'FAQPage', '/services/janma-kundali-reading' => 'Service', '/tools' => 'BreadcrumbList',
            '/contact' => 'ContactPage', '/panchang' => 'BreadcrumbList', '/rashi/mesha' => 'BreadcrumbList'];
        foreach ($pages as $path => $type) {
            $html = $this->get($path)->assertOk()->assertSee('<link rel="canonical"', false)->getContent();
            preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
            $this->assertNotEmpty($m[1], "no JSON-LD on $path");
            foreach ($m[1] as $json) {
                $this->assertIsArray(json_decode($json, true), "invalid JSON-LD on $path");
            }
            $this->assertStringContainsString('"'.$type.'"', implode('', $m[1]), "$type missing on $path");
            $this->assertStringNotContainsString('noindex', $html, "$path should be indexable");
        }
        $this->get('/account/sign-in')->assertOk()->assertSee('noindex', false);
    }
}
