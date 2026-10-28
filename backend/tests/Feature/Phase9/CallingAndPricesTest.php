<?php

namespace Tests\Feature\Phase9;

use App\Domain\Booking\Slots;
use App\Domain\Meetings\LiveKit;
use App\Domain\Meetings\Meetings;
use App\Domain\Payments\Checkout;
use App\Models\Offering;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Carbon\Carbon;
use Database\Seeders\CatalogueSeeder;
use Database\Seeders\MarketPriceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** In-app calls (LiveKit), auto-created Zoom / Google Meet links, and market starting prices. */
class CallingAndPricesTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private const LK_SECRET = 'lk-secret-for-tests-0123456789abcdef';
    private int $seq = 0;
    private array $zoomCalls = [];
    private array $googleCalls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 03:00:00');
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        Mail::fake();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('F', 14));
        $s->set('payments.razorpay.key_secret', 'sec', secret: true);
        $s->set('payments.razorpay.webhook_secret', 'wh', secret: true);
        Http::fake([
            'api.razorpay.com/v1/orders' => fn ($r) => Http::response(['id' => 'order_C'.(++$this->seq), 'amount' => $r['amount']]),
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'zoom-at', 'expires_in' => 3600]),
            'api.zoom.us/v2/*' => function ($req) {
                $this->zoomCalls[] = [$req->method(), $req->url(), $req->data()];

                return $req->method() === 'POST' ? Http::response(['id' => 81234567890, 'join_url' => 'https://us06web.zoom.us/j/81234567890?pwd=x'], 201) : Http::response(null, 204);
            },
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'g-at', 'refresh_token' => 'g-rt', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/*' => function ($req) {
                $this->googleCalls[] = [$req->method(), $req->url(), $req->data()];

                return $req->method() === 'POST' ? Http::response(['id' => 'evt123', 'hangoutLink' => 'https://meet.google.com/abc-defg-hij']) : Http::response([], 200);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Caller', 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function livekit(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('calling.livekit.url', 'wss://rtc.example.test');
        $s->set('calling.livekit.key', 'APIkey123');
        $s->set('calling.livekit.secret', self::LK_SECRET, secret: true);
    }

    private function paidBooking(User $u, string $mode = 'video', string $start = '2026-11-03T04:30Z'): object
    {
        DB::table('availability_rules')->insertOrIgnore(['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->where('mode', $mode)->first();
        $v->update(['price_minor' => 210000, 'active' => true]);
        [$b, $r] = app(Slots::class)->hold($u, $v->fresh(), $start, null, null, 'k'.uniqid());
        $p = DB::table('payments')->where('order_id', $r['order']->id)->first();
        app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$p->id, (int) $p->amount_minor);

        return DB::table('bookings')->find($b->id);
    }

    private function decode(string $jwt): array
    {
        [$h, $p, $sig] = explode('.', $jwt);
        $this->assertSame(rtrim(strtr(base64_encode(hash_hmac('sha256', "$h.$p", self::LK_SECRET, true)), '+/', '-_'), '='), $sig, 'HS256 signature');

        return json_decode(base64_decode(strtr($p, '-_', '+/')), true);
    }

    public function test_paid_online_booking_gets_in_app_call_and_token_window(): void
    {
        $this->livekit();
        $u = $this->customer();
        $b = $this->paidBooking($u);
        $this->assertSame('livekit', $b->meeting_provider);
        $this->assertSame(route('call.join', $b->ref), $b->meeting_link);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'title' => 'Your consultation link']);

        $this->actingAs($u)->get(route('call.join', $b->ref))->assertOk()->assertSee('Join call')->assertHeader('Content-Security-Policy');
        $this->actingAs($this->customer())->get(route('call.join', $b->ref))->assertNotFound();
        $this->actingAs($u)->postJson(route('call.token', $b->ref))->assertStatus(422)->assertJsonPath('error.code', 'call_not_available'); // too early

        Carbon::setTestNow('2026-11-03 04:20:00'); // 10 min before
        $res = $this->actingAs($u)->postJson(route('call.token', $b->ref))->assertOk()->assertJsonPath('room', 'booking-'.$b->ref)->assertJsonPath('url', 'wss://rtc.example.test')->assertJsonPath('audio_only', false);
        $c = $this->decode($res->json('token'));
        $this->assertSame(['APIkey123', 'c-'.$u->ref, 'booking-'.$b->ref, true], [$c['iss'], $c['sub'], $c['video']['room'], $c['video']['roomJoin']]);
        $this->assertLessThanOrEqual(7200, $c['exp'] - time());
        $this->assertArrayNotHasKey('roomAdmin', $c['video']);

        // Office staff (bookings.manage) can join Acharya Ji's booking; others cannot
        $staff = $this->makeStaff(['administrator']);
        $this->asStaff($staff)->postJson(route('call.token', $b->ref))->assertOk();
        $this->asStaff($this->makeStaff(['content_author']))->postJson(route('call.token', $b->ref))->assertNotFound();

        // The app gets the same token through the API
        $tok = $u->createToken('t', ['customer'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($tok)->getJson('/api/v1/bookings')->assertJsonPath('data.0.meeting_provider', 'livekit')->assertJsonPath('data.0.call_open', true)->assertJsonPath('data.0.meeting_link', null);
        $this->withToken($tok)->postJson("/api/v1/bookings/{$b->ref}/call-token")->assertOk()->assertJsonPath('identity', 'c-'.$u->ref);

        Carbon::setTestNow('2026-11-03 05:31:00'); // 31 min after the end
        $this->actingAs($u)->postJson(route('call.token', $b->ref))->assertStatus(422);
    }

    public function test_in_person_bookings_get_no_call_and_unconfigured_stays_manual(): void
    {
        $u = $this->customer();
        $b = $this->paidBooking($u); // no provider configured at all
        $this->assertNull($b->meeting_provider);
        $this->assertNull($b->meeting_link);
        $this->livekit();
        $p = $this->paidBooking($this->customer(), 'in_person', '2026-11-03T05:30Z');
        $this->assertNull($p->meeting_provider);
    }

    public function test_zoom_default_creates_moves_and_deletes_meeting(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('calling.default', 'zoom');
        $s->set('calling.zoom.account_id', 'acct1');
        $s->set('calling.zoom.client_id', 'cid1');
        $s->set('calling.zoom.client_secret', 'csecret', secret: true);
        $u = $this->customer();
        $b = $this->paidBooking($u);
        $this->assertSame(['zoom', 'https://us06web.zoom.us/j/81234567890?pwd=x', '81234567890'], [$b->meeting_provider, $b->meeting_link, $b->meeting_external_id]);
        [$m, $url, $body] = $this->zoomCalls[0];
        $this->assertSame(['POST', 'https://api.zoom.us/v2/users/me/meetings', '2026-11-03T04:30:00Z', 30, true], [$m, $url, $body['start_time'], $body['duration'], $body['settings']['waiting_room']]);
        $this->assertStringNotContainsString($u->name, $body['topic'], 'no customer name sent to Zoom');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'zoom.us/oauth/token') && $r->hasHeader('Authorization', 'Basic '.base64_encode('cid1:csecret')));

        app(Slots::class)->reschedule($b, $u, '2026-11-10T05:00Z');
        $this->assertSame(['PATCH', 'https://api.zoom.us/v2/meetings/81234567890'], array_slice($this->zoomCalls[1], 0, 2));
        $this->assertSame('2026-11-10T05:00:00Z', $this->zoomCalls[1][2]['start_time']);
        app(Slots::class)->cancelByCustomer(DB::table('bookings')->find($b->id), $u);
        $this->assertSame(['DELETE', 'https://api.zoom.us/v2/meetings/81234567890'], array_slice($this->zoomCalls[2], 0, 2));
    }

    public function test_google_connect_flow_and_meet_link_from_admin(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->put(route('admin.calling.update'), ['default' => 'manual', 'google_client_id' => 'abc.apps.googleusercontent.com', 'google_client_secret' => 'gsecret'])->assertSessionHasNoErrors();
        $this->asStaff($owner)->put(route('admin.calling.update'), ['default' => 'manual', 'livekit_url' => 'https://not-wss.example'])->assertSessionHasErrors('livekit_url');
        $res = $this->asStaff($owner)->post(route('admin.calling.google.connect'))->assertRedirect();
        $url = session('google_url');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $q);
        $this->assertSame(['abc.apps.googleusercontent.com', 'offline', 'https://www.googleapis.com/auth/calendar.events'], [$q['client_id'], $q['access_type'], $q['scope']]);
        $this->asStaff($owner)->get(route('admin.calling.google.callback', ['state' => 'forged', 'code' => 'c']))->assertSessionHasErrors('provider');
        $this->asStaff($owner)->withSession(['google_oauth_state' => $q['state']])->get(route('admin.calling.google.callback', ['state' => $q['state'], 'code' => 'auth-code']))
            ->assertRedirect(route('admin.calling.edit'))->assertSessionHas('status');
        $this->assertTrue(app(Meetings::class)->configured('google_meet'));
        $this->assertStringNotContainsString('g-rt', (string) DB::table('settings')->where('key', 'calling.google.refresh_token')->value('value'));

        $u = $this->customer();
        $b = $this->paidBooking($u);
        $this->assertNull($b->meeting_link, 'default manual: no automatic link');
        $this->asStaff($owner)->put(route('admin.ops.booking', $b->ref), ['provider' => 'google_meet'])->assertSessionHasNoErrors()->assertRedirect();
        $b = DB::table('bookings')->find($b->id);
        $this->assertSame(['google_meet', 'https://meet.google.com/abc-defg-hij', 'evt123'], [$b->meeting_provider, $b->meeting_link, $b->meeting_external_id]);
        [$m, $url, $body] = end($this->googleCalls);
        $this->assertSame('POST', $m);
        $this->assertStringContainsString('calendars/primary/events?conferenceDataVersion=1&sendUpdates=none', $url);
        $this->assertSame('hangoutsMeet', $body['conferenceData']['createRequest']['conferenceSolutionKey']['type']);
        $this->assertArrayNotHasKey('attendees', $body, 'customer email is not shared with Google');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $u->id, 'title' => 'Your consultation link']);
        // Zoom not set up → clear error
        $this->asStaff($owner)->put(route('admin.ops.booking', $b->ref), ['provider' => 'zoom'])->assertSessionHasErrors('provider');
        // Manual link replaces (and deletes) the Meet event
        $this->asStaff($owner)->put(route('admin.ops.booking', $b->ref), ['meeting_link' => 'https://example.com/room'])->assertSessionHasNoErrors();
        $this->assertSame('DELETE', end($this->googleCalls)[0]);
        $this->assertSame(['manual', 'https://example.com/room'], [DB::table('bookings')->find($b->id)->meeting_provider, DB::table('bookings')->find($b->id)->meeting_link]);
        $this->asStaff($owner)->get(route('admin.calling.edit'))->assertOk()->assertSee('Google account connected')->assertDontSee('gsecret');
    }

    public function test_market_prices_fill_once_and_publish_reports_only(): void
    {
        $this->seed(MarketPriceSeeder::class);
        $price = fn ($slug, $name = null) => (int) Offering::where('slug', $slug)->first()->variants()->when($name, fn ($q) => $q->where('name', 'like', "%$name%"))->value('price_minor');
        $this->assertSame(210000, $price('consultation-with-acharya-ji', 'Audio call — 30'));
        $this->assertSame(410000, $price('consultation-with-acharya-ji', 'Video call — 1 hour'));
        $this->assertSame(1100000, $price('vastu-consultation', 'On-site'));
        $this->assertSame(49900, $price('premium-personalized-kundli'));
        $this->assertSame(69900, $price('ask-one-question'));
        $this->assertSame(2100000, $price('navgrah-shanti-puja'));
        $this->assertSame(999900, $price('elite-astrologer-consultation', 'Urgent'));
        $this->assertSame(0, DB::table('offering_variants')->whereNull('price_minor')->count());
        $this->assertSame('active', Offering::where('slug', 'lal-kitab-report')->value('status'));
        $this->assertSame('draft', Offering::where('slug', 'navgrah-shanti-puja')->value('status'), 'puja needs an authorised priest/temple');
        $this->assertSame('draft', Offering::where('slug', 'elite-astrologer-consultation')->value('status'), 'team tier needs an approved astrologer');

        // Owner changes are never overwritten
        Offering::where('slug', 'premium-personalized-kundli')->first()->variants()->update(['price_minor' => 59900]);
        Offering::where('slug', 'lal-kitab-report')->update(['status' => 'draft']);
        $this->seed(MarketPriceSeeder::class);
        $this->assertSame(59900, $price('premium-personalized-kundli'));
        $this->assertSame('draft', Offering::where('slug', 'lal-kitab-report')->value('status'));
    }
}
