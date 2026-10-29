<?php

namespace Tests\Feature\Phase13;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Payments\Invoices;
use App\Domain\Payments\Refunds;
use App\Domain\Store\Store;
use App\Models\Offering;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

/** Coupons (FI-13), GST tax invoices and credit notes (FI-08), PDF downloads. */
class CouponsAndInvoicesTest extends TestCase
{
    use RefreshDatabase, StaffHelpers;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogueSeeder::class);
        $this->syncRbac();
        $s = app(SettingsRepository::class);
        $s->set('payments.razorpay.key_id', 'rzp_test_'.str_repeat('C', 14));
        $s->set('payments.razorpay.key_secret', 'sec', secret: true);
        Mail::fake();
        Http::fake([
            'api.razorpay.com/v1/orders' => fn ($r) => Http::response(['id' => 'order_C'.(++$this->seq), 'amount' => $r['amount']]),
            'api.razorpay.com/v1/payments/*/refund' => Http::response(['id' => 'rfnd_C1', 'status' => 'processed']),
        ]);
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Asha', 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function variant(int $minor = 100000)
    {
        $v = Offering::where('slug', 'janma-kundali-reading')->first()->variants()->first();
        $v->update(['price_minor' => $minor, 'mode' => 'report']);

        return $v->fresh();
    }

    private function coupon(array $over = []): object
    {
        DB::table('coupons')->insert($over + ['code' => 'DIWALI20', 'type' => 'percent', 'value' => 20, 'max_discount_minor' => 50000, 'min_order_minor' => 0,
            'per_user_limit' => 1, 'active' => true, 'first_order_only' => false, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('coupons')->where('code', $over['code'] ?? 'DIWALI20')->first();
    }

    private function capture(object $order): void
    {
        $p = DB::table('payments')->where('order_id', $order->id)->first();
        $this->assertSame('applied', app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$order->id, (int) $p->amount_minor, 'upi'));
    }

    private function enableGst(): void
    {
        $s = app(SettingsRepository::class);
        $s->set('tax.gst_enabled', true);
        $s->set('tax.gstin', '10ABCDE1234F1Z5');   // 10 = Bihar
        $s->set('tax.legal_name', 'Jyotirveda Vigyan Sansthan');
        $s->set('tax.address', 'Kankarbagh, Patna');
    }

    private function fails(callable $fn, string $field, string $contains = ''): void
    {
        try {
            $fn();
            $this->fail("Expected a validation error on $field");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
            if ($contains) {
                $this->assertStringContainsString($contains, $e->errors()[$field][0]);
            }
        }
    }

    public function test_coupon_discounts_the_order_and_its_limits_hold(): void
    {
        $this->coupon();
        $v = $this->variant(100000);
        $u = $this->customer();
        $c = app(Checkout::class);

        $r = $c->startServiceOrder($u, $v, false, 'k1', [], ' diwali20 ');
        $o = $r['order'];
        $this->assertSame([100000, 20000, 80000, 80000], [(int) $o->list_minor, (int) $o->discount_minor, (int) $o->subtotal_minor, (int) $o->payable_minor]);
        $this->assertSame(80000, $r['razorpay']['amount']);
        $this->assertSame('DIWALI20', json_decode($o->snapshot, true)['coupon']['code']);

        // one use per customer, while the first order is pending
        $this->fails(fn () => $c->startServiceOrder($u, $v, false, 'k2', [], 'DIWALI20'), 'coupon', 'already used');
        // cancelling the unpaid order frees the coupon again
        $c->cancel($o);
        $this->assertSame(80000, (int) $c->startServiceOrder($u, $v, false, 'k3', [], 'DIWALI20')['order']->subtotal_minor);

        // maximum discount, minimum order, scope, dates, total uses, first order only, unknown code
        $this->coupon(['code' => 'BIG', 'value' => 90, 'max_discount_minor' => 30000]);
        $this->assertSame(70000, (int) $c->startServiceOrder($this->customer(), $v, false, 'k4', [], 'BIG')['order']->subtotal_minor);
        $this->coupon(['code' => 'MIN', 'min_order_minor' => 200000]);
        $this->fails(fn () => $c->startServiceOrder($this->customer(), $v, false, 'k5', [], 'MIN'), 'coupon', 'at least');
        $this->coupon(['code' => 'GEMS', 'applies_to' => json_encode(['product'])]);
        $this->fails(fn () => $c->startServiceOrder($this->customer(), $v, false, 'k6', [], 'GEMS'), 'coupon', 'cannot be used');
        $this->coupon(['code' => 'OLD', 'ends_at' => now()->subDay()]);
        $this->fails(fn () => $c->startServiceOrder($this->customer(), $v, false, 'k7', [], 'OLD'), 'coupon', 'not valid today');
        $this->coupon(['code' => 'ONCE', 'usage_limit' => 1, 'type' => 'flat', 'value' => 10000, 'max_discount_minor' => null]);
        $this->assertSame(90000, (int) $c->startServiceOrder($this->customer(), $v, false, 'k8', [], 'ONCE')['order']->subtotal_minor);
        $this->fails(fn () => $c->startServiceOrder($this->customer(), $v, false, 'k9', [], 'ONCE'), 'coupon', 'fully used');
        $this->fails(fn () => $c->startServiceOrder($this->customer(), $v, false, 'k10', [], 'NOPE'), 'coupon', 'not valid');
        $this->coupon(['code' => 'FIRST', 'first_order_only' => true]);
        $repeat = $this->customer();
        $this->capture($c->startServiceOrder($repeat, $v, false, 'k11')['order']);
        $this->fails(fn () => $c->startServiceOrder($repeat, $v, false, 'k12', [], 'FIRST'), 'coupon', 'first order');

        // a 100% coupon on a service is paid at once; recharges never take a coupon
        $this->coupon(['code' => 'FREE', 'value' => 100, 'max_discount_minor' => null]);
        $free = $c->startServiceOrder($this->customer(), $v, false, 'k13', [], 'FREE');
        $this->assertSame(['paid', 0], [$free['order']->status, (int) $free['order']->subtotal_minor]);
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_coupon_on_web_checkout_gem_store_and_app_preview(): void
    {
        $this->coupon(['code' => 'GEM10', 'value' => 10, 'max_discount_minor' => null, 'applies_to' => json_encode(['product'])]);
        $u = $this->customer();
        $ref = (string) Str::ulid();
        DB::table('products')->insert(['ref' => $ref, 'slug' => 'ruby-'.$ref, 'category' => 'gemstone', 'name' => 'Ruby', 'summary' => 'Burma', 'price_minor' => 500000,
            'stock' => 3, 'max_per_order' => 2, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $token = $u->createToken('t', ['customer'])->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/coupons/check', ['code' => 'gem10', 'product' => $ref, 'quantity' => 2])->assertOk()
            ->assertJsonPath('data.discount_minor', 100000)->assertJsonPath('data.total_minor', 900000);
        $this->withToken($token)->postJson('/api/v1/coupons/check', ['code' => 'GEM10', 'variant' => $this->variant()->ref])->assertJsonValidationErrors('coupon');

        $addr = ['ship_name' => 'Asha', 'ship_phone' => '9123456789', 'ship_line1' => 'House 1', 'ship_city' => 'Pune', 'ship_state' => 'Maharashtra', 'ship_pincode' => '411001', 'agree' => '1'];
        $this->actingAs($u)->post(route('store.buy'), $addr + ['product' => $ref, 'quantity' => 1, 'key' => 'g1', 'coupon' => 'GEM10'])->assertRedirect();
        $o = DB::table('orders')->where('kind', 'product')->first();
        $this->assertSame([500000, 50000, 450000], [(int) $o->list_minor, (int) $o->discount_minor, (int) $o->subtotal_minor]);
        $this->actingAs($u)->get(route('checkout.show', $o->ref))->assertSee('Coupon GEM10')->assertSee('₹4,500');

        $this->actingAs($u)->post(route('checkout.buy'), ['variant' => $this->variant()->ref, 'key' => 'w1', 'coupon' => 'GEM10'])->assertSessionHasErrors('coupon');
    }

    public function test_gst_invoice_ledger_and_credit_note(): void
    {
        $this->enableGst();
        $u = $this->customer();
        $o = app(Checkout::class)->startServiceOrder($u, $this->variant(118000), false, 'g1')['order'];
        $this->capture($o);
        $o = DB::table('orders')->find($o->id);
        $this->assertSame(18000, (int) $o->tax_minor);   // 18% included in ₹1,180
        $inv = DB::table('invoices')->where('order_id', $o->id)->first();
        $this->assertMatchesRegularExpression('#^ARJ/\d{2}-\d{2}/0{5}1$#', $inv->number);
        $this->assertLessThanOrEqual(16, strlen($inv->number));
        $this->assertSame([100000, 9000, 9000, 0, 118000], [(int) $inv->taxable_minor, (int) $inv->cgst_minor, (int) $inv->sgst_minor, (int) $inv->igst_minor, (int) $inv->total_minor]);
        $this->assertSame(18000, (int) DB::table('ledger_accounts')->where('code', 'gst_output')->value('balance_minor'));
        $this->assertSame(100000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));

        // receipt links the invoice; another customer cannot open it
        $this->actingAs($u)->get(route('account.receipt', $o->ref))->assertSee($inv->number);
        $html = $this->actingAs($u)->get(route('account.invoice', $inv->ref))->assertOk()->getContent();
        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('10ABCDE1234F1Z5', $html);
        $this->assertStringContainsString('CGST @ 9%', $html);
        $this->actingAs($this->customer())->get(route('account.invoice', $inv->ref))->assertNotFound();

        // a refund reverses GST in proportion and issues a numbered credit note
        $a = $this->makeStaff(['finance']);
        $b = $this->makeStaff(['finance']);
        $r = app(Refunds::class)->request($o, 59000, 'source', 'Half refund', $a);
        app(Refunds::class)->decide($r->ref, true, $b);
        $cn = DB::table('invoices')->where('type', 'credit_note')->first();
        $this->assertMatchesRegularExpression('#^ARJC/\d{2}-\d{2}/0{4}1$#', $cn->number);
        $this->assertSame([59000, 4500, 4500], [(int) $cn->total_minor, (int) $cn->cgst_minor, (int) $cn->sgst_minor]);
        $this->assertSame(9000, (int) DB::table('ledger_accounts')->where('code', 'gst_output')->value('balance_minor'));
        $this->assertSame([], app(Ledger::class)->verify());

        // gems delivered to another state are charged IGST at the gem rate
        $ref = (string) Str::ulid();
        DB::table('products')->insert(['ref' => $ref, 'slug' => 'ruby-'.$ref, 'category' => 'gemstone', 'name' => 'Ruby', 'summary' => 'Burma', 'price_minor' => 103000,
            'stock' => 1, 'max_per_order' => 1, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $po = app(Store::class)->buy($u, $ref, 1, ['name' => 'Asha', 'phone' => '9123456789', 'line1' => 'House 1', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001'], 'p1')['order'];
        $this->capture($po);
        $gi = DB::table('invoices')->where('order_id', $po->id)->first();
        $this->assertSame([3000, 0, '27 Maharashtra'], [(int) $gi->igst_minor, (int) $gi->cgst_minor, $gi->place_of_supply]);
        $this->assertStringEndsWith('/000002', $gi->number);

        // recharges are not invoiced
        DB::table('recharge_packages')->insert(['amount_minor' => 50000, 'bonus_minor' => 0, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $rc = app(Checkout::class)->startRecharge($u, DB::table('recharge_packages')->first(), 'r1')['order'];
        $this->capture($rc);
        $this->assertSame(0, DB::table('invoices')->where('order_id', $rc->id)->count());

        // the app sees the invoices on the order
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('t', ['customer'])->plainTextToken)->getJson('/api/v1/orders/'.$o->ref)->assertOk()
            ->assertJsonPath('data.tax_minor', 18000)->assertJsonPath('data.invoices.0.number', $inv->number)->assertJsonPath('data.invoices.1.type', 'credit_note');
    }

    public function test_no_invoice_or_tax_while_gst_is_off(): void
    {
        $o = app(Checkout::class)->startServiceOrder($this->customer(), $this->variant(118000), false, 'n1')['order'];
        $this->capture($o);
        $this->assertSame(0, (int) DB::table('orders')->find($o->id)->tax_minor);
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(118000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
    }

    public function test_admin_coupon_and_gst_settings_need_permission_and_step_up(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($this->makeStaff(['support']))->get(route('admin.coupons.index'))->assertForbidden();
        $form = ['code' => 'new-year', 'type' => 'percent', 'value' => '15', 'max_discount' => '300', 'per_user_limit' => 1, 'active' => '1', 'applies_to' => ['report', 'bundle']];
        $this->asStaff($owner, freshStepUp: false)->post(route('admin.coupons.save'), $form)->assertRedirect(route('staff.step-up'));
        $this->asStaff($owner)->post(route('admin.coupons.save'), $form)->assertSessionHasNoErrors();
        $c = DB::table('coupons')->first();
        $this->assertSame(['NEW-YEAR', 15, 30000, '["report","bundle"]'], [$c->code, (int) $c->value, (int) $c->max_discount_minor, $c->applies_to]);
        $this->asStaff($owner)->post(route('admin.coupons.save'), array_merge($form, ['value' => '150', 'code' => 'XYZ1']))->assertSessionHasErrors('value');
        $this->asStaff($owner)->get(route('admin.coupons.index'))->assertOk()->assertSee('NEW-YEAR')->assertSee('15% off (up to ₹300)');

        $tax = ['gst_enabled' => '1', 'gstin' => '10ABCDE1234F1Z5', 'legal_name' => 'JVS', 'address' => 'Patna', 'service_rate' => '18', 'product_rate' => '3',
            'service_sac' => '999799', 'product_hsn' => '7103', 'invoice_prefix' => 'ARJ'];
        $this->asStaff($owner)->put(route('admin.payments.tax'), ['gstin' => 'bad'] + $tax)->assertSessionHasErrors('gstin');
        $this->asStaff($owner)->put(route('admin.payments.tax'), ['invoice_prefix' => 'TOOLONG'] + $tax)->assertSessionHasErrors('invoice_prefix');
        $this->asStaff($owner)->put(route('admin.payments.tax'), $tax)->assertSessionHasNoErrors();
        $this->assertTrue(app(Invoices::class)->enabled());
        $this->asStaff($owner)->get(route('admin.payments.settings'))->assertOk()->assertSee('GST tax invoices')->assertSee('RazorpayX payouts')->assertSee('Courier (Shiprocket)');
    }

    public function test_pdf_downloads_for_invoice_and_report(): void
    {
        $bin = env('PDF_CHROME_BINARY');
        if (! $bin || ! is_executable($bin)) {
            $this->markTestSkipped('Set PDF_CHROME_BINARY to a Chromium binary to run the PDF test.');
        }
        config(['pdf.binary' => $bin]);
        $this->enableGst();
        $u = $this->customer();
        $o = app(Checkout::class)->startServiceOrder($u, $this->variant(118000), false, 'pdf1')['order'];
        $this->capture($o);
        $inv = DB::table('invoices')->first();
        $res = $this->actingAs($u)->get(route('account.invoice.pdf', $inv->ref))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());

        DB::table('report_jobs')->insert(['ref' => $jr = (string) Str::ulid(), 'user_id' => $u->id, 'order_id' => $o->id, 'offering_slug' => 'career-report', 'status' => 'completed', 'language' => 'hi',
            'sections' => json_encode(['tenth' => 'दशम भाव मज़बूत है। करियर में प्रगति के योग हैं।']), 'completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pdf = $this->actingAs($u)->get(route('account.reports.pdf', $jr))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->actingAs($this->customer())->get(route('account.reports.pdf', $jr))->assertNotFound();
        $this->assertSame([], glob(storage_path('app/pdf-tmp/*')) ?: [], 'temporary files removed');
    }

    public function test_pdf_unavailable_is_a_clear_error(): void
    {
        config(['pdf.binary' => '/nonexistent/chromium']);
        $this->enableGst();
        $u = $this->customer();
        $o = app(Checkout::class)->startServiceOrder($u, $this->variant(118000), false, 'pdf2')['order'];
        $this->capture($o);
        $this->actingAs($u)->get(route('account.invoice.pdf', DB::table('invoices')->value('ref')))->assertStatus(503);
    }
}
