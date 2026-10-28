<?php

namespace Tests\Feature\Payments;

use App\Domain\Ledger\InsufficientFunds;
use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Payments\Reconciler;
use App\Models\Offering;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private const SECRET = 'test_secret_abc';
    private const WEBHOOK = 'whsec_test_123';
    private int $orderSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('A', 14));
        $s->set('payments.razorpay.key_secret', self::SECRET, secret: true);
        $s->set('payments.razorpay.webhook_secret', self::WEBHOOK, secret: true);
        $this->fakeRazorpay([]);
    }

    private array $captures = [];
    private bool $refundFails = false;

    /** Fake Razorpay registered once; tests change $captures / $refundFails to alter responses. */
    private function fakeRazorpay(array $captured): void
    {
        $this->captures = $captured;
        static $registered = null;
        if ($registered === spl_object_id($this)) {
            return;
        }
        $registered = spl_object_id($this);
        Http::fake([
            'api.razorpay.com/v1/orders/*/payments' => function ($req) {
                preg_match('#orders/([^/]+)/payments#', $req->url(), $m);

                return Http::response(['items' => isset($this->captures[$m[1]]) ? [$this->captures[$m[1]]] : []]);
            },
            'api.razorpay.com/v1/orders' => fn ($req) => Http::response(['id' => 'order_T'.(++$this->orderSeq), 'amount' => $req['amount'], 'status' => 'created'], 200),
            'api.razorpay.com/v1/payments/*/refund' => fn () => $this->refundFails
                ? Http::response(['error' => ['description' => 'Insufficient balance']], 400)
                : Http::response(['id' => 'rfnd_T1', 'status' => 'processed'], 200),
        ]);
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Pay', 'email' => 'pay'.uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function pricedVariant(int $minor = 210000)
    {
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->first();
        // a non-slot mode: slot-bookable consultations go through BookingController (tested in Ops)
        $v->update(['price_minor' => $minor, 'mode' => 'report']);

        return $v->fresh();
    }

    private function webhook(array $event, ?string $id = null)
    {
        $raw = json_encode($event);

        return $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, self::WEBHOOK), 'HTTP_X_RAZORPAY_EVENT_ID' => $id ?? uniqid('evt_')], $raw);
    }

    private function captured(string $orderId, int $amount, string $pid = 'pay_T1'): array
    {
        return ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => $pid, 'order_id' => $orderId, 'amount' => $amount, 'status' => 'captured', 'method' => 'upi']]]];
    }

    public function test_service_purchase_end_to_end_via_browser_callback(): void
    {
        $u = $this->customer();
        $v = $this->pricedVariant();
        $this->actingAs($u)->post('/checkout', ['variant' => $v->ref, 'key' => 'k1'])->assertRedirect();
        $order = DB::table('orders')->first();
        $this->assertSame('pending_payment', $order->status);
        $snap = json_decode($order->snapshot, true);
        $this->assertSame(210000, $snap['price_minor']);
        $this->get("/checkout/{$order->ref}")->assertOk()->assertSee('checkout.razorpay.com')->assertSee('order_T1');

        // Same idempotency key → same order, no second Razorpay order.
        $this->post('/checkout', ['variant' => $v->ref, 'key' => 'k1']);
        $this->assertSame(1, DB::table('orders')->count());

        // Price change after purchase does not affect the order snapshot.
        $v->update(['price_minor' => 999900]);

        $this->fakeRazorpay(['order_T1' => ['id' => 'pay_T1', 'amount' => 210000, 'status' => 'captured', 'method' => 'upi']]);
        $sig = hash_hmac('sha256', 'order_T1|pay_T1', self::SECRET);
        $this->post("/checkout/{$order->ref}/verify", ['razorpay_order_id' => 'order_T1', 'razorpay_payment_id' => 'pay_T1', 'razorpay_signature' => $sig])->assertRedirect("/account/orders/{$order->ref}");
        $this->assertSame('paid', DB::table('orders')->value('status'));
        $this->assertSame(210000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
        $this->get("/account/orders/{$order->ref}")->assertSee('₹2,100')->assertSee('pay_T1');

        // Webhook arriving later for the same payment is a harmless duplicate.
        $this->webhook($this->captured('order_T1', 210000))->assertOk()->assertJson(['status' => 'duplicate']);
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_bad_signature_is_rejected_and_nothing_is_marked_paid(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'k2']);
        $o = DB::table('orders')->first();
        $this->post("/checkout/{$o->ref}/verify", ['razorpay_order_id' => 'order_T1', 'razorpay_payment_id' => 'pay_X', 'razorpay_signature' => str_repeat('0', 64)])->assertSessionHasErrors('payment');
        $this->assertSame('pending_payment', DB::table('orders')->value('status'));
    }

    public function test_webhook_signature_replay_and_amount_mismatch(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'k3']);
        $raw = json_encode($this->captured('order_T1', 210000));
        $this->call('POST', '/webhooks/razorpay', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'bad'], $raw)->assertStatus(400);
        $this->assertSame('pending_payment', DB::table('orders')->value('status'));

        $this->webhook($this->captured('order_T1', 100), 'evt_1')->assertJson(['status' => 'amount_mismatch']);
        $this->assertSame('pending_payment', DB::table('orders')->value('status'));
        $this->assertSame(1, DB::table('reconciliation_findings')->where('check', 'amount_mismatch')->count());

        $this->webhook($this->captured('order_T1', 210000, 'pay_T2'), 'evt_2')->assertJson(['status' => 'applied']);
        $this->webhook($this->captured('order_T1', 210000, 'pay_T2'), 'evt_2')->assertJson(['status' => 'duplicate']); // replay of same event id
        $this->assertSame(1, DB::table('ledger_transactions')->where('kind', 'service_purchase')->count());
    }

    public function test_wallet_recharge_with_bonus_then_spend_with_wallet(): void
    {
        DB::table('recharge_packages')->insert(['amount_minor' => 100000, 'bonus_minor' => 10000, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $u = $this->customer();
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => DB::table('recharge_packages')->value('id'), 'key' => 'r1'])->assertRedirect();
        $this->webhook($this->captured('order_T1', 100000, 'pay_R'))->assertJson(['status' => 'applied']);
        $this->assertSame(110000, app(Ledger::class)->walletBalance($u->id));
        $this->get('/account/wallet')->assertSee('₹1,100');

        // Service of ₹2,100 with wallet: ₹1,100 from wallet, ₹1,000 online.
        $this->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'use_wallet' => '1', 'key' => 's1']);
        $o = DB::table('orders')->where('kind', 'service')->first();
        $this->assertSame([110000, 100000], [(int) $o->wallet_applied_minor, (int) $o->payable_minor]);
        $this->webhook($this->captured('order_T2', 100000, 'pay_S'))->assertJson(['status' => 'applied']);
        $this->assertSame(0, app(Ledger::class)->walletBalance($u->id));
        $this->assertSame(210000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_fully_wallet_paid_order_needs_no_gateway(): void
    {
        $u = $this->customer();
        $l = app(Ledger::class);
        $l->post('test_credit', 'seed-'.$u->id, [[$l->account('bonus_expense', 'expense'), 'debit', 300000], [$l->wallet($u->id), 'credit', 300000]]);
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'use_wallet' => '1', 'key' => 'w1'])->assertRedirect();
        $this->assertSame('paid', DB::table('orders')->value('status'));
        $this->assertSame(90000, $l->walletBalance($u->id));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v1/orders'));
    }

    public function test_ledger_rejects_unbalanced_negative_wallet_and_is_idempotent(): void
    {
        $l = app(Ledger::class);
        $a = $l->account('revenue', 'revenue');
        $w = $l->wallet(1);
        try {
            $l->post('x', 'k-unbal', [[$a, 'debit', 100], [$w, 'credit', 99]]);
            $this->fail('unbalanced accepted');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(InsufficientFunds::class);
        try {
            $l->post('x', 'k-neg', [[$w, 'debit', 100], [$a, 'credit', 100]]);
        } finally {
            $this->assertSame(0, DB::table('ledger_transactions')->count());
            $first = $l->post('x', 'k-ok', [[$l->account('bonus_expense', 'expense'), 'debit', 500], [$w, 'credit', 500]]);
            $again = $l->post('x', 'k-ok', [[$l->account('bonus_expense', 'expense'), 'debit', 500], [$w, 'credit', 500]]);
            $this->assertTrue($first['created']);
            $this->assertFalse($again['created']);
            $this->assertSame(500, $l->walletBalance(1));
        }
    }

    public function test_refund_maker_checker_to_wallet_and_source(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'rf']);
        $o = DB::table('orders')->first();
        $this->webhook($this->captured('order_T1', 210000));

        $maker = $this->makeStaff(['finance']);
        $checker = $this->makeStaff(['finance']);
        $author = $this->makeStaff(['content_author']);
        $this->asStaff($author)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '500', 'destination' => 'wallet', 'reason' => 'x'])->assertForbidden();
        $this->asStaff($maker)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '5000', 'destination' => 'wallet', 'reason' => 'too much'])->assertSessionHasErrors('amount');
        $this->asStaff($maker)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '500', 'destination' => 'wallet', 'reason' => 'Late start'])->assertSessionHasNoErrors();
        $r1 = DB::table('refunds')->first();
        $this->post("/admin/finance/refunds/{$r1->ref}/decision", ['decision' => 'approve'])->assertSessionHasErrors('refund'); // same person
        $this->asStaff($checker)->post("/admin/finance/refunds/{$r1->ref}/decision", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame(50000, app(Ledger::class)->walletBalance($u->id));
        $this->assertSame('partially_refunded', DB::table('orders')->value('status'));

        $this->asStaff($maker)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '1600', 'destination' => 'source', 'reason' => 'Cancelled'])->assertSessionHasNoErrors();
        $r2 = DB::table('refunds')->orderByDesc('id')->first();
        $this->asStaff($checker)->post("/admin/finance/refunds/{$r2->ref}/decision", ['decision' => 'approve']);
        $this->assertSame('processed', DB::table('refunds')->where('id', $r2->id)->value('status'));
        $this->assertSame('refunded', DB::table('orders')->value('status'));
        $this->assertSame(0, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/payments/pay_T1/refund') && $r['amount'] === 160000);
        $this->asStaff($maker)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '1', 'destination' => 'wallet', 'reason' => 'x'])->assertSessionHasErrors('amount');
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_failed_gateway_refund_releases_hold(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'ff']);
        $o = DB::table('orders')->first();
        $this->webhook($this->captured('order_T1', 210000));
        $this->refundFails = true;
        $maker = $this->makeStaff(['finance']);
        $checker = $this->makeStaff(['owner']);
        $this->asStaff($maker)->post("/admin/finance/orders/{$o->ref}/refunds", ['amount' => '2100', 'destination' => 'source', 'reason' => 'x']);
        $r = DB::table('refunds')->first();
        $this->asStaff($checker)->post("/admin/finance/refunds/{$r->ref}/decision", ['decision' => 'approve']);
        $this->assertSame('failed', DB::table('refunds')->value('status'));
        $this->assertSame('paid', DB::table('orders')->value('status'));
        $this->assertSame(210000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
        $this->assertSame(0, (int) DB::table('ledger_accounts')->where('code', 'refunds_payable')->value('balance_minor'));
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_reconciler_applies_missed_capture_and_cancels_stale(): void
    {
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'a']);
        $this->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'b']);
        $this->travel(2)->days();
        $this->fakeRazorpay(['order_T1' => ['id' => 'pay_L', 'amount' => 210000, 'status' => 'captured', 'method' => 'card']]);
        $s = app(Reconciler::class)->run();
        $this->assertSame(['checked' => 2, 'applied' => 1, 'cancelled' => 1, 'ledger_problems' => 0], $s);
        $this->assertSame(['paid', 'cancelled'], DB::table('orders')->orderBy('id')->pluck('status')->all());
    }

    public function test_ledger_tampering_is_detected(): void
    {
        $l = app(Ledger::class);
        $l->post('x', 'k', [[$l->account('bonus_expense', 'expense'), 'debit', 700], [$l->wallet(5), 'credit', 700]]);
        DB::table('ledger_accounts')->where('code', 'customer_wallet:5')->update(['balance_minor' => 99999]);
        $this->assertNotEmpty($l->verify());
    }

    public function test_live_keys_blocked_until_owner_enables(): void
    {
        app(SettingsRepository::class)->set('payments.razorpay.key_id', 'rzp_live_'.str_repeat('B', 14));
        $u = $this->customer();
        $this->actingAs($u)->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'live'])->assertSessionHasErrors('variant');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/v1/orders'));
    }

    public function test_unpriced_or_draft_variants_cannot_be_bought_and_other_users_orders_hidden(): void
    {
        $u = $this->customer();
        $draft = Offering::where('slug', 'career-report')->first()->variants()->first();
        $draft->update(['price_minor' => 50000]);
        $this->actingAs($u)->post('/checkout', ['variant' => $draft->ref, 'key' => 'd'])->assertSessionHasErrors('variant');
        $this->post('/checkout', ['variant' => Offering::where('slug', 'numerology')->first()->variants()->first()->ref, 'key' => 'n'])->assertSessionHasErrors('variant');

        $this->post('/checkout', ['variant' => $this->pricedVariant()->ref, 'key' => 'mine']);
        $o = DB::table('orders')->first();
        $other = $this->customer();
        $this->actingAs($other)->get("/checkout/{$o->ref}")->assertNotFound();
        $this->get("/account/orders/{$o->ref}")->assertNotFound();
    }

    public function test_payment_settings_owner_only_with_step_up(): void
    {
        $this->asStaff($this->makeStaff(['finance']))->get('/admin/payments')->assertForbidden();
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner, false)->put('/admin/payments', ['key_id' => 'rzp_test_ABCDEFGHIJ'])->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->put('/admin/payments', ['key_id' => 'rzp_test_ABCDEFGHIJ', 'key_secret' => 'new-secret'])->assertSessionHasNoErrors();
        $this->assertSame('new-secret', app(SettingsRepository::class)->get('payments.razorpay.key_secret'));
        $this->get('/admin/payments')->assertDontSee('new-secret');
    }

    public function test_dispute_webhook_raises_a_finance_finding(): void
    {
        $this->webhook(['event' => 'payment.dispute.created', 'payload' => ['payment' => ['entity' => ['id' => 'pay_D1', 'order_id' => 'order_X', 'amount' => 210000, 'status' => 'captured']],
            'dispute' => ['entity' => ['id' => 'disp_1', 'amount' => 210000, 'phase' => 'chargeback', 'reason_code' => 'fraud', 'respond_by' => 1798761600]]]], 'evt_disp_1')
            ->assertOk()->assertJsonPath('status', 'dispute_recorded');
        $f = DB::table('reconciliation_findings')->where('check', 'dispute')->first();
        $this->assertSame('critical', $f->severity);
        $this->assertStringContainsString('disp_1', $f->detail);
        $this->assertStringContainsString('₹2,100.00', $f->detail);
    }
}
