<?php

namespace Tests\Feature\Phase13;

use App\Domain\Astro\Engine;
use App\Models\Offering;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\HindiCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Hindi website and catalogue (RP-24) and the app's content API (horoscope, gochar, muhurat, festivals). */
class ContentAndLanguageTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(HindiCatalogueSeeder::class);
        $this->syncRbac();
    }

    public function test_hindi_website_and_catalogue(): void
    {
        $this->get('/services/janma-kundali-reading')->assertOk()->assertSee('Janma Kundali Reading')->assertSee('हिन्दी');
        $this->get('/services/janma-kundali-reading?lang=hi')->assertOk()->assertSee('जन्म कुंडली विश्लेषण')->assertSee('क्या शामिल है')
            ->assertSee('<html lang="hi">', false)->assertSee('English');
        // the choice is remembered for the next page
        $this->get('/services')->assertOk()->assertSee('सही मार्गदर्शन खोजें')->assertSee('परामर्श');
        $this->get('/?lang=en')->assertOk()->assertSee('Browse by category');
        // a language that is not switched on is ignored (the earlier choice, English, stays)
        $this->get('/services?lang=ta')->assertOk()->assertSee('Find the right guidance')->assertSee('<html lang="en">', false);
        $this->get('/faq?lang=hi')->assertOk()->assertSee('परामर्श कैसे बुक करें?');
        // a service without Hindi text falls back to English
        Offering::where('slug', 'numerology')->first()->translations()->where('locale', 'hi')->delete();
        $this->get('/services/numerology?lang=hi')->assertOk()->assertSee('Numerology');

        // API: Accept-Language or ?lang
        $this->getJson('/api/v1/catalogue/offerings/janma-kundali-reading', ['Accept-Language' => 'hi-IN,hi;q=0.9'])->assertOk()->assertJsonPath('data.title', 'जन्म कुंडली विश्लेषण');
        $this->getJson('/api/v1/catalogue/families?lang=hi')->assertOk()->assertJsonFragment(['name' => 'परामर्श']);
        $res = $this->getJson('/api/v1/catalogue/offerings/consultation-with-acharya-ji?lang=hi')->assertOk();
        $this->assertContains('ऑडियो कॉल — 30 मिनट', array_column($res->json('data.variants'), 'name'));
        $this->getJson('/api/v1/catalogue/offerings/janma-kundali-reading')->assertJsonPath('data.title', 'Janma Kundali Reading');

        // the control centre stays English
        $this->asStaff($this->makeStaff(['owner']))->get('/admin?lang=hi')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_admin_edits_and_removes_a_translation(): void
    {
        $o = Offering::where('slug', 'business-astrology')->first();
        $editor = $this->makeStaff(['content_author']);
        $this->asStaff($editor)->put(route('admin.catalogue.translation', [$o->ref, 'hi']), ['title' => 'x', 'summary' => 'y'])->assertForbidden();
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->get(route('admin.catalogue.edit', [$o->ref, 'locale' => 'hi']))->assertOk()->assertSee('Other languages')->assertSee('व्यापार ज्योतिष');
        $v = $o->variants()->first();
        $this->asStaff($admin)->put(route('admin.catalogue.translation', [$o->ref, 'hi']), ['title' => 'बिज़नेस ज्योतिष', 'summary' => 'नया सारांश', 'includes' => "एक\nदो",
            'variant_names' => [$v->ref => 'वीडियो परामर्श']])->assertSessionHasNoErrors();
        $this->get('/services/business-astrology?lang=hi')->assertSee('बिज़नेस ज्योतिष')->assertSee('नया सारांश')->assertSee('वीडियो परामर्श');
        $this->asStaff($admin)->put(route('admin.catalogue.translation', [$o->ref, 'xx']), ['title' => 'a', 'summary' => 'b'])->assertNotFound();
        $this->asStaff($admin)->put(route('admin.catalogue.translation', [$o->ref, 'hi']), ['title' => ''])->assertSessionHasNoErrors();
        $this->get('/services/business-astrology?lang=hi')->assertSee('Business Astrology');
        // the seeder does not overwrite owner edits
        $this->asStaff($admin)->put(route('admin.catalogue.translation', [$o->ref, 'hi']), ['title' => 'मेरा शीर्षक', 'summary' => 'मेरा सारांश'])->assertSessionHasNoErrors();
        $this->seed(HindiCatalogueSeeder::class);
        $this->assertSame('मेरा शीर्षक', $o->translations()->where('locale', 'hi')->value('title'));
    }

    public function test_content_api_for_the_app(): void
    {
        $this->getJson('/api/v1/horoscopes/types')->assertOk()->assertJsonCount(5, 'data.kinds')->assertJsonPath('data.periods.3.key', 'halfyearly');
        DB::table('horoscopes')->insert(['kind' => 'rashi', 'topic' => 'love', 'period' => 'weekly', 'sign' => 2, 'starts_on' => now('Asia/Kolkata')->startOfWeek()->toDateString(),
            'ends_on' => now('Asia/Kolkata')->startOfWeek()->addDays(6)->toDateString(), 'locale' => 'en', 'body' => 'Gemini: an honest talk brings you closer this week.', 'status' => 'published',
            'author_id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/horoscopes?kind=rashi&period=weekly&topic=love&lang=en')->assertOk()->assertJsonPath('data.0.subject', 2)
            ->assertJsonPath('data.0.name', 'Mithuna (Gemini)')->assertJsonCount(12, 'meta.subjects');
        $this->getJson('/api/v1/horoscopes?kind=rashi&period=weekly&lang=en')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/horoscopes?kind=gochar')->assertJsonValidationErrors('kind');
        $this->getJson('/api/v1/muhurat')->assertOk()->assertJsonCount(7, 'data');

        if (! app(Engine::class)->available()) {
            $this->markTestSkipped('Astronomy engine not built');
        }
        $this->getJson('/api/v1/gochar')->assertOk()->assertJsonCount(9, 'data')->assertJsonPath('data.4.planet', 'jupiter');
        $j = $this->getJson('/api/v1/gochar/jupiter?year=2026')->assertOk();
        $this->assertContains('ingress', array_column($j->json('data.events'), 'type'));
        $this->assertCount(12, $j->json('data.by_moon_sign'));
        $this->getJson('/api/v1/gochar/pluto')->assertNotFound();
        $m = $this->getJson('/api/v1/muhurat/vivah?month=2026-12')->assertOk();
        $this->assertSame(['2026-12-02', '2026-12-03'], array_slice(array_column($m->json('data'), 'date'), 0, 2));
        $this->assertNotEmpty($m->json('data.0.windows.0.tithi'));
        $f = $this->getJson('/api/v1/festivals?year=2026&religion=hindu')->assertOk();
        $this->assertContains(['date' => '2026-11-08', 'name' => 'Diwali (Lakshmi Puja)'], array_map(fn ($x) => ['date' => $x['date'], 'name' => $x['name']], $f->json('data')));
        $this->assertSame(['hindu'], array_values(array_unique(array_column($f->json('data'), 'religion'))));
    }

    public function test_customer_saved_language_is_used_when_signed_in(): void
    {
        $u = new User(['name' => 'Asha', 'email' => 'a@example.com', 'locale' => 'hi']);
        $u->save();
        $this->actingAs($u)->get('/services')->assertSee('सही मार्गदर्शन खोजें');
    }
}
