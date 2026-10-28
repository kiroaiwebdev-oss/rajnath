<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\Ledger;
use App\Domain\Ledger\Wallet;
use App\Domain\Payments\PaymentService;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceHelpers;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class RefundDisputeTest extends TestCase
{
    use FinanceHelpers, RefreshDatabase, StaffHelpers;

    private function paid($u, int $amount = 50000, int $bonus = 0): Payment
    {
        $p = app(PaymentService::class)->startRecharge($u, $this->package($amount, $bonus), 'k'.uniqid());
        $pay = $this->rzPay($p->provider_order_id);
        app(PaymentService::class)->confirmFromCheckout($p, $p->provider_order_id, $pay, $this->checkoutSignature($p->provider_order_id, $pay));

        return $p->refresh();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncRbac();
        $this->configureRazorpay();
    }

    public function test_maker_checker_refund_processed_by_razorpay(): void
    {
        $u = $this->customer();
        $p = $this->paid($u, 50000, 5000);
        $support = $this->makeStaff(['support']);
        $finance = $this->makeStaff(['finance']);

        // Support can request but not approve; more than unspent cash is refused.
        $this->asStaff($support)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '600', 'reason' => 'Customer asked'])->assertSessionHasErrors('amount');
        $this->asStaff($support)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '200', 'reason' => 'Customer asked for partial refund'])->assertSessionHasNoErrors();
        $r = Refund::firstOrFail();
        $this->assertSame([20000, 'requested'], [$r->amount_minor, $r->status]);
        $this->asStaff($support)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'approve'])->assertForbidden();

        // Finance needs step-up.
        $this->asStaff($finance, freshStepUp: false)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'approve'])->assertRedirect(route('staff.step-up'));
        $this->assertSame('requested', $r->refresh()->status);

        $this->asStaff($finance)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame('processed', $r->status);
        $this->assertNotNull($r->provider_refund_id);
        $this->assertSame(20000, $p->refresh()->refunded_minor);
        $bal = app(Wallet::class)->balances($u);
        $this->assertSame([30000, 5000], [$bal['cash'], $bal['bonus']]);
        $this->assertSame(30000, app(Ledger::class)->balance('gateway_clearing'));
        $this->assertSame(0, app(Ledger::class)->balance('refunds_in_flight'));
        $this->assertTrue(app(Ledger::class)->verify()['ok']);

        // Duplicate refund webhook does nothing.
        $this->webhook(['event' => 'refund.processed', 'payload' => ['refund' => ['entity' => $this->rz['refunds'][$r->provider_refund_id]]]])->assertOk();
        $this->assertSame(30000, app(Wallet::class)->balances($u)['cash']);
        $this->asStaff($finance)->get('/admin/finance')->assertOk()->assertSee('Matches');
    }

    public function test_requester_cannot_approve_own_refund_unless_owner(): void
    {
        $u = $this->customer();
        $p = $this->paid($u);
        $finance = $this->makeStaff(['finance']);
        $this->asStaff($finance)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '100', 'reason' => 'Duplicate booking'])->assertSessionHasNoErrors();
        $r = Refund::firstOrFail();
        $this->asStaff($finance)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'approve'])->assertSessionHasErrors('decision');
        $this->assertSame('requested', $r->refresh()->status);

        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '50', 'reason' => 'Goodwill refund'])->assertSessionHasNoErrors();
        $own = Refund::latest('id')->first();
        $this->asStaff($owner)->post("/admin/finance/refunds/{$own->ref}/decide", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->assertSame('processed', $own->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund.approved', 'subject_ref' => $own->ref]);
    }

    public function test_rejected_and_failed_refunds_return_money(): void
    {
        $u = $this->customer();
        $p = $this->paid($u, 10000);
        $a = $this->makeStaff(['support']);
        $f = $this->makeStaff(['finance']);
        $this->asStaff($a)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '100', 'reason' => 'Not needed'])->assertSessionHasNoErrors();
        $r = Refund::first();
        $this->asStaff($f)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->asStaff($f)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'reject', 'note' => 'Service already delivered'])->assertSessionHasNoErrors();
        $this->assertSame('rejected', $r->refresh()->status);

        // Razorpay accepts the refund request but later reports failure.
        $this->rz['refund_status'] = 'pending';
        $this->asStaff($a)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '100', 'reason' => 'Try again'])->assertSessionHasNoErrors();
        $r2 = Refund::latest('id')->first();
        $this->asStaff($f)->post("/admin/finance/refunds/{$r2->ref}/decide", ['decision' => 'approve']);
        $this->assertSame('processing', $r2->refresh()->status);
        $this->assertSame(0, app(Wallet::class)->balances($u)['cash']);
        $this->rz['refunds'][$r2->provider_refund_id]['status'] = 'failed';
        $this->webhook(['event' => 'refund.failed', 'payload' => ['refund' => ['entity' => $this->rz['refunds'][$r2->provider_refund_id]]]])->assertOk();
        $this->assertSame('failed', $r2->refresh()->status);
        $this->assertSame(10000, app(Wallet::class)->balances($u)['cash']);
        $this->assertSame(0, app(Ledger::class)->balance('refunds_in_flight'));
        $this->assertTrue(app(Ledger::class)->verify()['ok']);
    }

    public function test_transient_refund_error_is_retried_without_double_refund(): void
    {
        $u = $this->customer();
        $p = $this->paid($u, 10000);
        $a = $this->makeStaff(['support']);
        $f = $this->makeStaff(['finance']);
        $this->asStaff($a)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '100', 'reason' => 'Customer request'])->assertSessionHasNoErrors();
        $r = Refund::first();
        $this->rzDown = true;
        $this->asStaff($f)->post("/admin/finance/refunds/{$r->ref}/decide", ['decision' => 'approve']);
        $this->assertSame('processing', $r->refresh()->status);
        $this->assertNull($r->provider_refund_id);
        $this->rzDown = false;
        $this->artisan('arj:payments-reconcile')->assertSuccessful();
        $this->artisan('arj:payments-reconcile')->assertSuccessful();
        $this->assertSame('processed', $r->refresh()->status);
        $this->assertCount(1, $this->rz['refunds']);
    }

    public function test_spent_money_cannot_be_refunded(): void
    {
        $u = $this->customer();
        $p = $this->paid($u, 10000);
        app(Wallet::class)->reserve($u->id, 8000, 'Booking', 'B1');
        $a = $this->makeStaff(['support']);
        $this->asStaff($a)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '50', 'reason' => 'Too much'])->assertSessionHasErrors('amount');
        $this->asStaff($a)->post("/admin/finance/payments/{$p->ref}/refund", ['amount' => '20', 'reason' => 'Remaining balance'])->assertSessionHasNoErrors();
    }

    public function test_dispute_holds_then_lost_and_won(): void
    {
        $u = $this->customer();
        $p = $this->paid($u, 10000);
        app(Wallet::class)->reserve($u->id, 4000, 'Booking', 'B1'); // ₹40 already spent/reserved
        $pay = $this->rz['payments'][$p->provider_payment_id];
        $dispute = ['id' => 'disp_1', 'amount' => 10000, 'reason_code' => 'chargeback', 'status' => 'open'];
        $this->webhook(['event' => 'payment.dispute.created', 'payload' => ['payment' => ['entity' => $pay], 'dispute' => ['entity' => $dispute]]])->assertOk();
        $bal = app(Wallet::class)->balances($u);
        $this->assertSame([0, 6000], [$bal['cash'], $bal['held']]);
        $this->assertDatabaseHas('reconciliation_findings', ['kind' => 'dispute.opened', 'severity' => 'critical']);

        $this->webhook(['event' => 'payment.dispute.lost', 'payload' => ['payment' => ['entity' => $pay], 'dispute' => ['entity' => ['status' => 'lost'] + $dispute]]])->assertOk();
        $this->assertSame(0, app(Wallet::class)->balances($u)['held']);
        $this->assertSame(4000, app(Ledger::class)->balance('chargeback_loss'));
        $this->assertSame(0, app(Ledger::class)->balance('gateway_clearing'));

        // A second, won dispute on another payment releases the hold.
        $p2 = $this->paid($u, 5000);
        $pay2 = $this->rz['payments'][$p2->provider_payment_id];
        $d2 = ['id' => 'disp_2', 'amount' => 5000, 'status' => 'open'];
        $this->webhook(['event' => 'payment.dispute.created', 'payload' => ['payment' => ['entity' => $pay2], 'dispute' => ['entity' => $d2]]])->assertOk();
        $this->webhook(['event' => 'payment.dispute.won', 'payload' => ['payment' => ['entity' => $pay2], 'dispute' => ['entity' => ['status' => 'won'] + $d2]]])->assertOk();
        $this->webhook(['event' => 'payment.dispute.won', 'payload' => ['payment' => ['entity' => $pay2], 'dispute' => ['entity' => ['status' => 'won'] + $d2]]])->assertOk();
        $bal = app(Wallet::class)->balances($u);
        $this->assertSame([5000, 0], [$bal['cash'], $bal['held']]);
        $this->assertTrue(app(Ledger::class)->verify()['ok']);
    }
}
