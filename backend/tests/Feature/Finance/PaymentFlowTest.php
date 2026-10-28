<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Wallet;
use App\Models\Payment;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FinanceHelpers;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use FinanceHelpers, RefreshDatabase;

    private function startRecharge($u, $pk, string $idem = '01JTESTIDEMPOTENCY0000000A'): Payment
    {
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => $pk->ref, 'idem' => $idem])->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    public function test_wallet_page_without_configuration_says_payments_unavailable(): void
    {
        $u = $this->customer();
        $this->package();
        $this->actingAs($u)->get('/account/wallet')->assertOk()->assertSee('Online payment is not available right now');
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => \App\Models\RechargePackage::first()->ref, 'idem' => 'x1'])->assertSessionHasErrors('package');
        $this->assertSame(0, Payment::count());
    }

    public function test_full_web_recharge_with_verified_checkout(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $pk = $this->package(50000, 5000);
        $this->actingAs($u)->get('/account/wallet')->assertOk()->assertSee('₹500')->assertSee('+ ₹50 bonus')->assertSee('Test mode');

        $p = $this->startRecharge($u, $pk);
        $this->assertSame('created', $p->status);
        $this->assertSame('test', $p->provider_mode);
        $this->assertSame(['package_ref' => $pk->ref, 'name' => $pk->name, 'amount_minor' => 50000, 'bonus_minor' => 5000], $p->snapshot);
        $this->assertSame(50000, $this->rz['orders'][$p->provider_order_id]['amount']);

        // Same idempotency key: no second payment / order.
        $this->startRecharge($u, $pk);
        $this->assertSame(1, Payment::count());

        $page = $this->actingAs($u)->get("/account/payments/{$p->ref}/checkout")->assertOk()->assertSee($p->provider_order_id)->assertSee('rzp_test_ABCDEF123456');
        $page->assertDontSee($this->keySecret)->assertDontSee($this->webhookSecret);

        $payId = $this->rzPay($p->provider_order_id);
        $sig = $this->checkoutSignature($p->provider_order_id, $payId);
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => $sig])
            ->assertRedirect("/account/payments/{$p->ref}");
        $p->refresh();
        $this->assertSame('captured', $p->status);
        $this->assertSame($payId, $p->provider_payment_id);
        $this->assertSame(['cash' => 50000, 'bonus' => 5000], array_intersect_key(app(Wallet::class)->balances($u), ['cash' => 0, 'bonus' => 0]));

        // Replayed confirmation and webhook do not credit twice.
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => $sig]);
        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$payId]]]])->assertOk()->assertJsonPath('result', 'applied');
        $this->assertSame(55000, app(Wallet::class)->balances($u)['available']);
        $this->assertSame(1, DB::table('ledger_transactions')->where('kind', 'recharge')->count());
        $this->assertTrue(app(Ledger::class)->verify()['ok']);

        $this->actingAs($u)->get("/account/payments/{$p->ref}")->assertOk()->assertSee('Payment confirmed');
        $this->actingAs($u)->get("/account/payments/{$p->ref}/receipt")->assertOk()->assertSee('Payment receipt')->assertSee($payId)->assertSee('not a tax invoice');
        $this->actingAs($u)->get('/account/wallet')->assertSee('₹550');
        $csv = $this->actingAs($u)->get('/account/wallet/statement.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('Wallet recharge', $csv);
        $this->assertStringContainsString('500.00', $csv);
    }

    public function test_forged_checkout_signature_is_rejected_and_not_credited(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->startRecharge($u, $this->package());
        $payId = $this->rzPay($p->provider_order_id);
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => str_repeat('a', 64)])
            ->assertSessionHasErrors('payment');
        $this->assertSame('created', $p->refresh()->status);
        $this->assertSame(0, app(Wallet::class)->balances($u)['available']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.bad_checkout_signature']);
    }

    public function test_amount_mismatch_marks_payment_ambiguous_with_critical_finding(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->startRecharge($u, $this->package(50000));
        $payId = $this->rzPay($p->provider_order_id, 'captured', 100);
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => $this->checkoutSignature($p->provider_order_id, $payId)]);
        $this->assertSame('ambiguous', $p->refresh()->status);
        $this->assertSame(0, app(Wallet::class)->balances($u)['available']);
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'payment.mismatch', 'severity' => 'critical', 'subject_ref' => $p->ref]);
    }

    public function test_authorized_payment_is_captured_server_side(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->startRecharge($u, $this->package(20000));
        $payId = $this->rzPay($p->provider_order_id, 'authorized');
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => $this->checkoutSignature($p->provider_order_id, $payId)]);
        $this->assertSame('captured', $p->refresh()->status);
        $this->assertContains('POST https://api.razorpay.com/v1/payments/'.$payId.'/capture', $this->rz['calls']);
    }

    public function test_razorpay_outage_after_valid_signature_is_finished_by_reconciler(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->startRecharge($u, $this->package(30000));
        $payId = $this->rzPay($p->provider_order_id);
        $this->rzDown = true;
        $this->actingAs($u)->post("/account/payments/{$p->ref}/confirm", ['razorpay_payment_id' => $payId, 'razorpay_order_id' => $p->provider_order_id, 'razorpay_signature' => $this->checkoutSignature($p->provider_order_id, $payId)]);
        $this->assertSame('pending', $p->refresh()->status);
        $this->actingAs($u)->get("/account/payments/{$p->ref}")->assertSee('confirming this payment');

        $this->rzDown = false;
        $this->travel(10)->minutes();
        $this->artisan('arj:payments-reconcile')->assertSuccessful();
        $this->assertSame('captured', $p->refresh()->status);
        $this->assertSame(30000, app(Wallet::class)->balances($u)['cash']);
    }

    public function test_lost_callback_is_recovered_by_reconciler_and_abandoned_orders_expire(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $paid = $this->startRecharge($u, $this->package(10000), 'idem-a');
        $this->rzPay($paid->provider_order_id); // customer paid but closed the browser
        $abandoned = $this->startRecharge($u, $this->package(20000), 'idem-b');

        $this->travel(10)->minutes();
        $this->artisan('arj:payments-reconcile')->assertSuccessful();
        $this->assertSame('captured', $paid->refresh()->status);
        $this->assertSame('created', $abandoned->refresh()->status);

        $this->travel(25)->hours();
        $this->artisan('arj:payments-reconcile')->assertSuccessful();
        $this->assertSame('cancelled', $abandoned->refresh()->status);
    }

    public function test_failed_payment_then_late_capture_is_credited_with_finding(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->startRecharge($u, $this->package(10000));
        $failId = $this->rzPay($p->provider_order_id, 'failed');
        $this->webhook(['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$failId]]]])->assertOk();
        $this->assertSame('failed', $p->refresh()->status);
        $this->actingAs($u)->get("/account/payments/{$p->ref}")->assertSee('Payment declined by bank')->assertSee('Try again');

        $okId = $this->rzPay($p->provider_order_id, 'captured');
        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$okId]]]])->assertOk();
        $this->assertSame('captured', $p->refresh()->status);
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'payment.late_capture', 'subject_ref' => $p->ref]);
    }

    public function test_live_mode_requires_owner_settings_and_enforces_limit(): void
    {
        $this->configureRazorpay();
        $s = app(SettingsRepository::class);
        $s->set('payments.mode', 'live');
        $u = $this->customer();
        $pk = $this->package(50000);
        // Test key in live mode: refused.
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => $pk->ref, 'idem' => 'l1'])->assertSessionHasErrors('package');
        $s->set('payments.razorpay.key_id', 'rzp_live_ABCDEF123456');
        $s->set('payments.live_limit_minor', 20000);
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => $pk->ref, 'idem' => 'l2'])->assertSessionHasErrors('package');
        $this->assertSame(0, Payment::count());
        $small = $this->package(10000);
        $this->actingAs($u)->post('/account/wallet/recharge', ['package' => $small->ref, 'idem' => 'l3'])->assertRedirect();
        $this->assertSame('live', Payment::first()->provider_mode);
    }

    public function test_customers_cannot_see_each_others_payments(): void
    {
        $this->configureRazorpay();
        $a = $this->customer('a@example.com');
        $b = $this->customer('b@example.com');
        $p = $this->startRecharge($a, $this->package());
        foreach (["/account/payments/{$p->ref}", "/account/payments/{$p->ref}/checkout", "/account/payments/{$p->ref}/receipt"] as $url) {
            $this->actingAs($b)->get($url)->assertNotFound();
        }
        $this->actingAs($b)->post("/account/payments/{$p->ref}/refresh")->assertNotFound();
        $this->app['auth']->forgetGuards();
        $this->get('/account/wallet')->assertRedirect();
    }
}
