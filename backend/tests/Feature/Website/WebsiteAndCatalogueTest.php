<?php

namespace Tests\Feature\Website;

use App\Models\Enquiry;
use App\Models\Offering;
use App\Support\Money;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class WebsiteAndCatalogueTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
    }

    public function test_public_pages_render(): void
    {
        foreach (['/', '/services', '/services/janma-kundali-reading', '/about', '/contact', '/faq', '/privacy', '/terms', '/offline', '/account/sign-in'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Janma Kundali Reading')->assertSee('Global Astrological Iconic Award');
        $this->get('/about')->assertSee('Mahavir Mandir')->assertSee('Professor Award 2026');
        $this->get('/faq')->assertSee('Which languages are consultations available in?');
        $this->getJson('/manifest.webmanifest')->assertOk()->assertJsonPath('display', 'standalone');
        $this->assertFileExists(public_path('icons/icon-512.png'));
        $this->assertFileExists(public_path('sw.js'));
    }

    public function test_drafts_are_hidden_and_search_filters(): void
    {
        $this->get('/services/premium-personalized-kundli')->assertNotFound();
        $this->get('/services/mahamrityunjay-puja')->assertNotFound();
        $this->get('/services?q=vastu')->assertSee('Vastu Consultation')->assertDontSee('Numerology</a>', false);
        $this->get('/services?family=remedies')->assertSee('Gemstone Guidance')->assertDontSee('Janma Kundali Reading');
        $this->get('/services?mode=onsite')->assertSee('Vastu Consultation')->assertSee('1 service found');
        $this->get('/services?q=zzzz')->assertSee('No services match');
    }

    public function test_enquiry_submission_and_admin_follow_up(): void
    {
        $this->post('/enquiries', ['offering' => 'vastu-consultation', 'name' => 'Ramesh', 'phone' => '91234 56789', 'language' => 'Hindi', 'message' => 'Flat in Patna', 'consent' => '1'])
            ->assertRedirect();
        $e = Enquiry::firstOrFail();
        $this->assertSame('+919123456789', $e->phone_e164);
        $this->get("/enquiries/{$e->ref}/received")->assertOk()->assertSee('Thank you, Ramesh')->assertDontSee('9123456789');

        $support = $this->makeStaff(['support']);
        $this->asStaff($support)->get('/admin/enquiries')->assertOk()->assertSee('Ramesh');
        $this->get("/admin/enquiries/{$e->ref}")->assertOk()->assertSee('Flat in Patna');
        $this->assertDatabaseHas('audit_logs', ['action' => 'enquiry.viewed']);
        $this->put("/admin/enquiries/{$e->ref}", ['status' => 'contacted'])->assertForbidden(); // support has bookings.view only

        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->put("/admin/enquiries/{$e->ref}", ['status' => 'contacted', 'staff_note' => 'Called'])->assertRedirect();
        $this->assertSame('contacted', $e->fresh()->status);
        $csv = $this->get('/admin/enquiries/export?status=all')->assertOk()->streamedContent();
        $this->assertStringContainsString('Ramesh', $csv);
    }

    public function test_enquiry_validation_honeypot_and_csv_injection(): void
    {
        $this->post('/enquiries', ['name' => 'X', 'phone' => '123', 'consent' => '1'])->assertSessionHasErrors('phone');
        $this->post('/enquiries', ['name' => 'X', 'phone' => '9835258203'])->assertSessionHasErrors('consent');
        $this->post('/enquiries', ['name' => 'Bot', 'phone' => '9835258203', 'consent' => '1', 'website' => 'http://spam'])->assertRedirect();
        $this->assertSame(0, Enquiry::count());
        $this->assertSame("'=HYPERLINK(\"x\")", \App\Http\Controllers\Admin\EnquiryController::csvSafe('=HYPERLINK("x")'));
    }

    public function test_enquiry_rate_limit(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/enquiries', ['name' => 'N'.$i, 'phone' => '9835258203', 'consent' => '1']);
        }
        $this->post('/enquiries', ['name' => 'N4', 'phone' => '9835258203', 'consent' => '1'])->assertStatus(429);
    }

    public function test_admin_price_change_requires_permission_and_step_up(): void
    {
        $o = Offering::where('slug', 'janma-kundali-reading')->first();
        $v = $o->variants()->first();
        $payload = ['variant' => $v->ref, 'name' => $v->name, 'mode' => 'video', 'duration_min' => 30, 'participants' => 1, 'price' => '2,100', 'active' => '1'];

        $this->asStaff($this->makeStaff(['administrator']))->post("/admin/catalogue/{$o->ref}/variants", $payload)->assertForbidden(); // no prices.manage
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner, freshStepUp: false)->post("/admin/catalogue/{$o->ref}/variants", $payload)->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->post("/admin/catalogue/{$o->ref}/variants", $payload)->assertSessionHasNoErrors();
        $this->assertSame(210000, $v->fresh()->price_minor);
        $this->get('/services/janma-kundali-reading')->assertSee('₹2,100');
        $this->asStaff($owner)->post("/admin/catalogue/{$o->ref}/variants", ['price' => '12.345'] + $payload)->assertSessionHasErrors('price');
    }

    public function test_puja_requiring_supplier_cannot_be_activated_and_activation_rules(): void
    {
        $owner = $this->makeStaff(['owner']);
        $chadhava = Offering::whereHas('family', fn ($q) => $q->where('code', 'chadhava'))->first();
        $this->asStaff($owner)->put("/admin/catalogue/{$chadhava->ref}/status", ['status' => 'active'])->assertSessionHasErrors('status');
        $this->assertSame('draft', $chadhava->fresh()->status);

        $report = Offering::where('slug', 'career-report')->first();
        $this->put("/admin/catalogue/{$report->ref}/status", ['status' => 'active'])->assertSessionHasNoErrors();
        $this->get('/services/career-report')->assertOk();
        $this->put("/admin/catalogue/{$report->ref}/status", ['status' => 'paused']);
        $this->get('/services/career-report')->assertNotFound();
    }

    public function test_admin_can_edit_service_text_and_slug_uniqueness(): void
    {
        $owner = $this->makeStaff(['owner']);
        $o = Offering::where('slug', 'numerology')->first();
        $this->asStaff($owner)->put("/admin/catalogue/{$o->ref}", ['family_id' => $o->family_id, 'slug' => 'numerology', 'title' => 'Numerology (Ank Jyotish)', 'summary' => 'New summary', 'includes' => "A\nB"])
            ->assertSessionHasNoErrors();
        $this->get('/services/numerology')->assertSee('Numerology (Ank Jyotish)')->assertSee('New summary');
        $this->put("/admin/catalogue/{$o->ref}", ['family_id' => $o->family_id, 'slug' => 'palmistry', 'title' => 'x', 'summary' => 'y'])->assertSessionHasErrors('slug');
        $this->put("/admin/catalogue/{$o->ref}", ['family_id' => $o->family_id, 'title' => 'x', 'summary' => 'y', 'image_path' => '../../.env'])->assertSessionHasErrors('image_path');
    }

    public function test_admin_customer_suspension_and_faq_management(): void
    {
        $c = new \App\Models\User(['name' => 'Cust', 'email' => 'cust@example.com']);
        $c->save();
        $c->createToken('app', ['customer']);
        $support = $this->makeStaff(['support']);
        $this->asStaff($support)->get('/admin/customers')->assertOk()->assertSee('Cust')->assertDontSee('cust@example.com');
        $this->put("/admin/customers/{$c->ref}/status", ['status' => 'suspended', 'reason' => 'x'])->assertForbidden();

        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->put("/admin/customers/{$c->ref}/status", ['status' => 'suspended', 'reason' => 'Abuse'])->assertSessionHasNoErrors();
        $this->assertSame('suspended', $c->fresh()->status);
        $this->assertSame(0, $c->tokens()->count());

        $this->post('/admin/faqs', ['question' => 'New Q?', 'answer' => 'New A', 'published' => '1'])->assertSessionHasNoErrors();
        $this->get('/faq')->assertSee('New Q?');
        $this->asStaff($this->makeStaff(['content_author']))->post('/admin/faqs', ['question' => 'Q2', 'answer' => 'A'])->assertForbidden();
    }

    public function test_customer_devices_page_and_sign_out_others(): void
    {
        $c = new \App\Models\User(['name' => 'D', 'email' => 'd@example.com']);
        $c->save();
        $c->createToken('Phone', ['customer']);
        DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $c->id, 'payload' => '', 'last_activity' => time(), 'user_agent' => 'Firefox']);
        $this->actingAs($c)->get('/account/devices')->assertOk()->assertSee('Firefox')->assertSee('Phone');
        $this->delete('/account/devices/sessions/other-session')->assertRedirect();
        $this->assertSame(0, DB::table('sessions')->where('id', 'other-session')->count());
        $this->post('/account/devices/sign-out-others')->assertRedirect();
        $this->assertSame(0, $c->tokens()->count());
    }

    public function test_contact_change_requires_otp_to_new_address(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $c = new \App\Models\User(['name' => 'P', 'phone_e164' => '+919835258203']);
        $c->save();
        $taken = new \App\Models\User(['name' => 'T', 'email' => 'taken@example.com']);
        $taken->save();
        $this->actingAs($c)->post('/account/contact', ['contact' => 'taken@example.com'])->assertSessionHasErrors('contact');
        $this->post('/account/contact', ['contact' => 'new@example.com'])->assertSessionHas('contact_pending');
        $this->assertNull($c->fresh()->email);
        $code = null;
        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\OtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $this->post('/account/contact/confirm', ['contact_code' => $code])->assertSessionHas('status');
        $this->assertSame('new@example.com', $c->fresh()->email);
        $this->assertNotNull($c->fresh()->email_verified_at);
    }

    public function test_money_formatting_and_parsing(): void
    {
        $this->assertSame('₹1,00,000', Money::format(10000000));
        $this->assertSame('₹1,499.50', Money::format(149950));
        $this->assertSame('Price on request', Money::format(null));
        $this->assertSame(149950, Money::parseRupees('1,499.50'));
        $this->assertSame(210000, Money::parseRupees('₹2100'));
        $this->assertNull(Money::parseRupees(''));
        $this->expectException(\InvalidArgumentException::class);
        Money::parseRupees('-5');
    }

    public function test_guest_redirects_go_to_the_right_sign_in(): void
    {
        $this->get('/account')->assertRedirect('/account/sign-in');
        $this->get('/admin')->assertRedirect('/staff/login');
    }
}
