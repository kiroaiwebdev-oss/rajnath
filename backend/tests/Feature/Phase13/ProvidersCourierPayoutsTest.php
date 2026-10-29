<?php

namespace Tests\Feature\Phase13;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Practitioners\Payouts;
use App\Domain\Practitioners\RazorpayX;
use App\Domain\Store\Shiprocket;
use App\Domain\Store\Store;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Shiprocket courier booking and tracking; RazorpayX payouts and their webhook. */
class ProvidersCourierPayoutsTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private int $seq = 0;

    private array $calls = [];

    /** RazorpayX create-payout reply: [status, body] */
    private array $rzx = [200, ['id' => 'pout_1', 'status' => 'processing']];

    private string $track = 'IN TRANSIT';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('X', 14));
        $s->set('payments.razorpay.key_secret', 'sec', secret: true);
        Mail::fake();
        Http::fake(function (Request $r) {
            $this->calls[] = $r;
            $u = $r->url();

            return match (true) {
                str_ends_with($u, '/v1/orders') && str_contains($u, 'razorpay') => Http::response(['id' => 'order_S'.(++$this->seq), 'amount' => $r['amount']]),
                str_contains($u, 'razorpay.com/v1/payouts/') => Http::response(['id' => 'pout_1', 'status' => 'processed', 'utr' => 'POLLUTR1']),
                str_contains($u, 'razorpay.com/v1/payouts') => Http::response($this->rzx[1], $this->rzx[0]),
                str_ends_with($u, '/auth/login') => Http::response(['token' => 'sr-token']),
                str_contains($u, '/orders/create/adhoc') => Http::response(['order_id' => 9000 + (++$this->seq), 'shipment_id' => 7000 + $this->seq, 'status' => 'NEW']),
                str_contains($u, '/courier/assign/awb') => Http::response(['awb_assign_status' => 1, 'response' => ['data' => ['awb_code' => $this->seq === 2 ? 'AWB123456' : 'AWB'.(900000 + $this->seq), 'courier_name' => 'Delhivery']]]),
                str_contains($u, '/courier/generate/pickup') => Http::response(['pickup_status' => 1]),
                str_contains($u, '/courier/generate/label') => Http::response(['label_created' => 1, 'label_url' => 'https://kr-shipmultichannel.s3.amazonaws.com/label.pdf']),
                str_contains($u, '/courier/track/awb/') => Http::response(['tracking_data' => ['shipment_track' => [['current_status' => $this->track]]]]),
                default => Http::response(['error' => 'unexpected '.$u], 500),
            };
        });
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Asha', 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function paidGemOrder(): object
    {
        $ref = (string) Str::ulid();
        DB::table('products')->insert(['ref' => $ref, 'slug' => 'ruby-'.$ref, 'category' => 'gemstone', 'name' => 'Ruby', 'summary' => 'Burma', 'price_minor' => 500000,
            'stock' => 2, 'max_per_order' => 2, 'active' => true, 'ship_weight_g' => 150, 'created_at' => now(), 'updated_at' => now()]);
        $o = app(Store::class)->buy($this->customer(), $ref, 1, ['name' => 'Asha Devi', 'phone' => '+91 91234 56789', 'line1' => 'House 1', 'line2' => null, 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001'], 'g'.$ref)['order'];
        $p = DB::table('payments')->where('order_id', $o->id)->first();
        app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$o->id, (int) $p->amount_minor, 'upi');

        return DB::table('orders')->find($o->id);
    }

    private function configureShiprocket(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('courier.shiprocket.email', 'api@example.com');
        $s->set('courier.shiprocket.password', 'pw', secret: true);
        $s->set('courier.shiprocket.pickup_location', 'Primary');
        $s->set('courier.shiprocket.webhook_token', 'tok_'.str_repeat('a', 20), secret: true);
    }

    public function test_shiprocket_booking_tracking_and_webhook(): void
    {
        $o = $this->paidGemOrder();
        $staff = $this->makeStaff(['fulfilment']);
        $this->asStaff($staff)->get(route('admin.store.order', $o->ref))->assertOk()->assertSee('Shiprocket is not set up');

        $this->configureShiprocket();
        $this->asStaff($staff)->get(route('admin.store.order', $o->ref))->assertSee('Book courier and pickup');
        $this->asStaff($staff)->post(route('admin.store.courier', $o->ref))->assertSessionHasNoErrors()->assertRedirect();
        $s = DB::table('shipments')->where('order_id', $o->id)->first();
        $this->assertSame(['dispatched', 'Delhivery', 'AWB123456', 'shiprocket', (string) (7000 + $this->seq), 'PICKUP SCHEDULED'], [$s->status, $s->courier, $s->awb, $s->provider, $s->provider_shipment_id, $s->provider_status]);
        $this->assertSame('https://shiprocket.co/tracking/AWB123456', $s->tracking_url);
        $create = collect($this->calls)->first(fn ($r) => str_contains($r->url(), 'create/adhoc'));
        $this->assertSame(['Primary', 'Prepaid', '9123456789', 0.15, $o->ref], [$create['pickup_location'], $create['payment_method'], $create['billing_phone'], $create['weight'], $create['order_id']]);
        $this->assertSame('Bearer sr-token', collect($this->calls)->first(fn ($r) => str_contains($r->url(), 'assign/awb'))->header('Authorization')[0]);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $o->user_id, 'title' => 'Your order is on its way']);
        // booking again is refused
        $this->asStaff($staff)->post(route('admin.store.courier', $o->ref))->assertSessionHasErrors('courier');

        // webhook: wrong token refused; status updates applied once
        $hook = fn (array $body, string $token) => $this->call('POST', '/webhooks/courier-tracking', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_API_KEY' => $token], json_encode($body));
        $hook(['awb' => 'AWB123456', 'current_status' => 'DELIVERED'], 'wrong')->assertStatus(401);
        $hook(['awb' => 'AWB123456', 'current_status' => 'OUT FOR DELIVERY'], 'tok_'.str_repeat('a', 20))->assertOk()->assertJson(['status' => 'dispatched']);
        $hook(['awb' => 'AWB123456', 'current_status' => 'DELIVERED'], 'tok_'.str_repeat('a', 20))->assertOk()->assertJson(['status' => 'delivered']);
        $hook(['awb' => 'AWB123456', 'current_status' => 'DELIVERED'], 'tok_'.str_repeat('a', 20))->assertOk()->assertJson(['status' => 'duplicate']);
        $hook(['awb' => 'NOPE', 'current_status' => 'DELIVERED', 'x' => 1], 'tok_'.str_repeat('a', 20))->assertOk()->assertJson(['status' => 'unknown_awb']);
        $this->assertSame('delivered', DB::table('shipments')->find($s->id)->status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $o->user_id, 'title' => 'Delivered']);

        // polling fallback marks a returned (RTO) parcel
        $o2 = $this->paidGemOrder();
        DB::table('shipments')->where('order_id', $o2->id)->update(['provider_shipment_id' => null]);
        app(Shiprocket::class)->book($o2, $staff);
        $this->track = 'RTO DELIVERED';
        DB::table('shipments')->where('order_id', $o2->id)->update(['synced_at' => now()->subHours(2)]);
        $this->assertSame(1, app(Shiprocket::class)->sync());
        $this->assertSame('returned', DB::table('shipments')->where('order_id', $o2->id)->value('status'));
    }

    private function approvedPayout(string $method = 'bank'): array
    {
        $pu = $this->makeStaff(['practitioner']);
        $details = $method === 'upi' ? ['method' => 'upi', 'upi' => 'pandit@okbank'] : ['method' => 'bank', 'account_name' => 'Pt Sharma', 'account_number' => '123456789012', 'ifsc' => 'HDFC0000053'];
        DB::table('practitioners')->insert(['ref' => $ref = (string) Str::ulid(), 'user_id' => $pu->id, 'display_name' => 'Pt. Sharma', 'status' => 'approved', 'tier' => 'team',
            'payout_details' => Crypt::encryptString(json_encode($details)), 'created_at' => now(), 'updated_at' => now()]);
        $p = DB::table('practitioners')->where('ref', $ref)->first();
        $ledger = app(Ledger::class);
        $ledger->post('earning', 'earning:t'.$p->id, [[$ledger->account('practitioner_expense', 'expense'), 'debit', 60000], [$ledger->account('practitioner_payable:'.$p->id, 'liability'), 'credit', 60000]], 'test', 'practitioner', $p->id);
        DB::table('practitioner_earnings')->insert(['practitioner_id' => $p->id, 'booking_id' => $p->id, 'order_id' => 1, 'gross_minor' => 100000, 'share_bps' => 6000, 'amount_minor' => 60000,
            'status' => 'available', 'available_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $a = $this->makeStaff(['finance', 'administrator']);
        $b = $this->makeStaff(['finance']);
        $po = app(Payouts::class)->propose($p, $a);
        app(Payouts::class)->approve($po, $b);

        return [DB::table('payouts')->find($po->id), $p, $a];
    }

    private function rzxWebhook(array $entity, string $event, string $secret = 'rzx_secret')
    {
        $raw = json_encode(['event' => $event, 'payload' => ['payout' => ['entity' => $entity]]]);

        return $this->call('POST', '/webhooks/razorpayx', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $raw, $secret), 'HTTP_X_RAZORPAY_EVENT_ID' => uniqid('evt_')], $raw);
    }

    public function test_razorpayx_send_webhook_and_failures(): void
    {
        [$po, $p, $finance] = $this->approvedPayout();
        $this->asStaff($finance)->get(route('admin.practitioners.show', $p->ref))->assertDontSee('Send via RazorpayX');
        $s = app(SettingsRepository::class);
        $s->set('payouts.razorpayx.account_number', '7878780080316316');
        $s->set('payouts.razorpayx.webhook_secret', 'rzx_secret', secret: true);
        $this->asStaff($finance)->get(route('admin.practitioners.show', $p->ref))->assertSee('Send via RazorpayX');

        $this->asStaff($finance, freshStepUp: false)->post(route('admin.payouts.action', $po->ref), ['action' => 'send'])->assertRedirect(route('staff.step-up'));
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'send'])->assertSessionHasNoErrors();
        $req = collect($this->calls)->last(fn ($r) => str_ends_with($r->url(), '/v1/payouts'));
        $this->assertSame($po->ref, $req->header('X-Payout-Idempotency')[0]);
        $this->assertSame(['7878780080316316', 60000, 'IMPS', 'bank_account', 'HDFC0000053', 'Pt. Sharma'],
            [$req['account_number'], $req['amount'], $req['mode'], $req['fund_account']['account_type'], $req['fund_account']['bank_account']['ifsc'], $req['fund_account']['contact']['name']]);
        $po = DB::table('payouts')->find($po->id);
        $this->assertSame(['processing', 'razorpayx', 'pout_1'], [$po->status, $po->provider, $po->provider_payout_id]);
        // cannot be marked paid by hand or cancelled while RazorpayX is processing it
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'paid', 'utr' => 'MANUAL1234'])->assertSessionHasErrors('utr');
        $this->asStaff($finance)->post(route('admin.payouts.action', $po->ref), ['action' => 'cancel'])->assertSessionHasErrors('payout');

        $this->rzxWebhook(['id' => 'pout_1', 'status' => 'processed', 'utr' => 'HDFCN0001'], 'payout.processed', 'wrong')->assertStatus(400);
        $this->rzxWebhook(['id' => 'pout_1', 'status' => 'processed', 'utr' => 'HDFCN0001'], 'payout.processed')->assertOk()->assertJson(['status' => 'paid']);
        $po = DB::table('payouts')->find($po->id);
        $this->assertSame(['paid', 'HDFCN0001', null], [$po->status, $po->utr, $po->paid_by]);
        $this->assertSame(0, (int) DB::table('ledger_accounts')->where('code', 'practitioner_payable:'.$p->id)->value('balance_minor'));
        $this->assertSame('paid', DB::table('practitioner_earnings')->value('status'));
        $this->assertSame([], app(Ledger::class)->verify());

        // a failed payout returns to approved with a finding; UPI details use UPI mode
        [$po2, $p2, $f2] = $this->approvedPayout('upi');
        $this->rzx = [200, ['id' => 'pout_2', 'status' => 'queued']];
        app(RazorpayX::class)->send($po2, $f2);
        $this->assertSame('UPI', collect($this->calls)->last(fn ($r) => str_ends_with($r->url(), '/v1/payouts'))['mode']);
        $this->rzxWebhook(['id' => 'pout_2', 'status' => 'reversed', 'status_details' => ['description' => 'Beneficiary bank down']], 'payout.reversed')->assertJson(['status' => 'returned']);
        $po2 = DB::table('payouts')->find($po2->id);
        $this->assertSame(['approved', null], [$po2->status, $po2->provider_payout_id]);
        $this->assertStringContainsString('Beneficiary bank down', $po2->provider_error);
        $this->assertDatabaseHas('reconciliation_findings', ['check' => 'payout_reversed', 'subject' => 'payout:'.$po2->ref]);

        // refused by RazorpayX: error shown, payout stays approved
        $this->rzx = [400, ['error' => ['description' => 'Insufficient balance in the account']]];
        $this->asStaff($f2)->post(route('admin.payouts.action', $po2->ref), ['action' => 'send'])->assertSessionHasErrors('payout');
        $this->assertSame('approved', DB::table('payouts')->find($po2->id)->status);

        // polling settles a processing payout
        $this->rzx = [200, ['id' => 'pout_3', 'status' => 'processing']];
        app(RazorpayX::class)->send(DB::table('payouts')->find($po2->id), $f2);
        $this->assertSame(1, app(RazorpayX::class)->sync());
        $this->assertSame(['paid', 'POLLUTR1'], [DB::table('payouts')->find($po2->id)->status, DB::table('payouts')->find($po2->id)->utr]);
    }
}
