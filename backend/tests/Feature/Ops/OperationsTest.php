<?php

namespace Tests\Feature\Ops;

use App\Domain\Booking\Slots;
use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Puja\PujaBookings;
use App\Models\BirthProfile;
use App\Models\Offering;
use App\Models\OfferingVariant;
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

/** Phase 8: slot booking, puja fulfilment, expert reports, support, privacy, Rashifal, vrat calendar. */
class OperationsTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        // Monday 2 Nov 2026, 08:30 IST
        Carbon::setTestNow('2026-11-02 03:00:00');
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('D', 14));
        $s->set('payments.razorpay.key_secret', 'sec', secret: true);
        $s->set('payments.razorpay.webhook_secret', 'wh', secret: true);
        Http::fake(['api.razorpay.com/v1/orders' => fn ($r) => Http::response(['id' => 'order_P'.(++$this->seq), 'amount' => $r['amount']])]);
        Storage::fake('local');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Seeker', 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function profile(User $u, string $name = 'Seeker'): BirthProfile
    {
        $p = new BirthProfile(['label' => 'self', 'name' => $name, 'gender' => 'male', 'birth_date' => '1990-05-15', 'birth_time' => '10:30', 'time_accuracy' => 'exact',
            'place_label' => 'Patna, Bihar, India', 'lat' => 25.59408, 'lng' => 85.13563, 'tz_name' => 'Asia/Kolkata', 'is_primary' => true]);
        $p->forceFill(['user_id' => $u->id, 'birth_utc' => '1990-05-15 05:00:00'])->save();

        return $p;
    }

    private function capture(object $order): void
    {
        $p = DB::table('payments')->where('order_id', $order->id)->first();
        $this->assertSame('applied', app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$order->id, (int) $p->amount_minor, 'upi'));
    }

    private function consultVariant(): OfferingVariant
    {
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->where('mode', 'video')->first();
        $v->update(['price_minor' => 210000, 'active' => true, 'duration_min' => 30]);

        return $v->fresh();
    }

    /** Tuesday 10:00–12:00 IST in 30-minute units. */
    private function hours(): void
    {
        DB::table('availability_rules')->insert(['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function serviceOrder(string $slug, User $u, int $price = 510000): object
    {
        $o = Offering::where('slug', $slug)->first();
        $o->forceFill(['status' => 'active'])->save();
        $v = $o->variants()->first() ?? $o->variants()->create(['ref' => (string) Str::ulid(), 'name' => 'Standard', 'mode' => 'report', 'participants' => 1, 'active' => true]);
        $v->forceFill(['price_minor' => $price, 'active' => true, 'mode' => in_array($v->mode, Slots::SLOT_MODES, true) ? 'report' : $v->mode])->save();
        $r = app(Checkout::class)->startServiceOrder($u, $v->fresh(), false, 'k-'.uniqid());
        $this->capture($r['order']);

        return DB::table('orders')->find($r['order']->id);
    }

    // ------------------------------------------------------------------ slots

    public function test_slots_respect_rules_lead_time_and_leave(): void
    {
        $this->hours();
        $slots = app(Slots::class)->available(30, 14);
        // Tuesdays 3 Nov and 10 Nov: 4 slots each (10:00, 10:30, 11:00, 11:30 IST)
        $this->assertSame(['2026-11-03', '2026-11-10'], array_keys($slots));
        $this->assertSame('2026-11-03T04:30Z', $slots['2026-11-03'][0]['start']);
        $this->assertSame('10:00 AM', $slots['2026-11-03'][0]['label']);
        $this->assertCount(4, $slots['2026-11-03']);
        $this->assertCount(3, app(Slots::class)->available(60, 14)['2026-11-03'] ?? [], '60 min needs two consecutive units');

        DB::table('availability_exceptions')->insert(['date' => '2026-11-10', 'reason' => 'Travel', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(['2026-11-03'], array_keys(app(Slots::class)->available(30, 14)));

        // Lead time: 180 minutes
        Carbon::setTestNow('2026-11-03 02:00:00'); // 07:30 IST → earliest 10:30 IST
        $this->assertSame('2026-11-03T05:00Z', app(Slots::class)->available(30, 1)['2026-11-03'][0]['start']);
    }

    public function test_book_pay_confirm_and_no_double_booking(): void
    {
        $this->hours();
        $v = $this->consultVariant();
        $a = $this->customer();
        $b = $this->customer();

        // /checkout redirects consultation variants to the time picker; the domain refuses a slot-less order
        $this->actingAs($a)->post('/checkout', ['variant' => $v->ref, 'key' => 'x1'])->assertRedirect(route('account.book', $v->ref));
        try {
            app(Checkout::class)->startServiceOrder($a, $v, false, 'direct');
            $this->fail('slot-less consultation order allowed');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->actingAs($a)->get(route('account.book', $v->ref))->assertOk()->assertSee('10:00 AM')->assertSee('2026-11-03T04:30Z');
        $res = $this->actingAs($a)->post(route('account.book', $v->ref), ['start' => '2026-11-03T04:30Z', 'topic' => 'Career', 'key' => 'h1']);
        $order = DB::table('orders')->where('user_id', $a->id)->first();
        $res->assertRedirect(route('checkout.show', $order->ref));
        $booking = DB::table('bookings')->where('user_id', $a->id)->first();
        $this->assertSame('held', $booking->status);
        $this->assertSame($order->id, $booking->order_id);
        $this->assertSame('2026-11-03T04:30Z', json_decode($order->snapshot, true)['start_utc']);

        // Held slot disappears for everyone; a second customer cannot take it
        $this->actingAs($b)->get(route('account.book', $v->ref))->assertDontSee('2026-11-03T04:30Z');
        $this->actingAs($b)->post(route('account.book', $v->ref), ['start' => '2026-11-03T04:30Z', 'key' => 'h2'])->assertSessionHasErrors('start');
        // Even bypassing the availability check, the UNIQUE unit index refuses the double booking
        $this->expectsDatabaseUniqueFailure(fn () => DB::table('booking_units')->insert(['booking_id' => $booking->id, 'unit_start' => '2026-11-03 04:30:00']));

        $this->capture($order);
        $this->assertSame('confirmed', DB::table('bookings')->find($booking->id)->status);
        $this->actingAs($a)->get(route('account.bookings'))->assertOk()->assertSee('Confirmed', false);
        // Other customers cannot see or change it
        $this->actingAs($b)->post(route('account.bookings.cancel', $booking->ref))->assertNotFound();
        $this->actingAs($b)->get(route('account.bookings.reschedule', $booking->ref))->assertNotFound();
    }

    private function expectsDatabaseUniqueFailure(callable $fn): void
    {
        try {
            $fn();
            $this->fail('duplicate unit accepted');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Duplicate', $e->getMessage());
        }
    }

    public function test_unpaid_hold_expires_and_frees_slot(): void
    {
        $this->hours();
        $v = $this->consultVariant();
        $u = $this->customer();
        [$b] = app(Slots::class)->hold($u, $v, '2026-11-03T05:00Z', null, null, 'e1');
        $this->assertSame(0, app(Slots::class)->expireHolds());
        Carbon::setTestNow(now()->addMinutes(16));
        $this->assertSame(1, app(Slots::class)->expireHolds());
        $this->assertSame('cancelled', DB::table('bookings')->find($b->id)->status);
        $this->assertSame('cancelled', DB::table('orders')->find($b->order_id)->status);
        $this->assertSame(0, DB::table('booking_units')->count());
        $this->assertContains('2026-11-03T05:00Z', array_column(app(Slots::class)->available(30, 3)['2026-11-03'], 'start'));

        // A late capture on the cancelled order is flagged for refund, never silently confirmed
        $p = DB::table('payments')->where('order_id', $b->order_id)->first();
        $this->assertSame('captured_after_cancel', app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_late', (int) $p->amount_minor));
        $this->assertSame('cancelled', DB::table('bookings')->find($b->id)->status);
    }

    public function test_paid_hold_is_not_expired(): void
    {
        $this->hours();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $this->consultVariant(), '2026-11-03T05:00Z', null, null, 'p1');
        Carbon::setTestNow(now()->addMinutes(20));
        $this->capture($r['order']); // payment arrives after the hold window but before the sweeper
        $this->assertSame(0, app(Slots::class)->expireHolds());
        $this->assertSame('confirmed', DB::table('bookings')->find($b->id)->status);
    }

    public function test_fully_wallet_paid_booking_is_confirmed_immediately(): void
    {
        $this->hours();
        $u = $this->customer();
        $l = app(Ledger::class);
        $l->post('bonus_grant', 'fund-'.$u->id, [[$l->account('bonus_expense', 'expense'), 'debit', 300000], [$l->wallet($u->id), 'credit', 300000]]);
        $v = $this->consultVariant();
        $this->actingAs($u)->get(route('account.book', $v->ref))->assertSee('Use wallet balance first');
        $this->actingAs($u)->post(route('account.book', $v->ref), ['start' => '2026-11-03T04:30Z', 'use_wallet' => '1', 'key' => 'wal'])->assertRedirect();
        $b = DB::table('bookings')->where('user_id', $u->id)->first();
        $this->assertSame('confirmed', $b->status);
        $this->assertSame('paid', DB::table('orders')->find($b->order_id)->status);
        $this->assertSame(90000, $l->walletBalance($u->id));
        Carbon::setTestNow(now()->addHour());
        $this->assertSame(0, app(Slots::class)->expireHolds());
    }

    public function test_reused_form_key_does_not_create_a_second_booking(): void
    {
        $this->hours();
        $u = $this->customer();
        $v = $this->consultVariant();
        app(Slots::class)->hold($u, $v, '2026-11-03T04:30Z', null, null, 'same');
        $this->actingAs($u)->post(route('account.book', $v->ref), ['start' => '2026-11-03T05:00Z', 'key' => 'same'])->assertSessionHasErrors('start');
        $this->assertSame(1, DB::table('bookings')->where('status', 'held')->count());
        $this->assertSame(1, DB::table('booking_units')->count());
    }

    public function test_hold_limit_per_customer(): void
    {
        $this->hours();
        $v = $this->consultVariant();
        $u = $this->customer();
        app(Slots::class)->hold($u, $v, '2026-11-03T04:30Z', null, null, 'l1');
        app(Slots::class)->hold($u, $v, '2026-11-03T05:00Z', null, null, 'l2');
        $this->expectException(ValidationException::class);
        app(Slots::class)->hold($u, $v, '2026-11-03T05:30Z', null, null, 'l3');
    }

    public function test_cancel_refunds_to_wallet_and_reschedule_rules(): void
    {
        $this->hours();
        $v = $this->consultVariant();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $v, '2026-11-03T04:30Z', null, null, 'c1');
        $this->capture($r['order']);

        // Reschedule once to the following Tuesday
        $this->actingAs($u)->get(route('account.bookings.reschedule', $b->ref))->assertOk()->assertSee('2026-11-10T04:30Z');
        $this->actingAs($u)->post(route('account.bookings.reschedule', $b->ref), ['start' => '2026-11-10T04:30Z'])->assertRedirect(route('account.bookings'));
        $moved = DB::table('bookings')->find($b->id);
        $this->assertSame('2026-11-10 04:30:00', (string) $moved->start_utc);
        $this->assertSame(1, (int) $moved->reschedules);
        $this->assertSame(['2026-11-10 04:30:00'], DB::table('booking_units')->pluck('unit_start')->map(fn ($x) => (string) $x)->all());
        // Second reschedule is refused
        $this->actingAs($u)->post(route('account.bookings.reschedule', $b->ref), ['start' => '2026-11-10T05:00Z'])->assertSessionHasErrors('start');

        // Cancel → full refund to wallet, slot freed, cannot cancel twice
        $this->actingAs($u)->post(route('account.bookings.cancel', $b->ref))->assertRedirect(route('account.bookings'));
        $this->assertSame('cancelled', DB::table('bookings')->find($b->id)->status);
        $this->assertSame(210000, app(Ledger::class)->walletBalance($u->id));
        $this->assertSame('refunded', DB::table('orders')->find($b->order_id)->status);
        $this->assertSame(0, DB::table('booking_units')->count());
        $this->actingAs($u)->post(route('account.bookings.cancel', $b->ref))->assertSessionHasErrors('booking');
        $this->assertSame(210000, app(Ledger::class)->walletBalance($u->id));
    }

    public function test_cancel_refused_inside_change_window(): void
    {
        $this->hours();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $this->consultVariant(), '2026-11-03T04:30Z', null, null, 'w1');
        $this->capture($r['order']);
        Carbon::setTestNow('2026-11-02 06:00:00'); // 22.5 hours before
        $this->actingAs($u)->post(route('account.bookings.cancel', $b->ref))->assertSessionHasErrors('booking');
        $this->assertSame('confirmed', DB::table('bookings')->find($b->id)->status);
    }

    public function test_admin_availability_and_booking_outcome(): void
    {
        $staff = $this->makeStaff(['administrator']);
        $this->asStaff($staff)->post(route('admin.ops.rule'), ['weekday' => 2, 'start_local' => '10:00', 'end_local' => '12:00', 'slot_minutes' => 30])->assertSessionHasNoErrors()->assertRedirect();
        $this->asStaff($staff)->post(route('admin.ops.rule'), ['weekday' => 2, 'start_local' => '12:00', 'end_local' => '10:00', 'slot_minutes' => 30])->assertSessionHasErrors('end_local');
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $this->consultVariant(), '2026-11-03T04:30Z', null, 'Job change', 'a1');
        $this->capture($r['order']);
        $this->asStaff($staff)->get(route('admin.ops.bookings'))->assertOk()->assertSee('Seeker')->assertSee('Job change');
        $this->asStaff($staff)->put(route('admin.ops.booking', $b->ref), ['meeting_link' => 'http://insecure.example'])->assertSessionHasErrors('meeting_link');
        $this->asStaff($staff)->put(route('admin.ops.booking', $b->ref), ['meeting_link' => 'https://meet.example.com/abc'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($u)->get(route('account.bookings'))->assertSee('https://meet.example.com/abc');
        $this->asStaff($staff)->put(route('admin.ops.booking', $b->ref), ['status' => 'completed'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', DB::table('bookings')->find($b->id)->status);

        // Content author has no booking access
        $this->asStaff($this->makeStaff(['content_author']))->get(route('admin.ops.bookings'))->assertForbidden();
    }

    // ------------------------------------------------------------------ puja

    private function supplier(array $over = []): object
    {
        $id = DB::table('suppliers')->insertGetId($over + ['ref' => (string) Str::ulid(), 'kind' => 'temple', 'name' => 'Test Temple Trust', 'authorization_doc' => 'suppliers/a.pdf',
            'verified_at' => now(), 'authorized_until' => '2027-12-31', 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('suppliers')->find($id);
    }

    public function test_puja_publish_requires_authorised_supplier(): void
    {
        $o = Offering::where('slug', 'rudrabhishek')->first();
        $this->assertFalse(PujaBookings::canPublish($o->id, $o->slug));
        $this->assertTrue(PujaBookings::canPublish(Offering::where('slug', 'puja-rituals')->value('id'), 'puja-rituals'));
        $expired = $this->supplier(['authorized_until' => '2026-10-01']);
        $unverified = $this->supplier(['verified_at' => null]);
        DB::table('offering_supplier')->insert([['offering_id' => $o->id, 'supplier_id' => $expired->id], ['offering_id' => $o->id, 'supplier_id' => $unverified->id]]);
        $this->assertFalse(PujaBookings::canPublish($o->id, $o->slug));

        $o->variants()->first()?->update(['price_minor' => 510000, 'active' => true]);
        $staff = $this->makeStaff(['owner']);
        $this->asStaff($staff)->put(route('admin.catalogue.status', $o->ref), ['status' => 'active'])->assertSessionHasErrors('status');
        $this->assertSame('draft', $o->fresh()->status);

        $ok = $this->supplier();
        DB::table('offering_supplier')->insert(['offering_id' => $o->id, 'supplier_id' => $ok->id]);
        $this->assertTrue(PujaBookings::canPublish($o->id, $o->slug));
    }

    public function test_supplier_authorisation_upload_validation(): void
    {
        $staff = $this->makeStaff(['administrator']);
        $o = Offering::where('slug', 'rudrabhishek')->first();
        $fakePdf = UploadedFile::fake()->createWithContent('auth.pdf', 'not a pdf at all');
        $this->asStaff($staff)->post(route('admin.ops.supplier'), ['kind' => 'temple', 'name' => 'Shiv Mandir', 'active' => '1', 'authorization' => $fakePdf, 'offerings' => [$o->id]])->assertSessionHasErrors('authorization');
        $this->assertSame(0, DB::table('suppliers')->count());

        $pdf = UploadedFile::fake()->createWithContent('auth.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->asStaff($staff)->post(route('admin.ops.supplier'), ['kind' => 'temple', 'name' => 'Shiv Mandir', 'active' => '1', 'authorized_until' => '2027-06-30', 'authorization' => $pdf, 'offerings' => [$o->id]])->assertSessionHasNoErrors()->assertRedirect();
        $s = DB::table('suppliers')->first();
        $this->assertSame($staff->id, $s->verified_by);
        Storage::disk('local')->assertExists($s->authorization_doc);
        $this->assertTrue(PujaBookings::canPublish($o->id, $o->slug));
        $this->asStaff($staff)->get(route('admin.ops.suppliers'))->assertOk()->assertSee('Shiv Mandir');
        $this->asStaff($this->makeStaff(['fulfilment']))->get(route('admin.ops.suppliers'))->assertForbidden();
    }

    public function test_puja_booking_lifecycle_and_privacy_of_proof(): void
    {
        $u = $this->customer();
        $other = $this->customer();
        $order = $this->serviceOrder('puja-rituals', $u);
        $b = DB::table('puja_bookings')->where('order_id', $order->id)->first();
        $this->assertSame('awaiting_details', $b->status);
        $this->actingAs($u)->get(route('account.puja.index'))->assertOk()->assertSee('Puja');
        $this->actingAs($other)->get(route('account.puja.show', $b->ref))->assertNotFound();
        $this->actingAs($other)->post(route('account.puja.details', $b->ref), [])->assertNotFound();

        $sankalp = ['devotees' => [['name' => 'Ramesh Kumar', 'relation' => 'self'], ['name' => 'Sita Devi', 'relation' => 'wife']], 'gotra' => 'Kashyap', 'wish' => 'Family health',
            'location' => 'online', 'preferred_date' => '2026-11-10', 'phone' => '9123456789'];
        $this->actingAs($u)->post(route('account.puja.details', $b->ref), ['location' => 'home'] + $sankalp)->assertSessionHasErrors('address');
        $this->actingAs($u)->post(route('account.puja.details', $b->ref), $sankalp)->assertSessionHasNoErrors()->assertRedirect();
        $row = DB::table('puja_bookings')->find($b->id);
        $this->assertSame('requested', $row->status);
        $this->assertStringNotContainsString('Ramesh', $row->sankalp, 'sankalp is encrypted at rest');
        $this->actingAs($u)->get(route('account.puja.show', $b->ref))->assertSee('Ramesh Kumar');

        $staff = $this->makeStaff(['fulfilment']);
        $this->asStaff($staff)->get(route('admin.ops.pujas'))->assertOk()->assertSee('Seeker');
        $this->asStaff($staff)->get(route('admin.ops.puja', $b->ref))->assertOk()->assertSee('Kashyap');

        // Cannot schedule with an unlinked or unauthorised supplier
        $stranger = $this->supplier();
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'schedule', 'supplier' => $stranger->id, 'scheduled_at' => '2026-11-10T07:00'])->assertSessionHasErrors('supplier');
        $linked = $this->supplier(['name' => 'Pt. Test Priest', 'kind' => 'priest']);
        DB::table('offering_supplier')->insert(['offering_id' => $b->offering_id, 'supplier_id' => $linked->id]);
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'schedule', 'supplier' => $linked->id, 'scheduled_at' => '2026-10-01T07:00'])->assertSessionHasErrors('scheduled_at');
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'complete'])->assertSessionHasErrors('status');
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'schedule', 'supplier' => $linked->id, 'scheduled_at' => '2026-11-10T07:00', 'live_link' => 'https://live.example.com/p'])->assertSessionHasNoErrors()->assertRedirect();
        $row = DB::table('puja_bookings')->find($b->id);
        $this->assertSame('scheduled', $row->status);
        $this->assertSame('2026-11-10 01:30:00', (string) $row->scheduled_at, 'IST input stored as UTC');
        // Sankalp is locked once scheduled
        $this->actingAs($u)->post(route('account.puja.details', $b->ref), $sankalp)->assertSessionHasErrors('devotees');

        // Performed needs real image proof
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'performed'])->assertSessionHasErrors('photos');
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'performed', 'photos' => [UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;')]])->assertSessionHasErrors();
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'performed', 'photos' => [UploadedFile::fake()->image('p1.jpg', 800, 600)], 'video_url' => 'https://video.example.com/v'])->assertSessionHasNoErrors()->assertRedirect();
        $this->asStaff($staff)->post(route('admin.ops.puja.action', $b->ref), ['action' => 'complete'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', DB::table('puja_bookings')->find($b->id)->status);

        // Proof photo: owner yes, other customer no, out-of-range index no
        $this->actingAs($u)->get(route('puja.photo', [$b->ref, 0]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($other)->get(route('puja.photo', [$b->ref, 0]))->assertNotFound();
        $this->actingAs($u)->get(route('puja.photo', [$b->ref, 5]))->assertNotFound();
        $this->asStaff($staff)->get(route('puja.photo', [$b->ref, 0]))->assertOk();
        $this->actingAs($u)->get(route('account.puja.show', $b->ref))->assertOk()->assertSee('https://video.example.com/v');
    }

    // ------------------------------------------------------------------ reports

    public function test_couple_report_two_profiles_chart_and_publish(): void
    {
        $u = $this->customer();
        $order = $this->serviceOrder('couple-kundli-compatibility-report', $u);
        $j = DB::table('report_jobs')->where('order_id', $order->id)->first();
        $this->assertSame('awaiting_details', $j->status);
        $a = $this->profile($u, 'Groom');
        $theirs = $this->profile($this->customer(), 'Stranger');
        $this->actingAs($u)->get(route('account.reports.show', $j->ref))->assertOk()->assertSee('Groom');
        $this->actingAs($u)->post(route('account.reports.details', $j->ref), ['profile_a' => $a->ref, 'language' => 'hi'])->assertSessionHasErrors('profile_b');
        $this->actingAs($u)->post(route('account.reports.details', $j->ref), ['profile_a' => $a->ref, 'profile_b' => $theirs->ref, 'language' => 'hi'])->assertSessionHasErrors('profile_a');
        $b = $this->profile($u, 'Bride');
        $b->forceFill(['birth_date' => '1992-08-20', 'birth_utc' => '1992-08-20 12:00:00'])->save();
        $this->actingAs($u)->post(route('account.reports.details', $j->ref), ['profile_a' => $a->ref, 'profile_b' => $b->ref, 'language' => 'hi'])->assertSessionHasNoErrors()->assertRedirect();
        $j = DB::table('report_jobs')->find($j->id);
        $this->assertSame('in_preparation', $j->status);
        $chart = json_decode($j->chart, true);
        $this->assertCount(2, $chart['charts']);
        $this->assertArrayHasKey('total', $chart['matching']);
        $this->actingAs($this->customer())->get(route('account.reports.show', $j->ref))->assertNotFound();

        $staff = $this->makeStaff(['fulfilment']);
        $this->asStaff($staff)->get(route('admin.ops.reports'))->assertOk()->assertSee('Seeker');
        $this->asStaff($staff)->get(route('admin.ops.report', $j->ref))->assertOk()->assertSee('Groom')->assertSee('Guna milan explained');
        $this->asStaff($staff)->post(route('admin.ops.report.save', $j->ref), ['action' => 'complete', 'sections' => ['guna' => 'short']])->assertSessionHasErrors('sections');
        $long = str_repeat('Detailed analysis based on both charts. ', 3);
        $this->asStaff($staff)->post(route('admin.ops.report.save', $j->ref), ['action' => 'complete', 'sections' => ['guna' => $long, 'manglik' => $long.'<script>alert(1)</script>']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('completed', DB::table('report_jobs')->find($j->id)->status);
        $page = $this->actingAs($u)->get(route('account.reports.show', $j->ref))->assertOk()->assertSee('Detailed analysis based on both charts');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->getContent());
        Mail::assertNothingQueued();
    }

    // ------------------------------------------------------------------ support

    public function test_tickets_internal_notes_hidden_and_ownership(): void
    {
        $u = $this->customer();
        $other = $this->customer();
        $foreignOrder = $this->serviceOrder('puja-rituals', $other);
        $this->actingAs($u)->post(route('account.support.store'), ['category' => 'payment', 'subject' => 'Refund?', 'body' => 'Where is my refund', 'order_ref' => $foreignOrder->ref])->assertSessionHasErrors('order_ref');
        $this->actingAs($u)->post(route('account.support.store'), ['category' => 'payment', 'subject' => 'Refund?', 'body' => 'Where is my refund'])->assertRedirect();
        $t = DB::table('tickets')->where('user_id', $u->id)->first();
        $this->assertSame('high', $t->priority);

        $staff = $this->makeStaff(['support']);
        $this->asStaff($staff)->get(route('admin.ops.tickets'))->assertOk()->assertSee('Refund?');
        $this->asStaff($staff)->post(route('admin.ops.ticket.reply', $t->ref), ['body' => 'INTERNAL: check gateway', 'internal' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $this->asStaff($staff)->post(route('admin.ops.ticket.reply', $t->ref), ['body' => 'We have processed it.'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('waiting_customer', DB::table('tickets')->find($t->id)->status);
        $this->asStaff($staff)->get(route('admin.ops.ticket', $t->ref))->assertSee('INTERNAL: check gateway');

        $this->actingAs($u)->get(route('account.support.show', $t->ref))->assertOk()->assertSee('We have processed it.')->assertDontSee('INTERNAL: check gateway');
        $this->actingAs($other)->get(route('account.support.show', $t->ref))->assertNotFound();
        $this->actingAs($other)->post(route('account.support.reply', $t->ref), ['body' => 'hijack'])->assertNotFound();
        $this->actingAs($u)->post(route('account.support.reply', $t->ref), ['body' => 'Thanks'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('open', DB::table('tickets')->find($t->id)->status);
        $this->actingAs($u)->post(route('account.support.close', $t->ref))->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($u)->post(route('account.support.reply', $t->ref), ['body' => 'again'])->assertSessionHasErrors('body');
        $this->asStaff($this->makeStaff(['content_author']))->get(route('admin.ops.tickets'))->assertForbidden();
    }

    // ------------------------------------------------------------------ privacy

    public function test_export_requires_recent_sign_in(): void
    {
        $u = $this->customer();
        $this->profile($u, 'Export Me');
        $this->actingAs($u)->post(route('account.privacy.export'))->assertRedirect(route('account.sign-in'));
        $this->assertGuest();

        $res = $this->actingAs($u)->withSession(['customer_reauth_at' => now()->timestamp])->post(route('account.privacy.export'));
        $res->assertOk()->assertHeader('Content-Disposition');
        $this->assertSame('Export Me', $res->json('birth_profiles.0.name'));
        $this->assertSame($u->email, $res->json('account.email'));
    }

    public function test_delete_blocked_by_pending_items_then_anonymises(): void
    {
        $u = $this->customer();
        $email = $u->email;
        $this->profile($u, 'Gone Soon');
        $order = $this->serviceOrder('puja-rituals', $u);
        $fresh = ['customer_reauth_at' => now()->timestamp];

        $this->actingAs($u)->withSession($fresh)->get(route('account.privacy'))->assertOk()->assertSee('A puja booking is still in progress.');
        $this->actingAs($u)->withSession($fresh)->post(route('account.privacy.delete'), ['confirm' => 'DELETE'])->assertSessionHasErrors('confirm');
        $this->assertSame($email, $u->fresh()->email);

        DB::table('puja_bookings')->where('order_id', $order->id)->update(['status' => 'completed']);
        $this->actingAs($u)->withSession($fresh)->post(route('account.privacy.delete'), ['confirm' => 'delete'])->assertSessionHasErrors('confirm');
        $this->actingAs($u)->withSession($fresh)->post(route('account.privacy.delete'), ['confirm' => 'DELETE'])->assertOk()->assertSee('deleted');
        $this->assertGuest();
        $gone = $u->fresh();
        $this->assertNull($gone->email);
        $this->assertSame('anonymized', $gone->status);
        $this->assertSame(0, DB::table('birth_profiles')->where('user_id', $u->id)->count());
        $this->assertSame(1, DB::table('orders')->where('id', $order->id)->count(), 'orders kept for tax');
        $this->assertDatabaseHas('privacy_requests', ['type' => 'delete', 'status' => 'completed', 'user_id' => null]);
    }

    public function test_wallet_balance_blocks_delete(): void
    {
        $this->hours();
        $u = $this->customer();
        [$b, $r] = app(Slots::class)->hold($u, $this->consultVariant(), '2026-11-03T04:30Z', null, null, 'wb');
        $this->capture($r['order']);
        app(Slots::class)->cancelByCustomer(DB::table('bookings')->find($b->id), $u);
        $this->assertContains('Your wallet still has money. Use it, or ask support for a refund, before deleting.', app(\App\Domain\Privacy\PrivacyService::class)->blockers($u));
    }

    // ------------------------------------------------------------------ rashifal & calendar

    public function test_rashifal_maker_checker_and_no_stale_entries(): void
    {
        $author = $this->makeStaff(['content_publisher']);
        $publisher = $this->makeStaff(['content_publisher']);
        $body = 'आज का दिन शुभ है। कार्यों में सफलता के योग हैं, परिवार का सहयोग मिलेगा।';
        $this->asStaff($this->makeStaff(['fulfilment']))->post(route('admin.ops.horoscope'), ['period' => 'daily', 'sign' => 0, 'starts_on' => '2026-11-02', 'locale' => 'hi', 'body' => $body])->assertForbidden();
        $this->asStaff($author)->post(route('admin.ops.horoscope'), ['period' => 'daily', 'sign' => 0, 'starts_on' => '2026-11-02', 'locale' => 'hi', 'body' => $body])->assertSessionHasNoErrors()->assertRedirect();
        $h = DB::table('horoscopes')->first();
        $this->get('/rashifal')->assertOk()->assertDontSee($body);

        $this->asStaff($author)->post(route('admin.ops.horoscope.decide', $h->id), ['decision' => 'publish'])->assertSessionHasErrors('decision');
        $this->asStaff($this->makeStaff(['content_author']))->post(route('admin.ops.horoscope.decide', $h->id), ['decision' => 'publish'])->assertForbidden();
        $this->asStaff($publisher)->post(route('admin.ops.horoscope.decide', $h->id), ['decision' => 'publish'])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/rashifal')->assertOk()->assertSee($body)->assertSee('मेष');

        Carbon::setTestNow('2026-11-03 03:00:00');
        $this->get('/rashifal')->assertOk()->assertDontSee($body);
    }

    public function test_vrat_calendar_renders(): void
    {
        $res = $this->get('/calendar?month=2026-11');
        $res->assertOk()->assertSee('November 2026');
        $this->assertMatchesRegularExpression('/Ekadashi|एकादशी/u', $res->getContent());
        $this->get('/calendar?month=2026-13')->assertSessionHasErrors('month');
        $this->get('/calendar?month=1700-01')->assertNotFound();
    }
}
