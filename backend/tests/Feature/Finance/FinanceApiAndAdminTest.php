<?php

namespace Tests\Feature\Finance;

use App\Domain\Ledger\Wallet;
use App\Domain\Orders\OrderService;
use App\Domain\Payments\PaymentService;
use App\Models\Offering;
use App\Models\Payment;
use App\Models\RechargePackage;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FinanceHelpers;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class FinanceApiAndAdminTest extends TestCase
{
    use FinanceHelpers, RefreshDatabase, StaffHelpers;

    private function token($u): string
    {
        return $u->createToken('Pixel', ['customer'])->plainTextToken;
    }

    public function test_api_wallet_recharge_confirm_and_history(): void
    {
        $this->configureRazorpay();
        $u = $this->customer();
        $pk = $this->package(25000, 2500);
        $t = $this->token($u);

        $this->withToken($t)->getJson('/api/v1/wallet')->assertOk()->assertJsonPath('data.available_minor', 0)->assertJsonPath('data.payments_available', true)->assertJsonPath('data.payments_mode', 'test');
        $this->withToken($t)->getJson('/api/v1/wallet/packages')->assertOk()->assertJsonPath('data.0.amount_minor', 25000)->assertJsonPath('data.0.bonus_minor', 2500);
        $this->withToken($t)->postJson('/api/v1/wallet/recharges', ['package_ref' => $pk->ref])->assertStatus(400)->assertJsonPath('error.code', 'idempotency_key_required');

        $res = $this->withToken($t)->withHeader('Idempotency-Key', 'app-key-0001')->postJson('/api/v1/wallet/recharges', ['package_ref' => $pk->ref])
            ->assertCreated()->assertJsonPath('data.status', 'created')->assertJsonPath('data.checkout.key', 'rzp_test_ABCDEF123456')->assertJsonPath('data.checkout.amount', 25000)->json('data');
        $this->assertStringNotContainsString($this->keySecret, json_encode($res));
        $again = $this->withToken($t)->withHeader('Idempotency-Key', 'app-key-0001')->postJson('/api/v1/wallet/recharges', ['package_ref' => $pk->ref])->json('data');
        $this->assertSame($res['ref'], $again['ref']);
        $this->assertSame(1, Payment::count());

        $order = $res['checkout']['order_id'];
        $pay = $this->rzPay($order);
        $this->withToken($t)->postJson("/api/v1/payments/{$res['ref']}/confirm", ['razorpay_payment_id' => $pay, 'razorpay_order_id' => $order, 'razorpay_signature' => str_repeat('0', 64)])
            ->assertStatus(422)->assertJsonPath('error.code', 'signature_invalid');
        $this->withToken($t)->postJson("/api/v1/payments/{$res['ref']}/confirm", ['razorpay_payment_id' => $pay, 'razorpay_order_id' => $order, 'razorpay_signature' => $this->checkoutSignature($order, $pay)])
            ->assertOk()->assertJsonPath('data.status', 'captured');
        $this->withToken($t)->getJson('/api/v1/wallet')->assertJsonPath('data.available_minor', 27500)->assertJsonPath('data.cash_minor', 25000);
        $this->withToken($t)->getJson('/api/v1/wallet/transactions')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.direction', 'credit');
        $this->withToken($t)->getJson('/api/v1/payments')->assertOk()->assertJsonPath('data.0.ref', $res['ref']);

        $other = $this->token($this->customer('o@example.com'));
        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson("/api/v1/payments/{$res['ref']}")->assertNotFound();
    }

    public function test_api_reports_payments_unavailable(): void
    {
        $u = $this->customer();
        $pk = $this->package();
        $this->withToken($this->token($u))->withHeader('Idempotency-Key', 'k-00000001')->postJson('/api/v1/wallet/recharges', ['package_ref' => $pk->ref])
            ->assertStatus(503)->assertJsonPath('error.code', 'payments_unavailable');
    }

    public function test_order_snapshot_wallet_payment_cancel_and_complete(): void
    {
        $this->seed(CatalogueSeeder::class);
        $u = $this->customer();
        $offering = Offering::where('status', 'active')->first();
        $v = $offering->variants()->first();
        $v->update(['price_minor' => 110000]);
        app(Wallet::class)->creditPayment($u->id, 99, 200000, 0);
        $svc = app(OrderService::class);

        $o = $svc->create($u, $v, 1, 'ord-1');
        $this->assertSame($o->id, $svc->create($u, $v, 1, 'ord-1')->id);
        $v->update(['price_minor' => 999900, 'name' => 'Renamed']);
        $item = $o->items()->first();
        $this->assertSame(110000, $item->unit_price_minor);
        $this->assertNotSame('Renamed', $item->variant_snapshot['name']);

        $svc->payFromWallet($o);
        $this->assertSame('paid', $o->refresh()->status);
        $this->assertSame(110000, app(Wallet::class)->balances($u)['reserved']);
        $svc->cancel($o, 'Customer cancelled');
        $this->assertSame(200000, app(Wallet::class)->balances($u)['cash']);

        $o2 = $svc->create($u, $v->refresh(), 1, 'ord-2');
        $this->expectException(\App\Domain\Ledger\InsufficientFunds::class);
        $svc->payFromWallet($o2); // 9,999 > 2,000
    }

    public function test_direct_order_payment_is_credited_then_reserved(): void
    {
        $this->seed(CatalogueSeeder::class);
        $this->configureRazorpay();
        $u = $this->customer();
        $v = Offering::where('status', 'active')->first()->variants()->first();
        $v->update(['price_minor' => 50000]);
        $o = app(OrderService::class)->create($u, $v);
        $p = app(PaymentService::class)->startOrderPayment($u, $o, 'po-1');
        $pay = $this->rzPay($p->provider_order_id);
        app(PaymentService::class)->confirmFromCheckout($p, $p->provider_order_id, $pay, $this->checkoutSignature($p->provider_order_id, $pay));
        $this->assertSame('paid', $o->refresh()->status);
        $bal = app(Wallet::class)->balances($u);
        $this->assertSame([0, 50000], [$bal['cash'], $bal['reserved']]);
        app(OrderService::class)->complete($o);
        $this->assertSame(0, app(Wallet::class)->balances($u)['reserved']);
        $this->assertSame(50000, app(\App\Domain\Ledger\Ledger::class)->balance('revenue'));
    }

    public function test_admin_finance_permissions_packages_and_live_mode(): void
    {
        $this->syncRbac();
        $this->configureRazorpay();
        $finance = $this->makeStaff(['finance']);
        $author = $this->makeStaff(['content_author']);
        $admin = $this->makeStaff(['administrator']);
        $owner = $this->makeStaff(['owner']);

        foreach (['/admin/finance', '/admin/finance/payments', '/admin/finance/refunds', '/admin/finance/disputes', '/admin/finance/packages'] as $url) {
            $this->asStaff($finance)->get($url)->assertOk();
            $this->asStaff($author)->get($url)->assertForbidden();
        }
        $this->actingAs($this->customer())->get('/admin/finance')->assertRedirect();

        // Packages need prices.manage (owner) + step-up.
        $this->asStaff($finance)->post('/admin/finance/packages', ['name' => 'Starter', 'amount' => '500', 'active' => '1'])->assertForbidden();
        $this->asStaff($owner, freshStepUp: false)->post('/admin/finance/packages', ['name' => 'Starter', 'amount' => '500', 'active' => '1'])->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->post('/admin/finance/packages', ['name' => 'Starter', 'amount' => '500', 'bonus' => '600', 'active' => '1'])->assertSessionHasErrors('bonus');
        $this->asStaff($owner)->post('/admin/finance/packages', ['name' => 'Starter', 'amount' => '1,000', 'bonus' => '50', 'active' => '1'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('recharge_packages', ['name' => 'Starter', 'amount_minor' => 100000, 'bonus_minor' => 5000, 'active' => 1]);

        // Razorpay setup page: providers.manage only; secrets never rendered.
        $this->asStaff($admin)->get('/admin/payments-setup')->assertForbidden();
        $this->asStaff($owner)->get('/admin/payments-setup')->assertOk()->assertSee('rzp_test_ABCDEF123456')->assertDontSee($this->keySecret)->assertDontSee($this->webhookSecret)->assertSee('/api/webhooks/razorpay');
        $this->asStaff($owner)->put('/admin/payments-setup', ['key_id' => 'bad_key'])->assertSessionHasErrors('key_id');
        $this->asStaff($owner)->post('/admin/payments-setup/test')->assertSessionHasNoErrors();

        // Live mode: owner-only, typed confirmation and limit.
        $this->asStaff($owner)->put('/admin/payments-setup/mode', ['mode' => 'live', 'live_limit' => '5000'])->assertSessionHasErrors('confirm');
        $this->asStaff($owner)->put('/admin/payments-setup/mode', ['mode' => 'live', 'live_limit' => '5000', 'confirm' => 'LIVE'])->assertSessionHasNoErrors();
        $this->assertSame('live', app(\App\Domain\Payments\RazorpayConfig::class)->mode());
        $this->assertSame(500000, app(\App\Domain\Payments\RazorpayConfig::class)->liveLimitMinor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payments.mode_changed']);
        $this->assertNotNull(RechargePackage::first());
    }
}
