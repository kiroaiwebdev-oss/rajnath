<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\Wallet;
use App\Domain\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\FinanceHelpers;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use FinanceHelpers, RefreshDatabase;

    private function payment($u, int $amount = 10000)
    {
        return app(PaymentService::class)->startRecharge($u, $this->package($amount), 'k'.uniqid());
    }

    public function test_bad_or_missing_signature_is_rejected(): void
    {
        $this->configureRazorpay();
        $this->webhook(['event' => 'payment.captured', 'payload' => []], 'evt_1', 'wrong-secret')->assertStatus(400)->assertJsonPath('result', 'invalid_signature');
        $this->postJson('/api/webhooks/razorpay', ['event' => 'payment.captured'])->assertStatus(400);
        $this->assertSame(0, DB::table('payment_events')->count());
    }

    public function test_replayed_event_is_acknowledged_once(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->payment($u);
        $pay = $this->rzPay($p->provider_order_id);
        $body = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$pay]]]];
        $this->webhook($body, 'evt_same')->assertOk()->assertJsonPath('result', 'applied');
        $this->webhook($body, 'evt_same')->assertOk()->assertJsonPath('result', 'duplicate');
        $this->assertSame(1, DB::table('payment_events')->count());
        $this->assertSame(10000, app(Wallet::class)->balances($u)['cash']);
    }

    public function test_webhook_claiming_capture_is_verified_against_api(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->payment($u);
        $pay = $this->rzPay($p->provider_order_id, 'failed');
        // Forged body says captured, but the API says failed.
        $forged = ['id' => $pay, 'order_id' => $p->provider_order_id, 'amount' => 10000, 'currency' => 'INR', 'status' => 'captured'];
        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $forged]]])->assertOk();
        $this->assertSame('failed', $p->refresh()->status);
        $this->assertSame(0, app(Wallet::class)->balances($u)['available']);
    }

    public function test_out_of_order_failed_after_captured_does_not_undo_capture(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->payment($u);
        $ok = $this->rzPay($p->provider_order_id);
        $this->webhook(['event' => 'order.paid', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$ok]]]])->assertOk();
        $failed = ['id' => 'pay_old', 'order_id' => $p->provider_order_id, 'status' => 'failed', 'error_description' => 'old attempt'];
        $this->webhook(['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => $failed]]])->assertOk();
        $this->assertSame('captured', $p->refresh()->status);
    }

    public function test_unknown_payment_raises_finding(): void
    {
        $this->configureRazorpay();
        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_x', 'order_id' => 'order_nope']]]])
            ->assertOk()->assertJsonPath('result', 'unknown_payment');
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'webhook.unknown_payment']);
    }

    public function test_processing_error_lets_razorpay_retry(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $p = $this->payment($u);
        $pay = $this->rzPay($p->provider_order_id);
        $this->rzDown = true; // re-fetch fails
        $body = ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => $this->rz['payments'][$pay]]]];
        $this->webhook($body, 'evt_retry')->assertStatus(500);
        $this->assertSame(0, DB::table('payment_events')->count());
        $this->rzDown = false;
        $this->webhook($body, 'evt_retry')->assertOk()->assertJsonPath('result', 'applied');
        $this->assertSame('captured', $p->refresh()->status);
    }
}
