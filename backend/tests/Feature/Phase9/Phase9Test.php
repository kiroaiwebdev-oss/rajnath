<?php

namespace Tests\Feature\Phase9;

use App\Domain\Assistant\Assistant;
use App\Domain\Booking\Slots;
use App\Domain\Ledger\Ledger;
use App\Domain\Notify\Fcm;
use App\Domain\Notify\Notifier;
use App\Domain\Payments\Checkout;
use App\Domain\Payments\Refunds;
use App\Domain\Practitioners\Payouts;
use App\Domain\Practitioners\Practitioners;
use App\Models\Offering;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Carbon\Carbon;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Phase 9: email + push notifications, AI assistant, practitioners + payouts, app API parity. */
class Phase9Test extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private const RZP_SECRET = 'sec_test_9';
    private int $seq = 0;
    private array $fcmSent = [];
    private array $aiCalls = [];
    /** provider host fragment => [status, body] */
    private array $aiReplies = [];
    private array $captures = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 03:00:00'); // Monday 08:30 IST
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('E', 14));
        $s->set('payments.razorpay.key_secret', self::RZP_SECRET, secret: true);
        $s->set('payments.razorpay.webhook_secret', 'wh', secret: true);
        Storage::fake('local');
        Storage::fake('uploads');
        Mail::fake();
        Http::fake([
            'api.razorpay.com/v1/orders/*/payments' => function ($req) {
                preg_match('#orders/([^/]+)/payments#', $req->url(), $m);

                return Http::response(['items' => isset($this->captures[$m[1]]) ? [$this->captures[$m[1]]] : []]);
            },
            'api.razorpay.com/v1/orders' => fn ($r) => Http::response(['id' => 'order_N'.(++$this->seq), 'amount' => $r['amount']]),
            'oauth2.googleapis.com/token' => fn () => Http::response(['access_token' => 'ya29.test', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => function ($req) {
                $this->fcmSent[] = $req->data()['message'];

                return str_contains($req->data()['message']['token'], 'dead')
                    ? Http::response(['error' => ['status' => 'NOT_FOUND', 'details' => [['errorCode' => 'UNREGISTERED']]]], 404)
                    : Http::response(['name' => 'projects/p/messages/1']);
            },
            '*/chat/completions' => function ($req) {
                $this->aiCalls[] = ['url' => $req->url(), 'body' => $req->data()];
                foreach ($this->aiReplies as $host => [$status, $body]) {
                    if (str_contains($req->url(), $host)) {
                        return Http::response($body, $status);
                    }
                }

                return Http::response(['error' => ['message' => 'not set']], 500);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(string $name = 'Seeker'): User
    {
        $u = new User(['name' => $name, 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function token(User $u): string
    {
        return $u->createToken('test', ['customer'])->plainTextToken;
    }

    /** Bearer request as a given token; resets the cached guard user so switching accounts works. */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        $this->app['auth']->forgetGuards();

        return parent::withToken($token, $type);
    }

    private function serviceAccount(): string
    {
        $k = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($k, $pem);

        return json_encode(['type' => 'service_account', 'project_id' => 'arj-test', 'client_email' => 'push@arj-test.iam.gserviceaccount.com', 'private_key' => $pem]);
    }

    private function configurePush(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('push.fcm.service_account', $this->serviceAccount(), secret: true);
        $s->set('push.android.api_key', 'AIza'.str_repeat('x', 35));
        $s->set('push.android.app_id', '1:123456789:android:abc123');
        $s->set('push.android.sender_id', '123456789');
    }

    private function capture(object $order): void
    {
        $p = DB::table('payments')->where('order_id', $order->id)->first();
        app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$order->id, (int) $p->amount_minor, 'upi');
    }

    // ================================================================ notifications

    public function test_service_account_validation(): void
    {
        $this->assertNull(Fcm::validateServiceAccount($this->serviceAccount()));
        $this->assertNotNull(Fcm::validateServiceAccount('{"type":"authorized_user"}'));
        $this->assertNotNull(Fcm::validateServiceAccount(json_encode(['type' => 'service_account', 'project_id' => 'arj-test', 'client_email' => 'x@y', 'private_key' => 'not a key'])));
    }

    public function test_notice_goes_to_inbox_email_and_push_and_dead_tokens_are_removed(): void
    {
        $this->configurePush();
        $u = $this->customer();
        DB::table('push_tokens')->insert([
            ['user_id' => $u->id, 'token' => str_repeat('a', 40), 'token_hash' => hash('sha256', str_repeat('a', 40)), 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $u->id, 'token' => 'dead'.str_repeat('b', 40), 'token_hash' => hash('sha256', 'dead'.str_repeat('b', 40)), 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $id = app(Notifier::class)->send($u, 'report', 'Your report is ready', 'Read it now.', 'REF123', 'https://example.test/r');
        $n = DB::table('user_notifications')->find($id);
        $this->assertSame(['email' => 'sent', 'push' => 'sent:1'], json_decode($n->channels, true));
        $this->assertCount(2, $this->fcmSent);
        $this->assertSame(['kind' => 'report', 'ref' => 'REF123', 'link' => 'https://example.test/r', 'notification_id' => (string) $id], $this->fcmSent[0]['data']);
        $this->assertSame(1, DB::table('push_tokens')->count(), 'unregistered token deleted');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'oauth2.googleapis.com/token') && str_starts_with($r['assertion'], 'eyJ'));
    }

    public function test_preferences_mute_optional_notices_but_not_service_ones(): void
    {
        $u = $this->customer();
        $u->forceFill(['notification_prefs' => json_encode(['booking_reminders' => true, 'reports_ready' => false, 'payments' => true, 'offers' => false])])->save();
        $muted = app(Notifier::class)->send($u, 'report', 'Report', 'Ready');
        $kept = app(Notifier::class)->send($u, 'ticket', 'Reply', 'We replied');
        $this->assertSame(['email' => 'muted', 'push' => 'muted'], json_decode(DB::table('user_notifications')->find($muted)->channels, true));
        $this->assertSame('sent', json_decode(DB::table('user_notifications')->find($kept)->channels, true)['email']);
        $this->assertSame('not_configured', json_decode(DB::table('user_notifications')->find($kept)->channels, true)['push']);
    }

    public function test_push_token_api_meta_and_inbox(): void
    {
        $this->getJson('/api/v1/meta')->assertOk()->assertJsonPath('push', null);
        $this->configurePush();
        $this->getJson('/api/v1/meta')->assertJsonPath('push.firebase.project_id', 'arj-test')->assertJsonPath('push.firebase.app_id', '1:123456789:android:abc123')
            ->assertJsonMissingPath('push.firebase.private_key');

        $a = $this->customer();
        $b = $this->customer();
        $tok = str_repeat('t', 60);
        $this->postJson('/api/v1/push-tokens', ['token' => $tok])->assertUnauthorized();
        $this->withToken($this->token($a))->postJson('/api/v1/push-tokens', ['token' => $tok, 'platform' => 'android'])->assertNoContent();
        $this->withToken($this->token($b))->postJson('/api/v1/push-tokens', ['token' => $tok])->assertNoContent();
        $this->assertSame($b->id, DB::table('push_tokens')->value('user_id'), 'token moves to the account now signed in on the device');

        $id = app(Notifier::class)->send($b, 'general', 'Hello', 'World', null, null);
        app(Notifier::class)->send($a, 'general', 'Not yours', 'x');
        $tb = $this->token($b);
        $this->withToken($tb)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('unread', 1)->assertJsonPath('data.0.title', 'Hello');
        $this->withToken($tb)->postJson("/api/v1/notifications/$id/read")->assertNoContent();
        $this->withToken($this->token($a))->postJson("/api/v1/notifications/$id/read")->assertNotFound();
        $this->withToken($tb)->getJson('/api/v1/notifications')->assertJsonPath('unread', 0);
        $this->withToken($tb)->deleteJson('/api/v1/push-tokens', ['token' => $tok])->assertNoContent();
        $this->assertSame(0, DB::table('push_tokens')->count());

        // Web inbox
        $this->actingAs($b)->get(route('account.notifications'))->assertOk()->assertSee('Hello')->assertDontSee('Not yours');
    }

    public function test_admin_push_settings_need_step_up_and_valid_input(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->get(route('admin.push.edit'))->assertOk()->assertSee('com.astrorajnathjha.app');
        $this->asStaff($owner, freshStepUp: false)->put(route('admin.push.save'), [])->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->put(route('admin.push.save'), ['service_account' => '{}', 'api_key' => 'nope', 'app_id' => 'x', 'sender_id' => 'y'])->assertSessionHasErrors(['api_key', 'app_id', 'sender_id']);
        $this->asStaff($owner)->put(route('admin.push.save'), ['service_account' => $this->serviceAccount(), 'api_key' => 'AIza'.str_repeat('Q', 35), 'app_id' => '1:99999999:android:ff00aa11bb22', 'sender_id' => '99999999'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(app(Fcm::class)->clientConfig());
        $raw = DB::table('settings')->where('key', 'push.fcm.service_account')->value('value');
        $this->assertStringNotContainsString('PRIVATE KEY', (string) $raw, 'service account encrypted at rest');
        $this->asStaff($owner)->post(route('admin.push.test'))->assertSessionHasNoErrors()->assertSessionHas('status', 'Credentials accepted by Google.');
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.push.edit'))->assertForbidden();
    }

    public function test_events_create_notices(): void
    {
        $u = $this->customer();
        DB::table('availability_rules')->insert(['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->where('mode', 'video')->first();
        $v->update(['price_minor' => 210000, 'active' => true]);
        [, $r] = app(Slots::class)->hold($u, $v->fresh(), '2026-11-03T04:30Z', null, null, 'n1');
        $this->capture($r['order']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'kind' => 'booking', 'title' => 'Consultation confirmed']);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $u->id, 'title' => 'Payment received']); // booking sends its own confirmation

        Carbon::setTestNow('2026-11-02 05:00:00'); // 23.5 h before
        $this->assertSame(1, app(Slots::class)->sendReminders());
        $this->assertSame(0, app(Slots::class)->sendReminders(), 'reminder sent once');
        Carbon::setTestNow('2026-11-03 03:45:00'); // 45 min before
        $this->assertSame(1, app(Slots::class)->sendReminders());
        $this->assertSame(2, DB::table('user_notifications')->where('title', 'Consultation reminder')->count());
    }

    // ================================================================ assistant

    private function enableAi(array $providers = ['groq', 'gemini']): void
    {
        $s = app(SettingsRepository::class);
        $s->set('ai.enabled', true);
        $s->set('ai.order', implode(',', $providers));
        foreach ($providers as $p) {
            $s->set("ai.$p.enabled", true);
            $s->set("ai.$p.key", "key-$p", secret: true);
        }
    }

    private function ok(string $text): array
    {
        return [200, ['choices' => [['message' => ['role' => 'assistant', 'content' => $text]]], 'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 40]]];
    }

    public function test_assistant_falls_back_and_grounds_on_catalogue(): void
    {
        $this->enableAi();
        $this->aiReplies = ['api.groq.com' => [503, ['error' => ['message' => 'overloaded']]], 'generativelanguage.googleapis.com' => $this->ok('Online consultation ke liye Services par jaakar time chuniye.')];
        $u = $this->customer();
        $res = $this->withToken($this->token($u))->postJson('/api/v1/assistant/chat', ['message' => 'Online consultation kaise book karein?'])->assertOk();
        $res->assertJsonPath('handoff', false)->assertJsonPath('reply', 'Online consultation ke liye Services par jaakar time chuniye.');
        $this->assertCount(2, $this->aiCalls);
        $this->assertStringContainsString('api.groq.com/openai/v1/chat/completions', $this->aiCalls[0]['url']);
        $this->assertSame('openai/gpt-oss-120b', $this->aiCalls[0]['body']['model']);
        $this->assertSame('gemini-3.8-flash', $this->aiCalls[1]['body']['model']);
        $system = $this->aiCalls[1]['body']['messages'][0]['content'];
        $this->assertStringContainsString('Janma Kundali', $system);
        $this->assertStringNotContainsString('Rudrabhishek', $system, 'draft services are not offered by the assistant');
        $this->assertDatabaseHas('assistant_messages', ['role' => 'assistant', 'provider' => 'gemini', 'tokens_in' => 900]);

        // Conversation continues with history; another user cannot continue it
        $conv = $res->json('conversation');
        $this->aiReplies['api.groq.com'] = $this->ok('Haan. [HANDOFF]');
        $this->withToken($this->token($u))->postJson('/api/v1/assistant/chat', ['message' => 'Refund chahiye', 'conversation' => $conv])->assertJsonPath('handoff', true)->assertJsonPath('conversation', $conv)
            ->assertJsonPath('reply', 'Haan.');
        $this->assertCount(4, end($this->aiCalls)['body']['messages'], 'system + 2 history + new');
        $other = $this->withToken($this->token($this->customer()))->postJson('/api/v1/assistant/chat', ['message' => 'hello there', 'conversation' => $conv]);
        $this->assertNotSame($conv, $other->json('conversation'));
    }

    public function test_assistant_crisis_reply_needs_no_ai_and_is_flagged(): void
    {
        // Works even when no provider is configured
        $res = $this->postJson('/assistant/chat', ['message' => 'I want to die, nothing works'])->assertOk();
        $this->assertStringContainsString('14416', $res->json('reply'));
        $res->assertJsonPath('handoff', true);
        $this->assertSame([], $this->aiCalls);
        $this->assertDatabaseHas('assistant_messages', ['flag' => 'crisis']);
    }

    public function test_assistant_unavailable_and_limits(): void
    {
        $this->postJson('/assistant/chat', ['message' => 'Price of kundli report?'])->assertStatus(503)->assertJsonPath('error.code', 'assistant_unavailable');
        $this->enableAi(['groq']);
        app(SettingsRepository::class)->set('ai.daily_guest', 2);
        $this->aiReplies = ['api.groq.com' => $this->ok('Answer')];
        $this->postJson('/assistant/chat', ['message' => 'one?'])->assertOk();
        $this->postJson('/assistant/chat', ['message' => 'two?'])->assertOk();
        $this->postJson('/assistant/chat', ['message' => 'three?'])->assertStatus(429)->assertJsonPath('error.code', 'daily_limit');
        $this->get('/assistant')->assertOk()->assertSee('data-endpoint', false);
        // HTML from a model is never passed through
        $this->actingAs($this->customer());
        $this->aiReplies = ['api.groq.com' => $this->ok('<script>alert(1)</script>Hi')];
        $this->postJson('/assistant/chat', ['message' => 'hello'])->assertJsonPath('reply', 'alert(1)Hi');
    }

    public function test_admin_ai_settings_and_provider_test(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put(route('admin.assistant.save'), ['enabled' => '1', 'order' => ['cloudflare', 'openai', 'groq', 'gemini'], 'daily_user' => 20, 'daily_guest' => 5,
            'cloudflare_enabled' => '1', 'cloudflare_key' => 'cf-token', 'cloudflare_account_id' => str_repeat('a', 32), 'cloudflare_model' => '',
            'openai_enabled' => '1', 'openai_key' => 'sk-test', 'openai_model' => ''])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['cloudflare'], app(\App\Domain\Assistant\AiProviders::class)->order(), 'OpenAI without a model is skipped');
        $this->assertStringNotContainsString('cf-token', (string) DB::table('settings')->where('key', 'ai.cloudflare.key')->value('value'));
        $this->aiReplies = ['api.cloudflare.com/client/v4/accounts/'.str_repeat('a', 32).'/ai/v1' => $this->ok('OK')];
        $this->asStaff($owner)->post(route('admin.assistant.test'), ['provider' => 'cloudflare'])->assertSessionHasNoErrors();
        $this->assertSame('@cf/openai/gpt-oss-120b', end($this->aiCalls)['body']['model']);
        $this->asStaff($owner)->post(route('admin.assistant.test'), ['provider' => 'openai'])->assertSessionHasErrors('provider');
        $this->asStaff($owner)->put(route('admin.assistant.save'), ['order' => ['groq'], 'daily_user' => 20, 'daily_guest' => 5, 'cloudflare_account_id' => 'bad'])->assertSessionHasErrors('cloudflare_account_id');

        $this->asStaff($owner)->post(route('admin.assistant.knowledge'), ['title' => 'Office timings', 'body' => 'Mon–Sat 10 am to 6 pm.', 'active' => '1'])->assertSessionHasNoErrors();
        $this->assertStringContainsString('Mon–Sat 10 am to 6 pm.', app(Assistant::class)->systemPrompt());
        $this->asStaff($owner)->get(route('admin.assistant.edit'))->assertOk()->assertSee('Office timings')->assertDontSee('cf-token');
        $this->asStaff($this->makeStaff(['content_author']))->get(route('admin.assistant.edit'))->assertForbidden();
    }

    // ================================================================ practitioners & payouts

    /** An approved team astrologer with an approved 60% share and Tuesday hours. */
    private function practitioner(string $name = 'Pt. Test Astrologer'): array
    {
        $user = $this->makeStaff(['practitioner'], ['name' => $name]);
        $p = app(Practitioners::class)->ensureProfile($user);
        app(Practitioners::class)->saveProfile($p, $user, ['display_name' => $name, 'bio' => str_repeat('Vedic astrologer with long practice. ', 3), 'languages' => 'Hindi, English',
            'specialties' => 'Career', 'experience_years' => 12], null, true);
        $reviewer = $this->makeStaff(['administrator']);
        app(Practitioners::class)->review(DB::table('practitioners')->find($p->id), 'approve', 'team', $reviewer);
        $finance = $this->makeStaff(['finance']);
        app(Practitioners::class)->proposeCommission(DB::table('practitioners')->find($p->id), 6000, '2026-11-01', null, $finance);
        app(Practitioners::class)->decideCommission(DB::table('commission_rules')->where('practitioner_id', $p->id)->value('id'), true, $this->makeStaff(['owner']));
        DB::table('availability_rules')->insert(['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30, 'resource_key' => $p->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return [DB::table('practitioners')->find($p->id), $user];
    }

    private function teamVariant()
    {
        $o = Offering::where('slug', 'team-astrologer-consultation')->first();
        $o->forceFill(['status' => 'active'])->save();
        $v = $o->variants()->first();
        $v->forceFill(['price_minor' => 100000, 'active' => true, 'mode' => 'video', 'duration_min' => 30])->save();

        return $v->fresh();
    }

    public function test_practitioner_profile_review_and_commission_maker_checker(): void
    {
        $user = $this->makeStaff(['practitioner']);
        $this->asStaff($user)->get(route('admin.practice.show'))->assertOk()->assertSee('My practice');
        $p = Practitioners::mine($user);
        $this->assertSame('draft', $p->status);
        $this->asStaff($user)->put(route('admin.practice.profile'), ['display_name' => 'Pt. A', 'bio' => 'short'])->assertSessionHasErrors('bio');
        $this->asStaff($user)->put(route('admin.practice.profile'), ['display_name' => 'Pt. A', 'bio' => str_repeat('Twenty years of Jyotish. ', 4), 'submit' => '1', 'photo' => UploadedFile::fake()->image('me.jpg', 400, 400)])
            ->assertSessionHasNoErrors()->assertRedirect();
        $p = DB::table('practitioners')->find($p->id);
        $this->assertSame('submitted', $p->status);
        Storage::disk('uploads')->assertExists($p->photo_path);

        // cannot approve self; non-practitioner staff cannot open "My practice"
        $this->expectsValidation(fn () => app(Practitioners::class)->review($p, 'approve', 'team', $user), 'decision');
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.practice.show'))->assertForbidden();
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->post(route('admin.practitioners.review', $p->ref), ['decision' => 'approve', 'tier' => 'premium'])->assertSessionHasNoErrors();
        $this->assertSame('approved', DB::table('practitioners')->find($p->id)->status);
        $this->assertFalse(Practitioners::bookable(DB::table('practitioners')->find($p->id)), 'no commission yet');

        // commission: admin proposes, admin cannot approve (no permission), same person cannot approve, owner can
        $this->asStaff($admin)->post(route('admin.practitioners.commission', $p->ref), ['share_percent' => 95, 'effective_from' => '2026-11-01'])->assertSessionHasErrors('share_percent');
        $this->asStaff($admin)->post(route('admin.practitioners.commission', $p->ref), ['share_percent' => 55, 'effective_from' => '2026-11-01'])->assertSessionHasNoErrors();
        $rule = DB::table('commission_rules')->first();
        $this->asStaff($admin)->post(route('admin.practitioners.commission.decide', $rule->id), ['decision' => 'approve'])->assertForbidden();
        $owner = $this->makeStaff(['owner']);
        $this->expectsValidation(fn () => app(Practitioners::class)->decideCommission($rule->id, true, User::find($rule->proposed_by)), 'decision');
        $this->asStaff($owner)->post(route('admin.practitioners.commission.decide', $rule->id), ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(5500, Practitioners::currentShareBps($p->id));
        $this->assertTrue(Practitioners::bookable(DB::table('practitioners')->find($p->id)));

        // editing an approved profile sends it back to review
        $this->asStaff($user)->put(route('admin.practice.profile'), ['display_name' => 'Pt. A renamed', 'bio' => str_repeat('Twenty years of Jyotish. ', 4)])->assertSessionHasNoErrors();
        $this->assertSame('submitted', DB::table('practitioners')->find($p->id)->status);
    }

    private function expectsValidation(callable $fn, string $field): void
    {
        try {
            $fn();
            $this->fail("expected validation error on $field");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_team_offering_publish_gate_and_practitioner_booking_isolation(): void
    {
        $o = Offering::where('slug', 'team-astrologer-consultation')->first();
        $this->assertFalse(Practitioners::canPublish($o->slug));
        [$p1] = $this->practitioner('Pt. One');
        [$p2] = $this->practitioner('Pt. Two');
        $this->assertTrue(Practitioners::canPublish($o->slug));
        $v = $this->teamVariant();

        $this->getJson('/api/v1/practitioners?offering=team-astrologer-consultation')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.display_name', 'Pt. One')
            ->assertJsonMissingPath('data.0.payout_details');
        $a = $this->customer();
        $b = $this->customer();
        $ta = $this->token($a);
        $this->withToken($ta)->getJson('/api/v1/slots?variant='.$v->ref)->assertStatus(422)->assertJsonValidationErrors('practitioner');
        $this->withToken($ta)->getJson("/api/v1/slots?variant={$v->ref}&practitioner={$p1->ref}")->assertOk()->assertJsonPath('data.2026-11-03.0.start', '2026-11-03T04:30Z');
        $res = $this->withToken($ta)->postJson('/api/v1/checkout/booking', ['variant' => $v->ref, 'start' => '2026-11-03T04:30Z', 'practitioner' => $p1->ref, 'key' => 'b1'])->assertOk();
        $res->assertJsonPath('booking.status', 'held')->assertJsonPath('razorpay.key', 'rzp_test_'.str_repeat('E', 14))->assertJsonPath('order.payable_minor', 100000);
        // Same time with another astrologer is fine; with the same astrologer it is taken
        $this->withToken($this->token($b))->postJson('/api/v1/checkout/booking', ['variant' => $v->ref, 'start' => '2026-11-03T04:30Z', 'practitioner' => $p2->ref, 'key' => 'b2'])->assertOk();
        $this->withToken($this->token($b))->postJson('/api/v1/checkout/booking', ['variant' => $v->ref, 'start' => '2026-11-03T04:30Z', 'practitioner' => $p1->ref, 'key' => 'b3'])->assertJsonValidationErrors('start');
        $this->assertSame(2, DB::table('booking_units')->where('unit_start', '2026-11-03 04:30:00')->count());
        // Acharya Ji's own hours are a separate calendar
        $this->assertSame([], app(Slots::class)->available(30, 7, 0));
    }

    public function test_earning_accrues_on_completion_and_payout_needs_two_people(): void
    {
        [$p, $pUser] = $this->practitioner();
        $v = $this->teamVariant();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $v, '2026-11-03T04:30Z', null, 'Career', 'e1', false, $p->ref);
        $this->capture($r['order']);

        // practitioner sees only their own bookings, and cannot close before the start
        $this->asStaff($pUser)->get(route('admin.practice.show'))->assertSee('Career');
        [, $other] = $this->practitioner('Pt. Other');
        $this->asStaff($other)->put(route('admin.practice.booking', $b->ref), ['status' => 'completed'])->assertNotFound();
        $this->asStaff($pUser)->put(route('admin.practice.booking', $b->ref), ['status' => 'completed'])->assertSessionHasErrors('status');
        Carbon::setTestNow('2026-11-03 05:10:00');
        $this->asStaff($pUser)->put(route('admin.practice.booking', $b->ref), ['status' => 'completed'])->assertSessionHasNoErrors();
        $e = DB::table('practitioner_earnings')->first();
        $this->assertSame([100000, 6000, 60000, 'pending'], [(int) $e->gross_minor, (int) $e->share_bps, (int) $e->amount_minor, $e->status]);
        $this->assertSame(60000, (int) DB::table('ledger_accounts')->where('code', 'practitioner_payable:'.$p->id)->value('balance_minor'));

        $finance = $this->makeStaff(['finance']);
        $this->expectsValidation(fn () => app(Payouts::class)->propose($p, $finance), 'payout'); // no payout details yet
        $this->asStaff($pUser)->put(route('admin.practice.payout'), ['method' => 'upi', 'upi' => 'not-upi'])->assertSessionHasErrors('upi');
        $this->asStaff($pUser)->put(route('admin.practice.payout'), ['method' => 'upi', 'upi' => 'test.astro@okbank'])->assertSessionHasNoErrors();
        $p = DB::table('practitioners')->find($p->id);
        $this->assertStringNotContainsString('test.astro', $p->payout_details);
        $this->expectsValidation(fn () => app(Payouts::class)->propose($p, $finance), 'payout'); // still in refund window

        Carbon::setTestNow('2026-11-11 00:00:00');
        $this->assertSame(1, app(Payouts::class)->release());
        $this->asStaff($finance)->post(route('admin.practitioners.payout', $p->ref))->assertSessionHasNoErrors();
        $po = DB::table('payouts')->first();
        $this->assertSame([60000, 'proposed'], [(int) $po->amount_minor, $po->status]);
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'approve'])->assertSessionHasErrors('payout');
        $this->asStaff($pUser)->post(route('admin.payouts.action', $po->ref), ['action' => 'approve'])->assertForbidden();
        $finance2 = $this->makeStaff(['finance']);
        $this->asStaff($finance2)->post(route('admin.payouts.action', $po->ref), ['action' => 'paid', 'utr' => 'UTR12345'])->assertSessionHasErrors('utr');
        $this->asStaff($finance2)->post(route('admin.payouts.action', $po->ref), ['action' => 'approve'])->assertSessionHasNoErrors();
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'paid', 'utr' => 'x'])->assertSessionHasErrors('utr');
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'paid', 'utr' => 'n123456789'])->assertSessionHasNoErrors();
        $this->assertSame(['paid', 'N123456789'], [DB::table('payouts')->find($po->id)->status, DB::table('payouts')->find($po->id)->utr]);
        $this->assertSame(0, (int) DB::table('ledger_accounts')->where('code', 'practitioner_payable:'.$p->id)->value('balance_minor'));
        $this->assertSame('paid', DB::table('practitioner_earnings')->value('status'));
        $this->assertSame([], app(Ledger::class)->verify(), 'ledger balanced');
        $csv = $this->asStaff($pUser)->get(route('admin.practice.statement'))->assertOk()->streamedContent();
        $this->assertStringContainsString('600.00', $csv);
        $this->assertStringContainsString('N123456789', $csv);
    }

    public function test_refund_before_payout_reduces_earning(): void
    {
        [$p] = $this->practitioner();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $this->teamVariant(), '2026-11-03T04:30Z', null, null, 'rf', false, $p->ref);
        $this->capture($r['order']);
        app(Slots::class)->setOutcome(DB::table('bookings')->find($b->id), 'completed', null, $this->makeStaff(['administrator']));
        $order = DB::table('orders')->find($r['order']->id);
        app(Refunds::class)->policyRefundToWallet($order, 40000, 'Partial goodwill refund');
        $e = DB::table('practitioner_earnings')->first();
        $this->assertSame([60000, 36000], [(int) $e->gross_minor, (int) $e->amount_minor]);
        $this->assertSame(36000, (int) DB::table('ledger_accounts')->where('code', 'practitioner_payable:'.$p->id)->value('balance_minor'));
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'title' => 'Refund processed']);
    }

    // ================================================================ app API parity

    public function test_app_service_purchase_verify_and_next_step(): void
    {
        $u = $this->customer();
        $t = $this->token($u);
        $o = Offering::where('slug', 'palmistry-photo-report')->first();
        $o->forceFill(['status' => 'active'])->save();
        $v = $o->variants()->first();
        $v->forceFill(['price_minor' => 110000, 'active' => true])->save();

        $res = $this->withToken($t)->postJson('/api/v1/checkout/service', ['variant' => $v->ref, 'key' => 'svc1'])->assertOk();
        $ref = $res->json('order.ref');
        $rzpOrder = $res->json('razorpay.order_id');
        $res->assertJsonPath('razorpay.amount', 110000)->assertJsonPath('razorpay.prefill.email', $u->email)->assertJsonPath('order.status', 'pending_payment');
        $this->withToken($t)->postJson('/api/v1/checkout/service', ['variant' => $v->ref, 'key' => 'svc1'])->assertJsonPath('order.ref', $ref, 'idempotent');

        // other users cannot see or verify it
        $other = $this->token($this->customer());
        $this->withToken($other)->getJson("/api/v1/orders/$ref")->assertNotFound();

        $this->withToken($t)->postJson("/api/v1/orders/$ref/verify", ['razorpay_order_id' => $rzpOrder, 'razorpay_payment_id' => 'pay_X1', 'razorpay_signature' => 'forged'])->assertStatus(422);
        $sig = hash_hmac('sha256', $rzpOrder.'|pay_X1', self::RZP_SECRET);
        $this->captures[$rzpOrder] = ['id' => 'pay_X1', 'status' => 'captured', 'amount' => 110000, 'method' => 'upi'];
        $this->withToken($t)->postJson("/api/v1/orders/$ref/verify", ['razorpay_order_id' => $rzpOrder, 'razorpay_payment_id' => 'pay_X1', 'razorpay_signature' => $sig])
            ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.next.type', 'palm_upload');
        $palmRef = $this->withToken($t)->getJson("/api/v1/orders/$ref")->json('data.next.ref');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'title' => 'Payment received']);

        // palm photos from the app
        $this->withToken($t)->getJson("/api/v1/palmistry/$palmRef")->assertOk()->assertJsonPath('data.can_upload', true);
        $this->withToken($other)->postJson("/api/v1/palmistry/$palmRef/photos", [])->assertNotFound();
        $this->withToken($t)->post("/api/v1/palmistry/$palmRef/photos", ['left' => UploadedFile::fake()->image('l.jpg', 900, 1200), 'right' => UploadedFile::fake()->image('r.jpg', 900, 1200),
            'dominant_hand' => 'right', 'consent' => '1'], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.can_upload', false);
        $this->withToken($t)->getJson('/api/v1/orders')->assertJsonCount(1, 'data');
    }

    public function test_app_wallet_store_and_booking_cancel(): void
    {
        $u = $this->customer();
        $t = $this->token($u);
        $l = app(Ledger::class);
        $l->post('bonus_grant', 'fund-'.$u->id, [[$l->account('bonus_expense', 'expense'), 'debit', 500000], [$l->wallet($u->id), 'credit', 500000]]);
        $this->withToken($t)->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('balance_minor', 500000);

        // gem store
        $ref = (string) Str::ulid();
        DB::table('products')->insert(['ref' => $ref, 'slug' => 'pukhraj-test', 'category' => 'gemstone', 'name' => 'Yellow Sapphire 5.25 ct', 'summary' => 'Ceylon', 'weight' => '5.25 ct',
            'certification' => 'Lab cert', 'price_minor' => 1550000, 'stock' => 1, 'max_per_order' => 2, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/store/products')->assertOk()->assertJsonPath('data.0.slug', 'pukhraj-test')->assertJsonPath('data.0.max_per_order', 1);
        $addr = ['name' => 'Ramesh', 'phone' => '9123456789', 'line1' => 'House 1', 'city' => 'Patna', 'state' => 'Bihar', 'pincode' => '800020'];
        $this->withToken($t)->postJson('/api/v1/checkout/product', ['product' => $ref, 'quantity' => 1, 'address' => ['pincode' => '12'] + $addr, 'key' => 'g0'])->assertJsonValidationErrors('address.pincode');
        $this->withToken($t)->postJson('/api/v1/checkout/product', ['product' => $ref, 'quantity' => 1, 'address' => $addr, 'key' => 'g1'])->assertOk()->assertJsonPath('order.kind', 'product');
        $this->assertSame(0, (int) DB::table('products')->where('ref', $ref)->value('stock'));

        // wallet-paid consultation from the app, then cancel → refund to wallet
        DB::table('availability_rules')->insert(['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->where('mode', 'video')->first();
        $v->update(['price_minor' => 210000, 'active' => true]);
        $this->withToken($t)->postJson('/api/v1/checkout/service', ['variant' => $v->ref, 'key' => 'x'])->assertJsonValidationErrors('variant');
        $res = $this->withToken($t)->postJson('/api/v1/checkout/booking', ['variant' => $v->ref, 'start' => '2026-11-03T04:30Z', 'use_wallet' => true, 'key' => 'w1'])->assertOk();
        $res->assertJsonPath('razorpay', null)->assertJsonPath('booking.status', 'confirmed')->assertJsonPath('order.next.type', 'booking');
        $bref = $res->json('booking.ref');
        $this->withToken($t)->getJson('/api/v1/bookings')->assertJsonPath('data.0.can_cancel', true)->assertJsonPath('data.0.variant', $v->ref);
        $this->withToken($this->token($this->customer()))->postJson("/api/v1/bookings/$bref/cancel")->assertNotFound();
        $this->withToken($t)->postJson("/api/v1/bookings/$bref/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(500000, $l->walletBalance($u->id));
    }
}
