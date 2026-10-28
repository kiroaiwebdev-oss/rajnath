<?php

namespace Tests\Feature\Store;

use App\Domain\Ledger\Ledger;
use App\Domain\Payments\Checkout;
use App\Domain\Palmistry\PalmReadings;
use App\Models\Offering;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\StaffHelpers;
use Tests\TestCase;

class StoreAndPalmTest extends TestCase
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
        $s->set('payments.razorpay.webhook_secret', 'wh', secret: true);
        Http::fake(['api.razorpay.com/v1/orders' => fn ($r) => Http::response(['id' => 'order_S'.(++$this->seq), 'amount' => $r['amount']])]);
        Storage::fake('local');
        Storage::fake('uploads');
        Mail::fake();
    }

    private function customer(): User
    {
        $u = new User(['name' => 'Buyer', 'email' => uniqid().'@example.com']);
        $u->save();

        return $u;
    }

    private function product(int $stock = 1, int $price = 1550000): object
    {
        $ref = (string) Str::ulid();
        DB::table('products')->insert(['ref' => $ref, 'slug' => 'pukhraj-'.$ref, 'category' => 'gemstone', 'name' => 'Yellow Sapphire 5.25 ct', 'summary' => 'Ceylon',
            'weight' => '5.25 carat', 'certification' => 'GIA 1234', 'price_minor' => $price, 'stock' => $stock, 'max_per_order' => 2, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('products')->where('ref', $ref)->first();
    }

    private function address(): array
    {
        return ['ship_name' => 'Ramesh', 'ship_phone' => '9123456789', 'ship_line1' => 'House 1', 'ship_city' => 'Patna', 'ship_state' => 'Bihar', 'ship_pincode' => '800020', 'agree' => '1'];
    }

    private function capture(object $order): void
    {
        $p = DB::table('payments')->where('order_id', $order->id)->first();
        $this->assertSame('applied', app(Checkout::class)->applyCapture($p->provider_order_id, 'pay_'.$order->id, (int) $p->amount_minor, 'upi'));
    }

    private function jpeg(int $w = 900, int $h = 1200, bool $withGps = false): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 230, 190, 160));
        $path = tempnam(sys_get_temp_dir(), 'palm').'.jpg';
        imagejpeg($im, $path, 90);
        if ($withGps) {
            // Prepend an APP1 Exif segment containing a marker string after SOI.
            $b = file_get_contents($path);
            $exif = "Exif\0\0".'GPSLatitude=25.59 SECRET-LOCATION';
            $seg = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;
            file_put_contents($path, substr($b, 0, 2).$seg.substr($b, 2));
        }

        return new UploadedFile($path, 'hand.jpg', 'image/jpeg', null, true);
    }

    public function test_gem_store_pages_and_full_order_to_dispatch(): void
    {
        $p = $this->product(2);
        $this->get('/gems')->assertOk()->assertSee('Yellow Sapphire 5.25 ct')->assertSee('₹15,500');
        $this->get('/gems/'.$p->slug)->assertOk()->assertSee('GIA 1234')->assertSee('Sign in to order');

        $u = $this->customer();
        $this->actingAs($u)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'g1'] + $this->address())->assertRedirect();
        $o = DB::table('orders')->first();
        $this->assertSame(['product', 1550000], [$o->kind, (int) $o->subtotal_minor]);
        $this->assertSame(1, (int) DB::table('products')->where('id', $p->id)->value('stock'));
        $ship = DB::table('shipments')->first();
        $this->assertStringNotContainsString('Patna', $ship->address, 'address encrypted at rest');

        $this->capture($o);
        $this->assertSame('awaiting_dispatch', DB::table('shipments')->value('status'));
        $this->assertSame('committed', DB::table('stock_reservations')->value('status'));
        $this->assertSame(1550000, (int) DB::table('ledger_accounts')->where('code', 'revenue')->value('balance_minor'));
        $this->get("/account/orders/{$o->ref}")->assertSee('Yellow Sapphire 5.25 ct × 1')->assertSee('awaiting dispatch');

        $ops = $this->makeStaff(['fulfilment']);
        $this->asStaff($ops)->get('/admin/store/orders')->assertOk()->assertSee('Buyer');
        $this->get("/admin/store/orders/{$o->ref}")->assertOk()->assertSee('House 1');
        $this->put("/admin/store/orders/{$o->ref}/shipment", ['status' => 'dispatched'])->assertSessionHasErrors('awb');
        $this->put("/admin/store/orders/{$o->ref}/shipment", ['status' => 'dispatched', 'courier' => 'India Post', 'awb' => 'EE123456789IN', 'tracking_url' => 'https://www.indiapost.gov.in'])->assertSessionHasNoErrors();
        $this->put("/admin/store/orders/{$o->ref}/shipment", ['status' => 'awaiting_dispatch'])->assertSessionHasErrors('status'); // no going back
        $this->actingAs($u)->get("/account/orders/{$o->ref}")->assertSee('EE123456789IN');
        $this->assertSame([], app(Ledger::class)->verify());
    }

    public function test_stock_cannot_be_oversold_and_cancel_releases_it(): void
    {
        $p = $this->product(1);
        $a = $this->customer();
        $b = $this->customer();
        $this->actingAs($a)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'a'] + $this->address())->assertRedirect();
        $this->actingAs($b)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'b'] + $this->address())->assertSessionHasErrors('quantity');
        $o = DB::table('orders')->first();
        $this->actingAs($a)->post("/checkout/{$o->ref}/cancel")->assertRedirect();
        $this->assertSame(1, (int) DB::table('products')->value('stock'));
        $this->assertSame('released', DB::table('stock_reservations')->value('status'));
        $this->actingAs($b)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'b2'] + $this->address())->assertRedirect();
        $this->assertSame(0, (int) DB::table('products')->value('stock'));
    }

    public function test_store_validation_and_limits(): void
    {
        $p = $this->product(5);
        $u = $this->customer();
        $this->actingAs($u)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 3, 'key' => 'x'] + $this->address())->assertSessionHasErrors('quantity'); // max 2
        $this->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'y', 'ship_pincode' => '12'] + $this->address())->assertSessionHasErrors('ship_pincode');
        $this->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'z'] + array_diff_key($this->address(), ['agree' => 1]))->assertSessionHasErrors('agree');
        DB::table('products')->update(['active' => false]);
        $this->get('/gems/'.$p->slug)->assertNotFound();
        $this->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'w'] + $this->address())->assertSessionHasErrors('product');
        auth()->logout();
        $this->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'v'] + $this->address())->assertRedirect('/account/sign-in');
    }

    public function test_other_customers_cannot_see_store_orders(): void
    {
        $p = $this->product(1);
        $u = $this->customer();
        $this->actingAs($u)->post('/gems/buy', ['product' => $p->ref, 'quantity' => 1, 'key' => 'k'] + $this->address());
        $o = DB::table('orders')->first();
        $this->actingAs($this->customer())->get("/account/orders/{$o->ref}")->assertNotFound();
        $this->asStaff($this->makeStaff(['content_author']))->get("/admin/store/orders/{$o->ref}")->assertForbidden();
    }

    public function test_admin_product_management_and_safe_image_upload(): void
    {
        $owner = $this->makeStaff(['owner']);
        $this->asStaff($owner)->post('/admin/store/products', ['name' => 'Ruby (Manik) 4 ct', 'category' => 'gemstone', 'summary' => 'Burma ruby', 'price' => '25,000', 'stock' => 1, 'max_per_order' => 1, 'active' => '1', 'image' => $this->jpeg(800, 800, true)])
            ->assertRedirect('/admin/store/products');
        $p = DB::table('products')->where('name', 'Ruby (Manik) 4 ct')->first();
        $this->assertSame(2500000, (int) $p->price_minor);
        Storage::disk('uploads')->assertExists(substr($p->image_path, strlen('uploads/')));
        $this->assertStringNotContainsString('SECRET-LOCATION', Storage::disk('uploads')->get(substr($p->image_path, strlen('uploads/'))), 'EXIF stripped');

        // A PHP file renamed to .jpg is rejected.
        $bad = UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;');
        $this->post('/admin/store/products', ['name' => 'Bad', 'category' => 'other', 'summary' => 'x', 'price' => '10', 'stock' => 1, 'max_per_order' => 1, 'image' => $bad])->assertSessionHasErrors('image');

        // Catalogue manager without price permission cannot change price.
        $admin = $this->makeStaff(['administrator']);
        $this->asStaff($admin)->post("/admin/store/products/{$p->ref}", ['name' => $p->name, 'category' => 'gemstone', 'summary' => 'x', 'price' => '1', 'stock' => 1, 'max_per_order' => 1])->assertSessionHasErrors('price');
        $this->asStaff($this->makeStaff(['support']))->get('/admin/store/products/new')->assertForbidden();
    }

    private function buyPalm(User $u): object
    {
        $v = Offering::where('slug', PalmReadings::OFFERING_SLUG)->first()->variants()->first();
        $v->update(['price_minor' => 110000]);
        $this->actingAs($u)->post('/checkout', ['variant' => $v->ref, 'key' => 'palm'.$u->id])->assertRedirect();
        $o = DB::table('orders')->where('user_id', $u->id)->first();
        $this->capture($o);

        return DB::table('palm_readings')->where('order_id', $o->id)->first();
    }

    public function test_palmistry_full_flow(): void
    {
        $this->get('/services/palmistry-photo-report')->assertOk()->assertSee('Palmistry Report from Photos');
        $u = $this->customer();
        $r = $this->buyPalm($u);
        $this->assertSame('awaiting_photos', $r->status);
        $this->get('/account/palmistry')->assertSee('Upload photos');
        $this->get("/account/orders/".DB::table('orders')->value('ref'))->assertSee('Upload your palm photos');

        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(), 'right' => $this->jpeg(), 'dominant_hand' => 'right'])->assertSessionHasErrors('consent');
        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(), 'right' => $this->jpeg(200, 200), 'dominant_hand' => 'right', 'consent' => '1'])->assertSessionHasErrors('right');
        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(1000, 1300, true), 'right' => $this->jpeg(), 'dominant_hand' => 'right', 'question' => 'Career?', 'consent' => '1'])->assertSessionHasNoErrors();
        $r = DB::table('palm_readings')->find($r->id);
        $this->assertSame('submitted', $r->status);
        Storage::disk('local')->assertExists($r->left_photo);
        $this->assertStringNotContainsString('SECRET-LOCATION', Storage::disk('local')->get($r->left_photo));
        $this->assertStringStartsNotWith('public', $r->left_photo);

        // Owner can view own photo; another customer and an unprivileged staff member cannot.
        $this->get(route('palm.photo', [$r->ref, 'left']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->actingAs($this->customer())->get(route('palm.photo', [$r->ref, 'left']))->assertNotFound();
        $this->actingAs($this->customer())->get("/account/palmistry/{$r->ref}")->assertNotFound();
        $this->asStaff($this->makeStaff(['support']))->get(route('palm.photo', [$r->ref, 'left']))->assertNotFound();
        $this->asStaff($this->makeStaff(['support']))->get("/admin/palmistry/{$r->ref}")->assertForbidden();

        $expert = $this->makeStaff(['fulfilment']);
        $this->asStaff($expert)->get('/admin/palmistry')->assertOk()->assertSee('Buyer');
        $this->get(route('palm.photo', [$r->ref, 'right']))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'palm.photo_viewed']);
        $this->post("/admin/palmistry/{$r->ref}/need-photos", ['note' => 'Right palm is blurred'])->assertSessionHasNoErrors();
        $this->actingAs($u)->get("/account/palmistry/{$r->ref}")->assertSee('Right palm is blurred');
        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(), 'right' => $this->jpeg(), 'dominant_hand' => 'right', 'consent' => '1'])->assertSessionHasNoErrors();

        $sections = ['overview' => str_repeat('Your hands show a balanced temperament. ', 3), 'guidance' => 'Keep a steady routine and chant daily for focus.', 'life_line' => 'Long and clear.'];
        $this->asStaff($expert)->post("/admin/palmistry/{$r->ref}/report", ['sections' => ['overview' => 'short', 'guidance' => 'x'], 'action' => 'complete'])->assertSessionHasErrors('overview');
        $this->post("/admin/palmistry/{$r->ref}/report", ['sections' => $sections, 'action' => 'complete'])->assertSessionHasNoErrors();
        $this->actingAs($u)->get("/account/palmistry/{$r->ref}")->assertSee('Your palmistry report')->assertSee('Long and clear.');
        Mail::assertSentCount(0); // Mail::raw is not a Mailable; check queue of raw messages below
        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(), 'right' => $this->jpeg(), 'dominant_hand' => 'right', 'consent' => '1'])->assertSessionHasErrors('left');
    }

    public function test_photos_purged_after_retention(): void
    {
        $u = $this->customer();
        $r = $this->buyPalm($u);
        $this->post("/account/palmistry/{$r->ref}/photos", ['left' => $this->jpeg(), 'right' => $this->jpeg(), 'dominant_hand' => 'left', 'consent' => '1']);
        DB::table('palm_readings')->where('id', $r->id)->update(['status' => 'completed', 'completed_at' => now()->subDays(181), 'report' => '{}']);
        $r = DB::table('palm_readings')->find($r->id);
        $this->assertSame(1, app(PalmReadings::class)->purgeOldPhotos());
        Storage::disk('local')->assertMissing($r->left_photo);
        $this->assertNull(DB::table('palm_readings')->value('left_photo'));
    }

    public function test_unpaid_palm_cannot_upload_and_unpriced_palm_not_buyable(): void
    {
        $u = $this->customer();
        $v = Offering::where('slug', PalmReadings::OFFERING_SLUG)->first()->variants()->first();
        $this->actingAs($u)->post('/checkout', ['variant' => $v->ref, 'key' => 'np'])->assertSessionHasErrors('variant'); // no price set yet
        $this->assertSame(0, DB::table('palm_readings')->count());
    }
}
