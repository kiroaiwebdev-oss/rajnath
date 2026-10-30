<?php

namespace Tests\Feature\Phase13;

use App\Domain\Identity\CustomerAuth;
use App\Domain\Legal\LegalDocuments;
use App\Domain\Privacy\PrivacyService;
use App\Domain\Reviews\Reviews;
use App\Models\Offering;
use App\Models\User;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\LegalSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Versioned legal pages edited in the admin, and moderated customer reviews (web, app, admin). */
class LegalAndReviewsTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->seed(LegalSeeder::class);
        $this->syncRbac();
    }

    private function customer(string $email = 'asha@example.com', string $name = 'Asha Devi'): User
    {
        $u = new User(['name' => $name, 'email' => $email]);
        $u->save();

        return $u;
    }

    private function order(User $u, string $status = 'paid', string $slug = 'janma-kundali-reading'): object
    {
        $o = Offering::where('slug', $slug)->first();
        $ref = (string) Str::ulid();
        DB::table('orders')->insert(['ref' => $ref, 'user_id' => $u->id, 'kind' => 'service', 'status' => $status, 'subtotal_minor' => 210000, 'payable_minor' => 210000,
            'snapshot' => json_encode(['offering' => ['ref' => $o->ref, 'slug' => $o->slug, 'title' => $o->t('en')->title, 'family' => 'K'], 'variant' => ['name' => 'Video call'], 'price_minor' => 210000]),
            'idempotency_key' => (string) Str::uuid(), 'paid_at' => $status === 'paid' ? now() : null, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('orders')->where('ref', $ref)->first();
    }

    public function test_legal_pages_render_from_the_admin_with_placeholders_and_history(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('What we collect')->assertSee('Version 2026-10-30')->assertSee('Download a copy of your data')
            ->assertSee(config('brand')['contact.email'] ?? 'acharyarajnathjha@gmail.com')->assertDontSee('{email}');
        $this->get('/terms')->assertOk()->assertSee('Nature of the services')->assertSee(url('/refund-policy'));
        // drafts are not public
        $this->get('/refund-policy')->assertNotFound();
        $this->get('/shipping-policy')->assertNotFound();
        $this->assertSame('2026-10-30', LegalDocuments::consentVersion());
    }

    public function test_publishing_needs_permission_and_step_up_and_keeps_old_versions(): void
    {
        $this->asStaff($this->makeStaff(['content_publisher']))->get(route('admin.legal.index'))->assertForbidden();
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->get(route('admin.legal.index'))->assertOk()->assertSee('Cancellation and refund policy')->assertSee('Live · 2026-10-30');
        $this->asStaff($admin)->get(route('admin.legal.edit', ['refunds', 'en']))->assertOk()->assertSee('24 hours')->assertSee('Preview');

        // a draft of the privacy policy: raw HTML and javascript links never reach the page
        $this->asStaff($admin)->get(route('admin.legal.edit', ['privacy', 'en']))->assertOk();
        $body = "## Who we are\n\nWe are {brand}. <script>alert(1)</script> [bad](javascript:alert(1))\n\n## New section\n\nGemstones can be returned within 7 days.";
        $this->asStaff($admin)->put(route('admin.legal.save', ['privacy', 'en']), ['title' => 'Privacy policy', 'body' => $body, 'change_note' => 'Added returns'])->assertRedirect();
        $this->get('/privacy')->assertDontSee('Gemstones can be returned'); // still the published text

        // without a fresh step-up the publish is refused
        $this->asStaff($admin, false)->post(route('admin.legal.publish', ['privacy', 'en']), ['confirm' => '1'])->assertRedirect();
        $this->assertSame(1, DB::table('legal_documents')->where('kind', 'privacy')->where('status', 'draft')->count());

        $this->asStaff($admin)->post(route('admin.legal.publish', ['privacy', 'en']), [])->assertSessionHasErrors('confirm');
        $this->asStaff($admin)->post(route('admin.legal.publish', ['privacy', 'en']), ['confirm' => '1'])->assertSessionHasNoErrors();
        $new = DB::table('legal_documents')->where('kind', 'privacy')->where('status', 'published')->first();
        $this->assertStringStartsWith(now('Asia/Kolkata')->toDateString(), $new->version);
        $html = $this->get('/privacy')->assertOk()->assertSee('Gemstones can be returned within 7 days.')->assertSee('Earlier versions')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('javascript:alert', $html);
        $this->assertStringContainsString('We are Astro Rajnath Jha.', $html);
        $this->get('/privacy?version=2026-10-30')->assertOk()->assertSee('What we collect')->assertSee('This is an earlier version.')->assertSee('noindex', false);
        $this->get(route('legal.history', 'privacy'))->assertOk()->assertSee('Added returns')->assertSee('2026-10-30');
        $this->assertSame($new->version, LegalDocuments::consentVersion());
        $this->assertDatabaseHas('audit_logs', ['action' => 'legal.published']);

        // publishing the same text again is refused; the privacy policy cannot be taken down
        $this->asStaff($admin)->get(route('admin.legal.edit', ['privacy', 'en']));
        $this->asStaff($admin)->post(route('admin.legal.publish', ['privacy', 'en']), ['confirm' => '1'])->assertSessionHasErrors('body');
        $this->asStaff($admin)->post(route('admin.legal.unpublish', ['privacy', 'en']))->assertSessionHasErrors('unpublish');

        // the refund policy goes live, appears in the footer and sitemap, and can be taken down
        $this->asStaff($admin)->post(route('admin.legal.publish', ['refunds', 'en']), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->get('/refund-policy')->assertOk()->assertSee('24 hours');
        $this->get('/')->assertSee(route('legal.refunds'));
        $this->get('/sitemap.xml')->assertSee(url('/refund-policy'));
        $this->asStaff($admin)->post(route('admin.legal.unpublish', ['refunds', 'en']))->assertRedirect();
        $this->get('/refund-policy')->assertNotFound();

        // a Hindi version is optional; without it Hindi visitors see English
        $this->get('/terms?lang=hi')->assertOk()->assertSee('Nature of the services');
        $this->asStaff($admin)->put(route('admin.legal.save', ['terms', 'hi']), ['title' => 'उपयोग की शर्तें', 'body' => "## सेवाओं की प्रकृति\n\nज्योतिष पारंपरिक है।"]);
        $this->asStaff($admin)->post(route('admin.legal.publish', ['terms', 'hi']), ['confirm' => '1'])->assertSessionHasNoErrors();
        $this->get('/terms?lang=hi')->assertSee('सेवाओं की प्रकृति');
        $this->get('/terms?lang=en')->assertSee('Nature of the services');
    }

    public function test_sign_up_records_the_published_version(): void
    {
        $this->assertSame('2026-10-30', LegalDocuments::consentVersion());
        DB::table('legal_documents')->delete();
        LegalDocuments::forget();
        $this->assertSame(CustomerAuth::TERMS_VERSION, LegalDocuments::consentVersion());
    }

    public function test_customer_reviews_a_paid_order_and_staff_moderate(): void
    {
        $u = $this->customer();
        $paid = $this->order($u);
        $unpaid = $this->order($u, 'pending_payment');

        $this->actingAs($u)->get(route('account.reviews.index'))->assertOk()->assertSee('Waiting for your review')->assertSee('Janma Kundali Reading');
        $this->actingAs($u)->get(route('account.reviews.create', $paid->ref))->assertOk()->assertSee('Asha D.');
        $this->actingAs($u)->post(route('account.reviews.store', $unpaid->ref), ['rating' => 5, 'body' => str_repeat('Very good. ', 3), 'display_name' => 'Asha D.', 'consent' => '1'])
            ->assertSessionHasErrors('order');
        $this->actingAs($u)->post(route('account.reviews.store', $paid->ref), ['rating' => 5, 'body' => 'Short', 'display_name' => 'Asha D.'])->assertSessionHasErrors(['body', 'consent']);
        $this->actingAs($u)->post(route('account.reviews.store', $paid->ref), ['rating' => 5, 'title' => 'Clear guidance', 'body' => 'Acharya Ji explained my chart patiently and the remedies were simple.',
            'display_name' => 'Asha D.', 'city' => 'Patna', 'consent' => '1'])->assertRedirect(route('account.reviews.index'));
        $r = DB::table('reviews')->first();
        $this->assertSame('pending', $r->status);
        $this->assertTrue((bool) $r->verified);
        $this->assertSame(Offering::where('slug', 'janma-kundali-reading')->value('id'), (int) $r->offering_id);
        // one review per order; nothing public yet
        $this->actingAs($u)->post(route('account.reviews.store', $paid->ref), ['rating' => 4, 'body' => str_repeat('Again and again ', 3), 'display_name' => 'A', 'consent' => '1'])->assertSessionHasErrors('order');
        $this->get('/reviews')->assertOk()->assertDontSee('explained my chart');

        // moderation needs content.review; rejecting needs a reason the customer sees
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.reviews.index'))->assertForbidden();
        $reviewer = $this->makeStaff(['content_reviewer']);
        $this->asStaff($reviewer)->get(route('admin.reviews.index'))->assertOk()->assertSee('explained my chart')->assertSee('Verified purchase');
        $this->asStaff($reviewer)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'reject'])->assertSessionHasErrors('reason');
        $this->asStaff($reviewer)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'reject', 'reason' => 'Please remove the phone number.'])->assertSessionHasNoErrors();
        $this->actingAs($u)->get(route('account.reviews.index'))->assertSee('Please remove the phone number.');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'kind' => 'review', 'title' => 'Your review was not published']);
        $this->actingAs($u)->put(route('account.reviews.update', $r->ref), ['rating' => 5, 'title' => 'Clear guidance', 'body' => 'Acharya Ji explained my chart patiently and the remedies were simple.',
            'display_name' => 'Asha D.', 'city' => 'Patna'])->assertRedirect();
        $this->assertSame('pending', DB::table('reviews')->value('status'));

        $this->asStaff($reviewer)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->asStaff($reviewer)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'feature'])->assertForbidden();
        $this->asStaff($reviewer)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'reply', 'reply' => 'Thank you, Asha ji.'])->assertSessionHasNoErrors();
        $this->asStaff($this->makeStaff(['content_publisher']))->post(route('admin.reviews.moderate', $r->ref), ['action' => 'feature'])->assertSessionHasNoErrors();

        $this->get('/reviews')->assertOk()->assertSee('explained my chart')->assertSee('Asha D., Patna')->assertSee('Thank you, Asha ji.')->assertSee('5.0');
        $this->get('/')->assertSee('What clients say')->assertSee('Clear guidance');
        $this->get('/services/janma-kundali-reading')->assertSee('What clients say')->assertSee('explained my chart');
        $this->get('/reviews?service=janma-kundali-reading')->assertOk()->assertSee('Reviews of Janma Kundali Reading');
        $this->get('/reviews?rating=1')->assertOk()->assertDontSee('explained my chart');
        $this->get('/sitemap.xml')->assertSee(url('/reviews'));

        // approved reviews can no longer be edited by the customer, but can be deleted
        $this->actingAs($u)->put(route('account.reviews.update', $r->ref), ['rating' => 1, 'body' => str_repeat('Changed my mind ', 3), 'display_name' => 'A'])->assertSessionHasErrors('body');
        $other = $this->customer('o@example.com', 'Other');
        $this->actingAs($other)->delete(route('account.reviews.destroy', $r->ref))->assertNotFound();
        $this->actingAs($u)->delete(route('account.reviews.destroy', $r->ref))->assertRedirect();
        $this->get('/reviews')->assertDontSee('explained my chart');
    }

    public function test_staff_add_offline_review_with_permission_note(): void
    {
        $pub = $this->makeStaff(['content_publisher']);
        $this->asStaff($pub)->post(route('admin.reviews.store'), ['rating' => 5, 'body' => 'Our family has consulted Acharya Ji for ten years.', 'display_name' => 'Ravi K.'])
            ->assertSessionHasErrors('permission_note');
        $this->asStaff($pub)->post(route('admin.reviews.store'), ['rating' => 5, 'body' => 'Our family has consulted Acharya Ji for ten years.', 'display_name' => 'Ravi K.',
            'permission_note' => 'WhatsApp, 2 Nov 2026'])->assertRedirect();
        $r = DB::table('reviews')->first();
        $this->assertSame('staff', $r->source);
        $this->assertFalse((bool) $r->verified);
        $this->asStaff($pub)->post(route('admin.reviews.moderate', $r->ref), ['action' => 'approve']);
        $this->get('/reviews')->assertSee('Ravi K.')->assertDontSee('Verified purchase');
        $this->asStaff($pub)->get(route('admin.dashboard'))->assertOk()->assertSee('Reviews to check');
    }

    public function test_reviews_api_for_the_app(): void
    {
        $u = $this->customer();
        $paid = $this->order($u);
        $t = $u->createToken('Phone', ['customer'])->plainTextToken;
        $this->withToken($t)->getJson('/api/v1/me/reviews')->assertOk()->assertJsonPath('meta.suggested_name', 'Asha D.')->assertJsonPath('meta.reviewable_orders.0.ref', $paid->ref);
        $this->withToken($t)->postJson('/api/v1/reviews', ['order_ref' => $paid->ref, 'rating' => 4, 'body' => 'Helpful consultation, clear and practical advice.', 'display_name' => 'Asha D.'])
            ->assertUnprocessable()->assertJsonValidationErrors('consent');
        $ref = $this->withToken($t)->postJson('/api/v1/reviews', ['order_ref' => $paid->ref, 'rating' => 4, 'body' => 'Helpful consultation, clear and practical advice.', 'display_name' => 'Asha D.', 'consent' => true])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.editable', true)->json('data.ref');
        $this->withToken($t)->getJson('/api/v1/orders/'.$paid->ref)->assertOk()->assertJsonPath('data.review.status', 'pending');
        $this->withToken($t)->getJson('/api/v1/site')->assertOk()->assertJsonCount(2, 'data.legal')->assertJsonPath('data.legal.0.kind', 'privacy');
        $this->withToken($t)->putJson('/api/v1/me/reviews/'.$ref, ['rating' => 5, 'body' => 'Helpful consultation, clear and practical advice!', 'display_name' => 'Asha'])->assertOk()->assertJsonPath('data.rating', 5);
        $this->getJson('/api/v1/reviews')->assertOk()->assertJsonCount(0, 'data');
        DB::table('reviews')->update(['status' => 'approved', 'published_at' => now()]);
        Reviews::forget();
        $this->getJson('/api/v1/reviews?service=janma-kundali-reading')->assertOk()->assertJsonPath('data.0.name', 'Asha')->assertJsonPath('data.0.verified', true)
            ->assertJsonPath('meta.summary.average', 5)->assertJsonMissingPath('data.0.reject_reason');
        $this->getJson('/api/v1/reviews?rating=9')->assertUnprocessable();
        $this->withToken($t)->deleteJson('/api/v1/me/reviews/'.$ref)->assertOk();
        $this->assertSame(0, DB::table('reviews')->count());
    }

    public function test_account_deletion_removes_reviews_and_export_includes_them(): void
    {
        $u = $this->customer();
        $o = $this->order($u);
        DB::table('orders')->where('id', $o->id)->update(['status' => 'refunded']); // nothing open, so deletion is not blocked
        DB::table('reviews')->insert(['ref' => (string) Str::ulid(), 'user_id' => $u->id, 'rating' => 5, 'body' => 'Wonderful guidance from Acharya Ji.', 'display_name' => 'Asha D.',
            'status' => 'approved', 'source' => 'customer', 'created_at' => now(), 'updated_at' => now()]);
        $p = app(PrivacyService::class);
        $this->assertCount(1, $p->export($u)['reviews']);
        $p->delete($u);
        $this->assertSame(0, DB::table('reviews')->count());
    }
}
